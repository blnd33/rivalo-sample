<?php

namespace App\Services;

use App\Enums\EligibilityStatus;
use App\Enums\MatchStatus;
use App\Enums\RegistrationStatus;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Enums\ThirdPlaceMethod;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\MatchParticipant;
use App\Models\MatchResult;
use App\Models\Stage;
use App\Models\StageGroup;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Draws and fixtures (Milestone 5).
 *
 * - Entrants are entries with a reserved place that are eligible (checked in, fee settled).
 * - Seeded entries (entries.seed) are placed first; the rest are drawn at random with a recorded
 *   draw seed, so any draw can be reproduced and checked.
 * - Single elimination uses the standard seeding order; byes go to the top seeds and advance
 *   automatically. A third-place match is created when the tournament's third-place method
 *   is "playoff match" and there are at least 4 entrants.
 * - Round robin uses the circle method. Groups → knockout plays round-robin groups, then
 *   builds a knockout from the group standings (group winners seeded first).
 * - Draws can be regenerated until the first result is submitted.
 */
class FixtureService
{
    public const DEFAULT_TABLE_POINTS = [3, 1, 0]; // win, draw, loss (group tables only, not ranking points)

    public function generate(Tournament $tournament, User $actor, array $options = []): Stage
    {
        $this->assertCan($actor, $tournament);

        return DB::transaction(function () use ($tournament, $actor, $options) {
            $tournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            if (! in_array($tournament->status, [TournamentStatus::RegistrationClosed, TournamentStatus::InProgress], true)) {
                throw DomainRuleException::make('wrong_status', 'Close registration before making the draw.', 409);
            }
            if (MatchResult::whereIn('match_id', $tournament->matches()->select('id'))->exists()) {
                throw DomainRuleException::make('results_exist', 'Results are already recorded, so the draw can no longer be changed.', 409);
            }

            $entries = $this->eligibleEntries($tournament);
            $minimum = max(2, (int) $tournament->min_entrants);
            if ($entries->count() < $minimum) {
                throw DomainRuleException::make('not_enough_entrants', 'Only :count eligible entrants (checked in, fee settled). At least :min are needed.', 409, [
                    'count' => $entries->count(), 'min' => $minimum,
                ]);
            }

            // Replace any earlier draw (no results exist yet).
            $hadDraw = $tournament->stages()->exists();
            $tournament->matches()->update(['next_match_id' => null, 'loser_next_match_id' => null]);
            $tournament->stages()->delete();

            $drawSeed = (int) ($options['draw_seed'] ?? random_int(1, 2_147_483_646));
            $ordered = $this->seedOrder($entries, $drawSeed);
            $settings = [
                'draw_seed' => $drawSeed,
                'table_points' => $options['table_points'] ?? self::DEFAULT_TABLE_POINTS,
            ];

            $stage = match ($tournament->format) {
                TournamentFormat::SingleElimination => $this->singleElimination($tournament, $actor, $ordered, $settings),
                TournamentFormat::RoundRobin => $this->roundRobin($tournament, $actor, $ordered, $settings),
                TournamentFormat::GroupsKnockout => $this->groups($tournament, $actor, $ordered, $settings, $options),
            };

            Audit::record($hadDraw ? 'fixtures.regenerated' : 'fixtures.generated', $tournament, new: [
                'format' => $tournament->format->value,
                'entrants' => $ordered->count(),
                'draw_seed' => $drawSeed,
                'matches' => $tournament->matches()->count(),
            ], actorId: $actor->id);

            return $stage;
        });
    }

