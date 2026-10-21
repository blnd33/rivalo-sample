<?php

namespace App\Services;

use App\Enums\MatchOutcome;
use App\Enums\MatchStatus;
use App\Enums\PointsRuleSetStatus;
use App\Enums\PointsTrigger;
use App\Enums\StageType;
use App\Enums\ThirdPlaceMethod;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\MatchResult;
use App\Models\PointsLedgerEntry;
use App\Models\PointsRule;
use App\Models\PointsRuleSet;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentEntryMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranking points (Milestone 5).
 *
 * - Points come only from a PUBLISHED rule set assigned to a ranking-eligible tournament. Rule
 *   sets start as drafts (decision D3 is open), so nothing is awarded until staff publish one.
 * - The ledger is append-only and idempotent: each award has a unique key, so retries or double
 *   clicks never award twice. Corrections add reversal rows; nothing is edited or deleted.
 * - Every active roster member of an entry receives the entry's points.
 * - Once the first points are awarded, the tournament's points rules are locked.
 */
class PointsService
{
    // ================================================================ Rule sets

    public function publish(PointsRuleSet $set, User $actor, string $note): PointsRuleSet
    {
        $this->assertManage($actor);
        $this->assertNote($note, 'A note is required (who decided the points table, where it is documented).');

        return DB::transaction(function () use ($set, $actor, $note) {
            $set = PointsRuleSet::whereKey($set->id)->lockForUpdate()->with('rules')->firstOrFail();
            if (! $set->isDraft()) {
                throw DomainRuleException::make('not_draft', 'Only a draft can be published.', 409);
            }
            if ($set->rules->where('points', '>', 0)->isEmpty()) {
                throw DomainRuleException::make('no_rules', 'Add at least one rule that awards points before publishing.', 422);
            }

            // One published table per discipline and season: the previous one is retired.
            PointsRuleSet::where('discipline_id', $set->discipline_id)
                ->where(fn ($q) => $set->season_id ? $q->where('season_id', $set->season_id) : $q->whereNull('season_id'))
                ->where('status', PointsRuleSetStatus::Published->value)
                ->get()
                ->each(fn (PointsRuleSet $old) => $old->update(['status' => PointsRuleSetStatus::Retired]));

            $set->update(['status' => PointsRuleSetStatus::Published, 'published_by_id' => $actor->id, 'published_at' => now(), 'publish_note' => $note]);
            Audit::record('points.rules_published', $set, new: [
                'rules' => $set->rules->mapWithKeys(fn ($r) => [$r->trigger->value => $r->points])->all(),
            ], reason: $note, actorId: $actor->id);

            return $set;
        });
    }

    public function retire(PointsRuleSet $set, User $actor, string $reason): PointsRuleSet
    {
        $this->assertManage($actor);
        $this->assertNote($reason);
        if (! $set->isPublished()) {
            throw DomainRuleException::make('not_published', 'Only a published table can be retired.', 409);
        }
        $set->update(['status' => PointsRuleSetStatus::Retired]);
        Audit::record('points.rules_retired', $set, reason: $reason, actorId: $actor->id);

        return $set;
    }

    /** Copy a table into a new draft version (published tables are never edited). */
    public function newVersion(PointsRuleSet $set, User $actor): PointsRuleSet
    {
        $this->assertManage($actor);

        return DB::transaction(function () use ($set, $actor) {
            $version = (int) PointsRuleSet::where('discipline_id', $set->discipline_id)
                ->where(fn ($q) => $set->season_id ? $q->where('season_id', $set->season_id) : $q->whereNull('season_id'))
                ->max('version') + 1;
            $copy = PointsRuleSet::create([
                'name' => $set->name, 'discipline_id' => $set->discipline_id, 'season_id' => $set->season_id,
                'version' => $version, 'status' => PointsRuleSetStatus::Draft, 'notes' => $set->notes,
            ]);
            foreach ($set->rules as $rule) {
                $copy->rules()->create($rule->only(['trigger', 'points', 'eligible_outcomes']));
            }
            Audit::record('points.rules_new_version', $copy, new: ['from_version' => $set->version], actorId: $actor->id);

            return $copy;
        });
    }

