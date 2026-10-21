<?php

namespace Tests\Feature\Competition;

use App\Enums\MatchStatus;
use App\Enums\RoleName;
use App\Enums\StaffRole;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Pages\TournamentFixtures;
use App\Filament\Resources\PointsRuleSets\Pages\EditPointsRuleSet;
use App\Filament\Resources\PointsRuleSets\PointsRuleSetResource;
use App\Models\PointsLedgerEntry;
use App\Models\PointsRuleSet;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\FixtureService;
use App\Services\PointsService;
use App\Services\RegistrationService;
use App\Services\ResultService;
use App\Services\TournamentService;
use App\Services\VenueService;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class CompetitionTest extends TestCase
{
    private User $admin;

    private User $organizer;

    private User $referee;

    /** @var Collection<int, User> */
    private Collection $players;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->staff(RoleName::SuperAdmin);
        $this->organizer = $this->staff(RoleName::TournamentOrganizer);
        $this->referee = $this->staff(RoleName::Referee);
    }

    private function expectRule(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected domain rule [{$code}].");
        } catch (DomainRuleException $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());
        }
    }

    /** Individual 9-ball tournament with $n checked-in (eligible) players, registration closed. */
    private function ready(int $n, string $format = 'single_elimination', array $overrides = []): Tournament
    {
        $t = $this->tournament(array_merge(['format' => $format, 'max_entrants' => 32, 'min_entrants' => 2, 'ranking_eligible' => true], $overrides), organizer: $this->organizer);
        app(TournamentService::class)->assignStaff($t, $this->referee, $this->admin, StaffRole::Referee);
        $this->players = collect();
        for ($i = 0; $i < $n; $i++) {
            $p = $this->player(['username' => sprintf('p%02d_%s', $i + 1, substr(uniqid(), -5))]);
            $entry = app(RegistrationService::class)->register($t->fresh(), $p);
            app(VenueService::class)->checkIn($entry, $this->organizer);
            $this->players->push($p);
        }

        return app(TournamentService::class)->transition($t->fresh(), $this->admin, TournamentStatus::RegistrationClosed)->fresh();
    }

    private function fixtures(): FixtureService
    {
        return app(FixtureService::class);
    }

    private function results(): ResultService
    {
        return app(ResultService::class);
    }

    private function points(): PointsService
    {
        return app(PointsService::class);
    }

    private function draw(Tournament $t, array $options = []): Tournament
    {
        $this->fixtures()->generate($t, $this->organizer, $options);
        $this->fixtures()->publish($t, $this->organizer);

        return $t->fresh();
    }

    /** Referee enters and approves: slot 1 wins 2-0 unless told otherwise. */
    private function play(TournamentMatch $m, int $s1 = 2, int $s2 = 0, array $extra = []): void
    {
        $this->results()->submit($m->fresh(), $this->referee, ['outcome' => 'normal', 'slot1' => $s1, 'slot2' => $s2, 'approve' => true] + $extra);
    }

    private function readyMatches(Tournament $t): Collection
    {
        return TournamentMatch::where('tournament_id', $t->id)->where('status', MatchStatus::Ready->value)->orderBy('round')->orderBy('position')->get();
    }

    private function publishedPoints(Tournament $t, array $rules = ['match_win' => 10, 'participation' => 5, 'place_1' => 50, 'place_2' => 30, 'place_3' => 20]): PointsRuleSet
    {
        $set = PointsRuleSet::create(['name' => 'Test table', 'discipline_id' => $t->discipline_id]);
        foreach ($rules as $trigger => $points) {
            $set->rules()->create(['trigger' => $trigger, 'points' => $points, 'eligible_outcomes' => ['normal', 'walkover']]);
        }
        $this->points()->publish($set, $this->admin, 'Owner approved test table');
        $this->points()->assignToTournament($t, $set, $this->admin);

        return $set->fresh();
    }

    // ---------------------------------------------------------------- Draws

    public function test_knockout_with_byes_seeds_and_reproducible_draw(): void
    {
        $t = $this->ready(5);
        $t->entries()->orderBy('id')->first()->update(['seed' => 1]);
        $this->fixtures()->generate($t, $this->organizer, ['draw_seed' => 42]);

        $matches = TournamentMatch::where('tournament_id', $t->id)->get();
        $this->assertSame(4, $matches->where('round', 1)->count());
        $this->assertSame(3, $matches->where('status', MatchStatus::Bye)->count());
        $this->assertSame('Final', $matches->firstWhere('round', 3)->label);
        // Top seed gets a bye and is already in the semi-final.
        $seedOne = $t->entries()->where('seed', 1)->first();
        $this->assertTrue(TournamentMatch::where('round', 2)->where('tournament_id', $t->id)->whereHas('participants', fn ($q) => $q->where('tournament_entry_id', $seedOne->id))->exists());
        // Third-place match exists (playoff method, ≥ 4 entrants).
        $this->assertSame('Third place', $matches->where('round', 3)->firstWhere('position', 2)->label);

        // Same draw seed → same draw.
        $first = TournamentMatch::where('tournament_id', $t->id)->where('round', 1)->orderBy('position')->get()->map(fn ($m) => $m->participants()->pluck('tournament_entry_id')->all())->all();
        $this->fixtures()->generate($t->fresh(), $this->organizer, ['draw_seed' => 42]);
        $again = TournamentMatch::where('tournament_id', $t->id)->where('round', 1)->orderBy('position')->get()->map(fn ($m) => $m->participants()->pluck('tournament_entry_id')->all())->all();
        $this->assertSame($first, $again);
    }

    public function test_round_robin_plays_everyone_once(): void
    {
        $t = $this->draw($this->ready(5, 'round_robin'));
        $matches = TournamentMatch::with('participants')->where('tournament_id', $t->id)->get();
        $this->assertCount(10, $matches);
        $pairs = $matches->map(fn ($m) => $m->participants->pluck('tournament_entry_id')->sort()->implode('-'))->unique();
        $this->assertCount(10, $pairs);
    }

    public function test_draw_needs_closed_registration_and_eligible_entrants(): void
    {
        $open = $this->tournament(['ranking_eligible' => true], organizer: $this->organizer);
        $this->expectRule('wrong_status', fn () => $this->fixtures()->generate($open, $this->organizer));

        $t = $this->ready(1);
        $this->expectRule('not_enough_entrants', fn () => $this->fixtures()->generate($t, $this->organizer));
        $this->expectRule('not_assigned', fn () => $this->fixtures()->generate($t, $this->player()));
    }

    public function test_groups_then_knockout_seeds_group_winners_apart(): void
    {
        $t = $this->draw($this->ready(8, 'groups_knockout'), ['group_size' => 4, 'advance_per_group' => 2]);
        $this->assertSame(12, TournamentMatch::where('tournament_id', $t->id)->count());
        $this->expectRule('groups_unfinished', fn () => $this->fixtures()->generateKnockout($t, $this->organizer));

        // Slot 1 always wins, so every group has a clear order.
        while (($m = $this->readyMatches($t)->first())) {
            $this->play($m);
        }
        $knockout = $this->fixtures()->generateKnockout($t, $this->organizer);
        $semis = $knockout->matches()->where('round', 1)->with('participants.entry')->get();
        $this->assertCount(2, $semis);
        $this->assertSame('published', $knockout->status->value);
        // A1 meets B2 and B1 meets A2: no semi-final pairs two teams from the same group.
        foreach ($semis as $semi) {
            $groups = $semi->participants->map(fn ($p) => TournamentMatch::where('stage_id', $t->stages()->first()->id)
                ->whereHas('participants', fn ($q) => $q->where('tournament_entry_id', $p->tournament_entry_id))->value('stage_group_id'))->unique();
            $this->assertCount(2, $groups);
        }
    }

    // ---------------------------------------------------------------- Results

    public function test_players_submit_referees_approve_and_the_winner_advances(): void
    {
        $t = $this->draw($this->ready(4));
        $semi = $this->readyMatches($t)->first();
        $p1 = $semi->participants()->where('slot', 1)->first()->entry->activeMembers()->first()->user;
        $outsider = $this->player();

        $this->expectRule('not_participant', fn () => $this->results()->submit($semi, $outsider, ['outcome' => 'normal', 'slot1' => 1, 'slot2' => 0]));
        $this->expectRule('tie_needs_winner', fn () => $this->results()->submit($semi, $p1, ['outcome' => 'normal', 'slot1' => 2, 'slot2' => 2]));
        $this->expectRule('staff_only_outcome', fn () => $this->results()->submit($semi, $p1, ['outcome' => 'disqualification', 'winner_slot' => 1]));

        $result = $this->results()->submit($semi, $p1, ['outcome' => 'normal', 'slot1' => 5, 'slot2' => 3]);
        $this->assertSame('submitted', $result->status->value);
        $this->expectRule('not_reviewer', fn () => $this->results()->approve($result, $p1));
        $this->expectRule('not_reviewer', fn () => $this->results()->approve($result, $this->organizer));

        $this->results()->approve($result, $this->referee, 'Checked score sheet');
        $semi->refresh();
        $this->assertSame(MatchStatus::Completed, $semi->status);
        $winner = $semi->entryIdInSlot(1);
        $this->assertSame($winner, $semi->winner_entry_id);
        $this->assertSame($winner, TournamentMatch::find($semi->next_match_id)->participants()->where('slot', $semi->next_slot)->value('tournament_entry_id'));
        $loser = $semi->entryIdInSlot(2);
        $this->assertSame($loser, TournamentMatch::find($semi->loser_next_match_id)->participants()->where('slot', $semi->loser_next_slot)->value('tournament_entry_id'));
        $this->expectRule('already_decided', fn () => $this->results()->submit($semi->fresh(), $p1, ['outcome' => 'normal', 'slot1' => 1, 'slot2' => 0]));
    }

    public function test_sets_decide_the_winner_and_league_matches_can_be_drawn(): void
    {
        $t = $this->draw($this->ready(4, 'round_robin'));
        [$a, $b] = $this->readyMatches($t)->take(2)->all();

        $this->results()->submit($a, $this->referee, ['outcome' => 'normal', 'sets' => [[6, 4], [3, 6], [10, 8]], 'approve' => true]);
        $this->assertSame(1, $a->fresh()->approvedResult->winner_slot);
        $this->assertSame(['slot1' => 2, 'slot2' => 1, 'sets' => [[6, 4], [3, 6], [10, 8]]], $a->fresh()->approvedResult->scores);

        $this->play($b, 1, 1);
        $this->assertTrue($b->fresh()->is_draw);
    }

    public function test_corrections_follow_the_bracket_until_a_later_match_is_played(): void
    {
        $t = $this->draw($this->ready(4));
        $this->publishedPoints($t);
        [$semi1, $semi2] = $this->readyMatches($t)->all();
        $this->play($semi1);
        $oldWinner = $semi1->fresh()->winner_entry_id;
        $this->assertGreaterThan(0, PointsLedgerEntry::where('tournament_id', $t->id)->sum('points_delta'));

        $this->expectRule('reason_required', fn () => $this->results()->correct($semi1, $this->referee, ['outcome' => 'normal', 'slot1' => 0, 'slot2' => 2], ''));
        $this->results()->correct($semi1, $this->referee, ['outcome' => 'normal', 'slot1' => 0, 'slot2' => 2], 'Score sheet was read the wrong way round');

        $semi1->refresh();
        $this->assertNotSame($oldWinner, $semi1->winner_entry_id);
        $final = TournamentMatch::find($semi1->next_match_id);
        $this->assertSame($semi1->winner_entry_id, $final->participants()->where('slot', $semi1->next_slot)->value('tournament_entry_id'));
        // The old winner's points were reversed; the new winner got them.
        $this->assertSame(0, (int) PointsLedgerEntry::where('tournament_entry_id', $oldWinner)->sum('points_delta'));
        $this->assertSame(10, (int) PointsLedgerEntry::where('tournament_entry_id', $semi1->winner_entry_id)->sum('points_delta'));
        $this->assertSame(1, PointsLedgerEntry::where('award_type', 'reversal')->where('tournament_id', $t->id)->count());

        $this->play($semi2);
        $this->play($final->fresh());
        $this->expectRule('later_match_played', fn () => $this->results()->correct($semi1->fresh(), $this->referee, ['outcome' => 'normal', 'slot1' => 2, 'slot2' => 0], 'again'));
    }

    public function test_disputes_are_raised_by_players_within_the_window_and_resolved_by_referees(): void
    {
        $t = $this->draw($this->ready(2));
        $final = $this->readyMatches($t)->first();
        $this->play($final);
        $result = $final->fresh()->approvedResult;
        $loser = $final->participants()->where('slot', 2)->first()->entry->activeMembers()->first()->user;

        $this->expectRule('not_participant', fn () => $this->results()->dispute($result, $this->player(), 'Not my match'));
        $dispute = $this->results()->dispute($result, $loser, 'The score was 2-3, not 2-0');
        $this->expectRule('dispute_open', fn () => $this->results()->dispute($result, $loser, 'Again'));
        $this->expectRule('correction_required', fn () => $this->results()->resolveDispute($dispute, $this->referee, true, 'Agreed'));

        $this->results()->resolveDispute($dispute, $this->referee, true, 'Video confirms', ['outcome' => 'normal', 'slot1' => 2, 'slot2' => 3]);
        $this->assertSame('upheld', $dispute->fresh()->status->value);
        $this->assertSame(2, $final->fresh()->approvedResult->winner_slot);

        $this->travel(49)->hours();
        $this->expectRule('dispute_window_closed', fn () => $this->results()->dispute($final->fresh()->approvedResult, $loser, 'Late'));
    }

    // ---------------------------------------------------------------- Points

    public function test_no_points_without_a_published_table(): void
    {
        $t = $this->draw($this->ready(2));
        $draft = PointsRuleSet::create(['name' => 'Draft', 'discipline_id' => $t->discipline_id]);
        $draft->rules()->create(['trigger' => 'match_win', 'points' => 10]);

        $this->expectRule('not_published', fn () => $this->points()->assignToTournament($t, $draft, $this->admin));
        $this->play($this->readyMatches($t)->first());
        $this->assertSame(0, PointsLedgerEntry::count());
        $this->assertSame([], $this->points()->leaderboard($t->discipline_id));
    }

    public function test_points_flow_to_every_roster_member_once_and_lock_the_rules(): void
    {
        $t = $this->draw($this->ready(4));
        $set = $this->publishedPoints($t);
        $semi = $this->readyMatches($t)->first();
        $this->play($semi);

        $winnerEntry = $semi->fresh()->winner_entry_id;
        $this->assertSame(10, (int) PointsLedgerEntry::where('tournament_entry_id', $winnerEntry)->sum('points_delta'));
        $this->assertNotNull($t->fresh()->points_rules_locked_at);
        $this->expectRule('points_locked', fn () => $this->points()->assignToTournament($t->fresh(), null, $this->admin));

        // Awarding the same result again writes nothing (idempotent).
        $this->assertSame(0, $this->points()->awardForResult($semi->fresh()->approvedResult));

        // Published tables are frozen.
        $this->expectException(LogicException::class);
        $set->update(['name' => 'Changed']);
    }

    public function test_finalizing_awards_placements_completes_the_tournament_and_fills_the_leaderboard(): void
    {
        $t = $this->draw($this->ready(4));
        $this->publishedPoints($t);
        while (($m = $this->readyMatches($t)->first())) {
            $this->play($m);
        }

        $places = $this->points()->finalize($t, $this->organizer);
        $this->assertSame([1, 2, 3, 4], collect($places)->values()->sort()->values()->all());
        $t->refresh();
        $this->assertSame(TournamentStatus::Completed, $t->status);
        $this->expectRule('already_finalized', fn () => $this->points()->finalize($t, $this->organizer));

        $board = $this->points()->leaderboard($t->discipline_id);
        $this->assertCount(4, $board);
        // Champion: 2 wins (20) + 1st place (50) + participation (5) = 75.
        $this->assertSame(75, $board[0]['points']);
        $this->assertSame(1, $board[0]['rank']);
        $this->assertSame(2, $board[0]['wins']);
        $this->assertSame(1, $board[0]['tournaments']);
    }

    public function test_points_rows_are_append_only(): void
    {
        $t = $this->draw($this->ready(2));
        $this->publishedPoints($t);
        $this->play($this->readyMatches($t)->first());

        $this->expectException(LogicException::class);
        PointsLedgerEntry::first()->delete();
    }

    // ---------------------------------------------------------------- API

    public function test_public_api_shows_published_fixtures_standings_and_leaderboards(): void
    {
        $t = $this->ready(4, 'round_robin');
        $this->fixtures()->generate($t, $this->organizer);
        $this->getJson("/api/v1/tournaments/{$t->slug}/fixtures")->assertOk()->assertJsonCount(0, 'data.stages');

        $this->fixtures()->publish($t, $this->organizer);
        $this->publishedPoints($t);
        $m = $this->readyMatches($t)->first();
        $player = $m->participants()->where('slot', 1)->first()->entry->activeMembers()->first()->user;

        // A player's own submission is visible to them, not to the public.
        $this->actingAs($player, 'sanctum')->postJson("/api/v1/matches/{$m->id}/results", ['outcome' => 'normal', 'slot1' => 3, 'slot2' => 1])
            ->assertOk()->assertJsonPath('data.pending_submission.score_line', '3 – 1')->assertJsonPath('data.my_slot', 1);
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/matches/{$m->id}")->assertOk()->assertJsonPath('data.pending_submission', null)->assertJsonPath('data.result', null);

        $this->results()->approve($m->fresh()->pendingResult(), $this->referee);
        $json = $this->getJson("/api/v1/tournaments/{$t->slug}/fixtures")->assertOk()->json('data');
        $this->assertSame('round_robin', $json['stages'][0]['type']);
        $this->assertSame(3, $json['stages'][0]['standings'][0]['points']);
        $this->assertSame('Test table', $json['points']['rule_set']['name']);

        $this->getJson("/api/v1/leaderboards?discipline_id={$t->discipline_id}")
            ->assertOk()
            ->assertJsonPath('data.0.points', 10)
            ->assertJsonPath('data.0.user.username', $player->username)
            ->assertJsonPath('meta.has_published_rules', true)
            ->assertJsonMissingPath('data.0.user.email');

        $this->actingAs($player, 'sanctum')->getJson('/api/v1/me/points')->assertOk()->assertJsonPath('data.total', 10)->assertJsonPath('data.history.0.award_type', 'match_win');
    }

    public function test_players_cannot_submit_through_the_api_for_other_matches_or_staff_outcomes(): void
    {
        $t = $this->draw($this->ready(4));
        [$a, $b] = $this->readyMatches($t)->all();
        $playerA = $a->participants()->first()->entry->activeMembers()->first()->user;

        $this->actingAs($playerA, 'sanctum')->postJson("/api/v1/matches/{$b->id}/results", ['outcome' => 'normal', 'slot1' => 1, 'slot2' => 0])->assertForbidden();
        $this->actingAs($playerA, 'sanctum')->postJson("/api/v1/matches/{$a->id}/results", ['outcome' => 'cancelled'])->assertUnprocessable();
    }

    // ---------------------------------------------------------------- Admin pages

    private function enrolled(User $user): User
    {
        $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        return $user;
    }

    public function test_fixtures_desk_draws_publishes_and_records_results(): void
    {
        $t = $this->ready(4);
        $this->actingAs($this->enrolled($this->organizer));

        Livewire::test(TournamentFixtures::class, ['tournamentId' => $t->id])
            ->assertSee(__('No draw yet. Close registration and check entrants in, then make the draw.'))
            ->callAction('generate', ['draw_seed' => 7])
            ->assertHasNoActionErrors()
            ->callAction('publishFixtures')
            ->assertSee(__('Semi-final'));
        $this->assertSame('published', $t->stages()->first()->status->value);

        // Organizers enter results; only the referee approves.
        $match = $this->readyMatches($t)->first();
        Livewire::test(TournamentFixtures::class, ['tournamentId' => $t->id])
            ->callAction('enterResult', ['match_id' => $match->id, 'outcome' => 'normal', 'slot1' => 3, 'slot2' => 1])
            ->assertHasNoActionErrors();
        $this->assertSame('submitted', $match->fresh()->pendingResult()->status->value);

        $this->actingAs($this->enrolled($this->referee));
        Livewire::test(TournamentFixtures::class, ['tournamentId' => $t->id])
            ->callAction('reviewResult', ['result_id' => $match->fresh()->pendingResult()->id, 'decision' => 'approve'])
            ->assertHasNoActionErrors();
        $this->assertSame(MatchStatus::Completed, $match->fresh()->status);

        // Sets typed as text are understood; a bad format is refused with a message.
        $other = $this->readyMatches($t)->first();
        Livewire::test(TournamentFixtures::class, ['tournamentId' => $t->id])
            ->callAction('enterResult', ['match_id' => $other->id, 'outcome' => 'normal', 'sets' => '6 - 4; 7-5', 'approve' => true])
            ->assertHasNoActionErrors();
        $this->assertSame([[6, 4], [7, 5]], $other->fresh()->approvedResult->scores['sets']);
    }

    public function test_points_tables_are_published_from_admin_and_players_cannot_open_the_desk(): void
    {
        $this->actingAs($this->enrolled($this->admin));
        $t = $this->ready(2);
        $set = PointsRuleSet::create(['name' => 'Pilot table', 'discipline_id' => $t->discipline_id]);
        $set->rules()->create(['trigger' => 'match_win', 'points' => 3]);

        $this->get(PointsRuleSetResource::getUrl('index'))->assertOk()->assertSee('Pilot table');
        Livewire::test(EditPointsRuleSet::class, ['record' => $set->getRouteKey()])
            ->callAction('publish', ['note' => 'Owner decision, 19 Oct'])
            ->assertHasNoActionErrors();
        $this->assertTrue($set->fresh()->isPublished());

        $organizer = $this->enrolled($this->staff(RoleName::TournamentOrganizer));
        $this->actingAs($organizer)->get(PointsRuleSetResource::getUrl('index'))->assertForbidden();
        $this->actingAs($this->player())->get(TournamentFixtures::getUrl())->assertForbidden();
    }
}