    /** After every group match is finished, build the knockout from the group standings. */
    public function generateKnockout(Tournament $tournament, User $actor): Stage
    {
        $this->assertCan($actor, $tournament);

        return DB::transaction(function () use ($tournament, $actor) {
            $tournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            $groupsStage = $tournament->stages()->where('type', StageType::Groups->value)->first();
            if (! $groupsStage) {
                throw DomainRuleException::make('no_groups', 'This tournament has no group stage.', 409);
            }
            if ($tournament->stages()->where('type', StageType::Knockout->value)->exists()) {
                throw DomainRuleException::make('knockout_exists', 'The knockout stage already exists.', 409);
            }
            if ($groupsStage->matches()->whereNotIn('status', [MatchStatus::Completed->value, MatchStatus::Cancelled->value])->exists()) {
                throw DomainRuleException::make('groups_unfinished', 'Every group match needs an approved result first.', 409);
            }

            $advance = (int) $groupsStage->setting('advance_per_group', 2);
            $standings = app(StandingsService::class);
            $byPlace = [];
            foreach ($groupsStage->groups as $group) {
                foreach (array_slice($standings->table($groupsStage, $group), 0, $advance) as $i => $row) {
                    $byPlace[$i][] = $row['entry_id'];
                }
            }
            // Group winners first (A1, B1, …), then runners-up (A2, B2, …): the bracket seeding keeps
            // teams from the same group apart in the first round.
            $qualified = TournamentEntry::findMany(array_merge(...$byPlace))->keyBy('id');
            $ordered = collect(array_merge(...$byPlace))->map(fn ($id) => $qualified[$id]);

            $groupsStage->update(['status' => StageStatus::Completed]);
            $stage = $this->createStage($tournament, $actor, StageType::Knockout, 'Knockout', 2, [
                'qualified_from_groups' => $advance,
            ], $groupsStage->isPublic() ? StageStatus::Published : StageStatus::Draft);
            $this->buildBracket($stage, $ordered, $this->wantsThirdPlace($tournament, $ordered->count()));

            Audit::record('fixtures.knockout_generated', $tournament, new: ['qualified' => $ordered->count()], actorId: $actor->id);

            return $stage;
        });
    }

    /** Make draft fixtures visible to players. */
    public function publish(Tournament $tournament, User $actor): int
    {
        $this->assertCan($actor, $tournament);
        $count = $tournament->stages()->where('status', StageStatus::Draft->value)->update(['status' => StageStatus::Published->value]);
        if ($count === 0) {
            throw DomainRuleException::make('nothing_to_publish', 'There are no draft fixtures to publish.', 409);
        }
        Audit::record('fixtures.published', $tournament, new: ['stages' => $count], actorId: $actor->id);
        app(Notifier::class)->sendToEntries($this->eligibleEntries($tournament)->pluck('id'), 'fixtures_published', ['tournament' => $tournament->name], ['tournament', $tournament->slug]);

        return $count;
    }

    public function schedule(TournamentMatch $match, User $actor, ?CarbonInterface $at, ?string $location): TournamentMatch
    {
        $this->assertCan($actor, $match->tournament);
        $old = ['scheduled_at' => $match->scheduled_at?->toIso8601String(), 'location' => $match->location];
        $match->update(['scheduled_at' => $at, 'location' => $location]);
        Audit::record('match.scheduled', $match->tournament, $old, ['match_id' => $match->id, 'scheduled_at' => $at?->toIso8601String(), 'location' => $location], actorId: $actor->id);
        if ($at || $location) {
            $t = $match->tournament;
            app(Notifier::class)->sendToEntries($match->participants()->pluck('tournament_entry_id'), 'match_scheduled', [
                'match' => $match->label ?? 'Round '.$match->round,
                'tournament' => $t->name,
                'time' => $at?->copy()->timezone($t->timezone)->format('d M H:i') ?? '',
                'location' => $location ? '· '.$location : '',
            ], ['match', $match->id]);
        }

        return $match;
    }

    // ---------------------------------------------------------------- Bracket movement (used by ResultService)

    /** Put the winner (and loser, for a third-place match) into the next matches. */
    public function advance(TournamentMatch $match, ?int $winnerEntryId, ?int $loserEntryId): void
    {
        if ($match->next_match_id) {
            $this->place($match->next_match_id, (int) $match->next_slot, $winnerEntryId);
        }
        if ($match->loser_next_match_id) {
            $this->place($match->loser_next_match_id, (int) $match->loser_next_slot, $loserEntryId);
        }
    }

    public function place(int $matchId, int $slot, ?int $entryId): void
    {
        MatchParticipant::updateOrCreate(['match_id' => $matchId, 'slot' => $slot], ['tournament_entry_id' => $entryId]);
        $next = TournamentMatch::with('participants')->findOrFail($matchId);
        if (! $next->status->isFinished()) {
            $filled = $next->participants->whereNotNull('tournament_entry_id')->count();
            $next->update(['status' => $filled === 2 ? MatchStatus::Ready : MatchStatus::Pending]);
        }
    }