    public function assignToTournament(Tournament $tournament, ?PointsRuleSet $set, User $actor): Tournament
    {
        if (! $actor->can('points.manage') && ! $actor->can('tournaments.manage_all')) {
            throw DomainRuleException::make('forbidden', 'Only points managers can choose a tournament\'s points rules.', 403);
        }

        return DB::transaction(function () use ($tournament, $set, $actor) {
            $tournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            if ($tournament->points_rules_locked_at) {
                throw DomainRuleException::make('points_locked', 'Points rules are locked because points were already awarded in this tournament.', 409);
            }
            if ($set) {
                $set = PointsRuleSet::findOrFail($set->id); // always judge the current state
                if (! $tournament->ranking_eligible) {
                    throw DomainRuleException::make('not_ranking_eligible', 'This tournament does not count toward rankings.', 409);
                }
                if (! $set->isPublished()) {
                    throw DomainRuleException::make('not_published', 'Only a published points table can be used.', 409);
                }
                if ($set->discipline_id !== $tournament->discipline_id || ($set->season_id && $set->season_id !== $tournament->season_id)) {
                    throw DomainRuleException::make('wrong_discipline', 'The points table must be for this tournament\'s discipline and season.', 422);
                }
            }
            $old = $tournament->points_rule_set_id;
            $tournament->forceFill(['points_rule_set_id' => $set?->id])->save();
            Audit::record('tournament.points_rules_assigned', $tournament, ['points_rule_set_id' => $old], ['points_rule_set_id' => $set?->id], actorId: $actor->id);

            return $tournament;
        });
    }

    /** The table that awards points for this tournament, or null (with no points awarded). */
    public function activeRuleSet(Tournament $tournament): ?PointsRuleSet
    {
        if (! $tournament->ranking_eligible || ! $tournament->points_rule_set_id) {
            return null;
        }
        $set = PointsRuleSet::with('rules')->find($tournament->points_rule_set_id);

        // A table retired after assignment still applies to the tournament it was chosen for.
        return $set && $set->status !== PointsRuleSetStatus::Draft ? $set : null;
    }

    // ================================================================ Awards

    public function awardForResult(MatchResult $result, ?User $actor = null): int
    {
        $match = $result->match()->with(['participants', 'tournament'])->first();
        $tournament = $match->tournament;
        $set = $this->activeRuleSet($tournament);
        if (! $set || $result->outcome === MatchOutcome::Cancelled) {
            return 0;
        }

        $written = 0;
        foreach ([1, 2] as $slot) {
            $entryId = $match->entryIdInSlot($slot);
            if (! $entryId) {
                continue;
            }
            $trigger = match (true) {
                $result->winner_slot === null => PointsTrigger::MatchDraw,
                $result->winner_slot === $slot => PointsTrigger::MatchWin,
                default => PointsTrigger::MatchLoss,
            };
            $rule = $set->rules->first(fn (PointsRule $r) => $r->trigger === $trigger);
            if (! $rule || $rule->points <= 0 || ! $rule->appliesToOutcome($result->outcome->value)) {
                continue;
            }
            $reason = __(':award · :tournament · :match', [
                'award' => $trigger->label(), 'tournament' => $tournament->name, 'match' => $match->displayLabel(),
            ]);
            foreach ($this->members($entryId) as $userId) {
                $written += $this->write($tournament, $set, $rule, $userId, $entryId, "res:{$result->id}:{$rule->id}:{$userId}", $rule->points, $trigger->value, $reason, $actor, $result->id);
            }
        }

        if ($written > 0 && ! $tournament->points_rules_locked_at) {
            $tournament->forceFill(['points_rules_locked_at' => now()])->save();
        }

        return $written;
    }

    /** Reverse every points row that came from a result (used when it is corrected). */
    public function reverseForResult(MatchResult $result, ?User $actor, string $reason): int
    {
        $rows = PointsLedgerEntry::where('match_result_id', $result->id)
            ->whereNull('reverses_id')
            ->whereDoesntHave('reversedBy')
            ->get();

        foreach ($rows as $row) {
            PointsLedgerEntry::firstOrCreate(['idempotency_key' => 'rev:'.$row->id], [
                'user_id' => $row->user_id,
                'tournament_id' => $row->tournament_id,
                'tournament_entry_id' => $row->tournament_entry_id,
                'discipline_id' => $row->discipline_id,
                'season_id' => $row->season_id,
                'points_rule_set_id' => $row->points_rule_set_id,
                'rule_set_version' => $row->rule_set_version,
                'points_rule_id' => $row->points_rule_id,
                'match_result_id' => $row->match_result_id,
                'award_type' => 'reversal',
                'points_delta' => -$row->points_delta,
                'reason' => __('Corrected: :reason', ['reason' => $reason]),
                'reverses_id' => $row->id,
                'created_by_id' => $actor?->id,
            ]);
        }

        return $rows->count();
    }

    /**
     * Confirm final placements once every match is finished, award placement and participation
     * points, and complete the tournament.
     *
     * @return array<int, int> entry_id => place
     */
    public function finalize(Tournament $tournament, User $actor): array
    {
        if (! $actor->can('manageFixtures', $tournament)) {
            throw DomainRuleException::make('not_assigned', 'You are not assigned to this tournament for this task.', 403);
        }

        return DB::transaction(function () use ($tournament, $actor) {
            $tournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            if ($tournament->placements_finalized_at) {
                throw DomainRuleException::make('already_finalized', 'Final placements are already confirmed.', 409);
            }
            if (! in_array($tournament->status, [TournamentStatus::RegistrationClosed, TournamentStatus::InProgress], true)) {
                throw DomainRuleException::make('tournament_not_running', 'Results can only be recorded while the tournament is being played.', 409);
            }
            $stage = $tournament->stages()->get()->last();
            if (! $stage) {
                throw DomainRuleException::make('no_fixtures', 'There are no fixtures yet.', 409);
            }
            if ($tournament->matches()->whereNotIn('status', [MatchStatus::Completed->value, MatchStatus::Cancelled->value, MatchStatus::Bye->value])->exists()) {
                throw DomainRuleException::make('matches_unfinished', 'Every match needs an approved result before placements are final.', 409);
            }
            if ($stage->type === StageType::Groups) {
                throw DomainRuleException::make('knockout_missing', 'Create and play the knockout stage first.', 409);
            }

            $places = $this->placements($tournament, $stage);
            foreach ($places as $entryId => $place) {
                TournamentEntry::whereKey($entryId)->update(['final_place' => $place]);
            }

            $set = $this->activeRuleSet($tournament);
            if ($set) {
                foreach ($places as $entryId => $place) {
                    $trigger = PointsTrigger::tryFrom('place_'.$place);
                    $rule = $trigger ? $set->rules->first(fn ($r) => $r->trigger === $trigger) : null;
                    if ($rule && $rule->points > 0) {
                        foreach ($this->members($entryId) as $userId) {
                            $this->write($tournament, $set, $rule, $userId, $entryId, "place:{$tournament->id}:{$entryId}:{$rule->id}:{$userId}", $rule->points, $trigger->value,
                                __(':award · :tournament', ['award' => $trigger->label(), 'tournament' => $tournament->name]), $actor);
                        }
                    }
                }
                $participation = $set->rules->first(fn ($r) => $r->trigger === PointsTrigger::Participation);
                if ($participation && $participation->points > 0) {
                    foreach ($this->playedEntries($tournament) as $entryId) {
                        foreach ($this->members($entryId) as $userId) {
                            $this->write($tournament, $set, $participation, $userId, $entryId, "part:{$tournament->id}:{$entryId}:{$participation->id}:{$userId}", $participation->points,
                                PointsTrigger::Participation->value, __(':award · :tournament', ['award' => PointsTrigger::Participation->label(), 'tournament' => $tournament->name]), $actor);
                        }
                    }
                }
                if (! $tournament->points_rules_locked_at) {
                    $tournament->forceFill(['points_rules_locked_at' => now()]);
                }
            }

            $tournament->forceFill(['placements_finalized_at' => now()])->save();
            $service = app(TournamentService::class);
            if ($tournament->status === TournamentStatus::RegistrationClosed) {
                $service->transition($tournament, $actor, TournamentStatus::InProgress);
            }
            $service->transition($tournament->fresh(), $actor, TournamentStatus::Completed);

            Audit::record('tournament.placements_finalized', $tournament, new: ['placements' => $places, 'points_rules' => $set?->id], actorId: $actor->id);
            app(AchievementService::class)->evaluateEntries(array_keys($places));

            return $places;
        });
    }

    // ================================================================ Reading

    /**
     * Leaderboard for one discipline: one season, or lifetime when $seasonId is null.
     *
     * @return list<array{rank:int, user_id:int, username:string, name:string, avatar_url:?string, points:int, wins:int, tournaments:int}>
     */
    public function leaderboard(int $disciplineId, ?int $seasonId = null, int $limit = 100): array
    {
        $base = PointsLedgerEntry::query()
            ->where('discipline_id', $disciplineId)
            ->when($seasonId, fn ($q) => $q->where('season_id', $seasonId));

        $totals = (clone $base)
            ->selectRaw('user_id, SUM(points_delta) as points, COUNT(DISTINCT tournament_id) as tournaments')
            ->groupBy('user_id')
            ->havingRaw('SUM(points_delta) > 0')
            ->get()
            ->keyBy('user_id');
        if ($totals->isEmpty()) {
            return [];
        }

        $wins = (clone $base)->where('award_type', PointsTrigger::MatchWin->value)->whereDoesntHave('reversedBy')
            ->selectRaw('user_id, COUNT(*) as wins')->groupBy('user_id')->pluck('wins', 'user_id');
        $users = User::whereIn('id', $totals->keys())->get()->keyBy('id');

        $rows = $totals->map(fn ($t, $userId) => [
            'user_id' => (int) $userId,
            'points' => (int) $t->points,
            'wins' => (int) ($wins[$userId] ?? 0),
            'tournaments' => (int) $t->tournaments,
        ])->filter(fn ($r) => isset($users[$r['user_id']]) && $users[$r['user_id']]->isActive())
            ->sort(fn ($a, $b) => [$b['points'], $b['wins'], $users[$a['user_id']]->username] <=> [$a['points'], $a['wins'], $users[$b['user_id']]->username])
            ->values()
            ->take($limit);

        // Standard competition ranking: equal points and wins share a rank (1, 2, 2, 4).
        $out = [];
        foreach ($rows as $i => $r) {
            $prev = $out[$i - 1] ?? null;
            $rank = ($prev && $prev['points'] === $r['points'] && $prev['wins'] === $r['wins']) ? $prev['rank'] : $i + 1;
            $u = $users[$r['user_id']];
            $out[] = ['rank' => $rank, 'user_id' => $u->id, 'username' => $u->username, 'name' => $u->publicName(), 'avatar_url' => $u->avatarUrl()] + $r;
        }

        return $out;
    }