    public function completeStageIfDone(Stage $stage): void
    {
        $open = $stage->matches()->whereNotIn('status', [MatchStatus::Completed->value, MatchStatus::Cancelled->value, MatchStatus::Bye->value])->exists();
        if (! $open && $stage->status === StageStatus::Published) {
            $stage->update(['status' => StageStatus::Completed]);
        }
    }

    // ---------------------------------------------------------------- Formats

    private function singleElimination(Tournament $t, User $actor, Collection $entries, array $settings): Stage
    {
        $third = $this->wantsThirdPlace($t, $entries->count());
        $stage = $this->createStage($t, $actor, StageType::SingleElimination, 'Knockout', 1, $settings + ['third_place_match' => $third]);
        $this->buildBracket($stage, $entries, $third);

        return $stage;
    }

    private function roundRobin(Tournament $t, User $actor, Collection $entries, array $settings): Stage
    {
        $stage = $this->createStage($t, $actor, StageType::RoundRobin, 'League', 1, $settings);
        $this->buildRoundRobin($stage, null, $entries);

        return $stage;
    }

    private function groups(Tournament $t, User $actor, Collection $entries, array $settings, array $options): Stage
    {
        $size = (int) ($options['group_size'] ?? 4);
        $advance = (int) ($options['advance_per_group'] ?? 2);
        if ($size < 2 || $advance < 1 || $advance >= $size) {
            throw DomainRuleException::make('invalid_groups', 'Groups need at least 2 entrants, and fewer must advance than play in each group.', 422);
        }
        $groupCount = (int) ceil($entries->count() / $size);
        if ($groupCount * $advance < 2) {
            throw DomainRuleException::make('invalid_groups', 'At least two entrants must reach the knockout. Use smaller groups or let more advance.', 422);
        }

        $stage = $this->createStage($t, $actor, StageType::Groups, 'Group stage', 1, $settings + [
            'group_size' => $size, 'advance_per_group' => $advance,
        ]);

        // Snake distribution keeps seeds spread across groups: A B C C B A A B C …
        $buckets = array_fill(0, $groupCount, []);
        foreach ($entries->values() as $i => $entry) {
            $round = intdiv($i, $groupCount);
            $pos = $i % $groupCount;
            $buckets[$round % 2 === 0 ? $pos : $groupCount - 1 - $pos][] = $entry;
        }

        foreach ($buckets as $i => $bucket) {
            $group = StageGroup::create(['stage_id' => $stage->id, 'name' => chr(65 + $i), 'sequence' => $i + 1]);
            $this->buildRoundRobin($stage, $group, collect($bucket));
        }

        return $stage;
    }

    // ---------------------------------------------------------------- Builders

    private function buildBracket(Stage $stage, Collection $entries, bool $thirdPlace): void
    {
        $entries = $entries->values();
        $n = $entries->count();
        $size = 2;
        while ($size < $n) {
            $size *= 2;
        }
        $rounds = (int) log($size, 2);
        $order = $this->bracketOrder($size);

        /** @var array<int, array<int, TournamentMatch>> $grid */
        $grid = [];
        for ($r = 1; $r <= $rounds; $r++) {
            $inRound = intdiv($size, 2 ** $r);
            for ($p = 1; $p <= $inRound; $p++) {
                $grid[$r][$p] = TournamentMatch::create([
                    'tournament_id' => $stage->tournament_id,
                    'stage_id' => $stage->id,
                    'round' => $r,
                    'position' => $p,
                    'label' => $this->roundLabel($r, $rounds, $size),
                    'status' => MatchStatus::Pending,
                ]);
            }
        }
        for ($r = 1; $r < $rounds; $r++) {
            foreach ($grid[$r] as $p => $match) {
                $match->update(['next_match_id' => $grid[$r + 1][(int) ceil($p / 2)]->id, 'next_slot' => $p % 2 === 1 ? 1 : 2]);
            }
        }
        if ($thirdPlace && $rounds >= 2) {
            $third = TournamentMatch::create([
                'tournament_id' => $stage->tournament_id,
                'stage_id' => $stage->id,
                'round' => $rounds,
                'position' => 2,
                'label' => 'Third place',
                'status' => MatchStatus::Pending,
            ]);
            foreach ($grid[$rounds - 1] as $p => $semi) {
                $semi->update(['loser_next_match_id' => $third->id, 'loser_next_slot' => $p]);
            }
        }

        // First round: seed pairs; a missing opponent is a bye and the entrant moves straight on.
        foreach ($grid[1] as $p => $match) {
            $a = $entries->get($order[($p - 1) * 2] - 1);
            $b = $entries->get($order[($p - 1) * 2 + 1] - 1);
            MatchParticipant::create(['match_id' => $match->id, 'slot' => 1, 'tournament_entry_id' => $a?->id]);
            MatchParticipant::create(['match_id' => $match->id, 'slot' => 2, 'tournament_entry_id' => $b?->id]);

            if ($a && $b) {
                $match->update(['status' => MatchStatus::Ready]);
            } else {
                $winner = $a ?? $b;
                $match->update(['status' => MatchStatus::Bye, 'winner_entry_id' => $winner?->id]);
                $this->advance($match->fresh(), $winner?->id, null);
            }
        }
    }