    public function totalFor(User $user): int
    {
        return (int) PointsLedgerEntry::where('user_id', $user->id)->sum('points_delta');
    }

    // ================================================================ Internals

    /** @return array<int, int> entry_id => place */
    private function placements(Tournament $tournament, $stage): array
    {
        if ($stage->type->isTable()) {
            $places = [];
            foreach (app(StandingsService::class)->table($stage) as $i => $row) {
                $places[$row['entry_id']] = $i + 1;
            }

            return $places;
        }

        $matches = TournamentMatch::with('participants')->where('stage_id', $stage->id)->get();
        $lastRound = $matches->max('round');
        $final = $matches->first(fn ($m) => $m->round === $lastRound && $m->position === 1);
        $third = $matches->first(fn ($m) => $m->round === $lastRound && $m->position === 2);

        $places = [];
        $finalists = $final->participants->pluck('tournament_entry_id')->filter();
        if ($final->winner_entry_id) {
            $places[$final->winner_entry_id] = 1;
            foreach ($finalists as $id) {
                $places[$id] ??= 2;
            }
        }
        if ($third && $third->winner_entry_id) {
            $places[$third->winner_entry_id] = 3;
            foreach ($third->participants->pluck('tournament_entry_id')->filter() as $id) {
                $places[$id] ??= 4;
            }
        } elseif ($tournament->third_place_method === ThirdPlaceMethod::SharedThird && $lastRound >= 2) {
            foreach ($matches->where('round', $lastRound - 1) as $semi) {
                foreach ($semi->participants->pluck('tournament_entry_id')->filter() as $id) {
                    if ($id !== $semi->winner_entry_id) {
                        $places[$id] ??= 3;
                    }
                }
            }
        }

        return $places;
    }

    /** Entries that actually played at least one match (not only byes or cancellations). */
    private function playedEntries(Tournament $tournament): Collection
    {
        return TournamentMatch::where('tournament_id', $tournament->id)
            ->where('status', MatchStatus::Completed->value)
            ->with('participants')
            ->get()
            ->flatMap(fn ($m) => $m->participants->pluck('tournament_entry_id'))
            ->filter()
            ->unique()
            ->values();
    }

    /** @return Collection<int, int> */
    private function members(int $entryId): Collection
    {
        return TournamentEntryMember::where('tournament_entry_id', $entryId)->where('is_active', true)->pluck('user_id');
    }

    private function write(Tournament $t, PointsRuleSet $set, PointsRule $rule, int $userId, int $entryId, string $key, int $points, string $type, string $reason, ?User $actor, ?int $resultId = null): int
    {
        $row = PointsLedgerEntry::firstOrCreate(['idempotency_key' => $key], [
            'user_id' => $userId,
            'tournament_id' => $t->id,
            'tournament_entry_id' => $entryId,
            'discipline_id' => $t->discipline_id,
            'season_id' => $t->season_id,
            'points_rule_set_id' => $set->id,
            'rule_set_version' => $set->version,
            'points_rule_id' => $rule->id,
            'match_result_id' => $resultId,
            'award_type' => $type,
            'points_delta' => $points,
            'reason' => mb_substr($reason, 0, 255),
            'created_by_id' => $actor?->id,
        ]);

        return $row->wasRecentlyCreated ? 1 : 0;
    }

    private function assertManage(User $actor): void
    {
        if (! $actor->can('points.manage')) {
            throw DomainRuleException::make('forbidden', 'Only points managers can change points tables.', 403);
        }
    }

    private function assertNote(?string $note, string $message = 'A reason is required.'): void
    {
        if (blank($note)) {
            throw DomainRuleException::make('reason_required', $message, 422);
        }
    }
}