    private function buildRoundRobin(Stage $stage, ?StageGroup $group, Collection $entries): void
    {
        $list = $entries->values()->all();
        if (count($list) % 2 === 1) {
            $list[] = null; // the entrant drawn against "null" rests that round
        }
        $n = count($list);
        $position = 0;
        for ($round = 1; $round < $n; $round++) {
            for ($i = 0; $i < $n / 2; $i++) {
                [$a, $b] = [$list[$i], $list[$n - 1 - $i]];
                if ($a && $b) {
                    $match = TournamentMatch::create([
                        'tournament_id' => $stage->tournament_id,
                        'stage_id' => $stage->id,
                        'stage_group_id' => $group?->id,
                        'round' => $round,
                        'position' => ++$position,
                        'status' => MatchStatus::Ready,
                    ]);
                    MatchParticipant::create(['match_id' => $match->id, 'slot' => 1, 'tournament_entry_id' => $a->id]);
                    MatchParticipant::create(['match_id' => $match->id, 'slot' => 2, 'tournament_entry_id' => $b->id]);
                }
            }
            // Circle method: keep the first entrant fixed, rotate the rest.
            $list = array_merge([$list[0], $list[$n - 1]], array_slice($list, 1, $n - 2));
        }
    }

    private function createStage(Tournament $t, User $actor, StageType $type, string $name, int $sequence, array $settings, StageStatus $status = StageStatus::Draft): Stage
    {
        return Stage::create([
            'tournament_id' => $t->id,
            'type' => $type,
            'name' => $name,
            'sequence' => $sequence,
            'status' => $status,
            'settings' => $settings,
            'created_by_id' => $actor->id,
        ]);
    }

    // ---------------------------------------------------------------- Helpers

    /** @return Collection<int, TournamentEntry> */
    public function eligibleEntries(Tournament $tournament): Collection
    {
        return $tournament->entries()
            ->where('registration_status', RegistrationStatus::Reserved->value)
            ->where('eligibility_status', EligibilityStatus::Eligible->value)
            ->get();
    }

    /** Seeded entries first (by seed), then everyone else in a reproducible random order. */
    private function seedOrder(Collection $entries, int $drawSeed): Collection
    {
        $seeded = $entries->whereNotNull('seed')->sortBy('seed')->values();
        $rest = $entries->whereNull('seed')->sortBy('id')->values()->all();
        $rest = (new Randomizer(new Mt19937($drawSeed)))->shuffleArray($rest);

        return $seeded->concat($rest)->values();
    }

    /** Standard bracket order, e.g. 8 → [1, 8, 4, 5, 2, 7, 3, 6]: top seeds meet as late as possible. */
    private function bracketOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $sum = count($order) * 2 + 1;
            $next = [];
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $sum - $seed;
            }
            $order = $next;
        }

        return $order;
    }

    /** Stored in English; shown translated via TournamentMatch::translateLabel(). */
    private function roundLabel(int $round, int $rounds, int $size): string
    {
        return match ($rounds - $round) {
            0 => 'Final',
            1 => 'Semi-final',
            2 => 'Quarter-final',
            default => 'Round of '.intdiv($size, 2 ** ($round - 1)),
        };
    }

    private function wantsThirdPlace(Tournament $t, int $entrants): bool
    {
        return $t->third_place_method === ThirdPlaceMethod::PlayoffMatch && $entrants >= 4;
    }

    private function assertCan(User $actor, Tournament $tournament): void
    {
        if (! $actor->can('manageFixtures', $tournament)) {
            throw DomainRuleException::make('not_assigned', 'You are not assigned to this tournament for this task.', 403);
        }
    }
}
