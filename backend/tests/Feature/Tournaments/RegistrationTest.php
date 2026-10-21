<?php

namespace Tests\Feature\Tournaments;

use App\Enums\RegistrationStatus;
use App\Enums\TournamentStatus;
use App\Models\TournamentEntry;
use App\Services\RegistrationService;
use App\Services\TeamService;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_individual_registration_reserves_a_place(): void
    {
        $tournament = $this->tournament();
        $player = $this->player();

        $this->actingAs($player, 'sanctum')
            ->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertCreated()
            ->assertJsonPath('data.registration_status', 'reserved')
            ->assertJsonPath('data.payment_status', 'not_required')
            ->assertJsonPath('data.check_in_status', 'not_checked_in')
            ->assertJsonPath('data.eligibility_status', 'pending');
    }

    public function test_duplicate_registration_is_refused(): void
    {
        $tournament = $this->tournament();
        $player = $this->player();

        $this->actingAs($player, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")->assertCreated();
        $this->actingAs($player, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertStatus(409)->assertJsonPath('code', 'already_registered');

        $this->assertSame(1, TournamentEntry::where('tournament_id', $tournament->id)->count());
    }

    public function test_capacity_overflow_goes_to_waitlist_and_cancellation_promotes_next(): void
    {
        $tournament = $this->tournament(['max_entrants' => 2]);
        $service = app(RegistrationService::class);
        [$a, $b, $c, $d] = [$this->player(), $this->player(), $this->player(), $this->player()];

        $entryA = $service->register($tournament, $a);
        $service->register($tournament, $b);
        $entryC = $service->register($tournament, $c);
        $entryD = $service->register($tournament, $d);

        $this->assertSame(RegistrationStatus::Waitlisted, $entryC->registration_status);
        $this->assertSame(1, $entryC->waitlist_position);
        $this->assertSame(2, $entryD->waitlist_position);
        $this->assertSame(2, $tournament->reservedEntries()->count(), 'Never more reserved entries than max_entrants.');

        $this->actingAs($a, 'sanctum')->postJson("/api/v1/registrations/{$entryA->id}/cancel", ['reason' => 'Injured'])
            ->assertOk()->assertJsonPath('data.registration_status', 'cancelled');

        $this->assertSame(RegistrationStatus::Reserved, $entryC->fresh()->registration_status);
        $this->assertNull($entryC->fresh()->waitlist_position);
        $this->assertSame(RegistrationStatus::Waitlisted, $entryD->fresh()->registration_status);
        $this->assertSame(2, $tournament->reservedEntries()->count());
    }

    public function test_cancelled_player_may_register_again_while_open(): void
    {
        $tournament = $this->tournament();
        $player = $this->player();
        $service = app(RegistrationService::class);

        $entry = $service->register($tournament, $player);
        $service->cancel($entry, $player);

        $again = $service->register($tournament, $player);
        $this->assertSame(RegistrationStatus::Reserved, $again->registration_status);
    }

    public function test_registration_is_refused_when_not_open(): void
    {
        $published = $this->tournament(status: TournamentStatus::Published);
        $closed = $this->tournament(status: TournamentStatus::RegistrationClosed);
        $player = $this->player();

        $this->actingAs($player, 'sanctum')->postJson("/api/v1/tournaments/{$published->slug}/registrations")
            ->assertStatus(409)->assertJsonPath('code', 'registration_closed');
        $this->actingAs($player, 'sanctum')->postJson("/api/v1/tournaments/{$closed->slug}/registrations")
            ->assertStatus(409)->assertJsonPath('code', 'registration_closed');
    }

    public function test_registration_window_dates_are_enforced(): void
    {
        $tournament = $this->tournament([
            'registration_opens_at' => now()->addDay(),
            'registration_closes_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->player(), 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertStatus(409)->assertJsonPath('code', 'registration_closed');
    }

    public function test_unverified_players_cannot_register(): void
    {
        $tournament = $this->tournament();

        $this->actingAs($this->player(['email_verified_at' => null]), 'sanctum')
            ->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertForbidden();
    }

    public function test_pair_registration_requires_captain_and_accepted_partner(): void
    {
        $tournament = $this->tournament(['discipline' => 'doubles', 'registration_type' => 'pair']);
        $team = $this->teamWithMembers('padel', 1);
        $captain = $team->captain;
        $partner = $team->activeMemberships()->where('user_id', '!=', $captain->id)->first()->user;
        $outsider = $this->player();

        // Outsider who never accepted membership cannot be put on the roster.
        $this->actingAs($captain, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations", [
            'team_id' => $team->id, 'player_ids' => [$captain->id, $outsider->id],
        ])->assertUnprocessable()->assertJsonPath('code', 'not_team_member');

        // The partner cannot register on the captain's behalf.
        $this->actingAs($partner, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations", [
            'team_id' => $team->id, 'player_ids' => [$captain->id, $partner->id],
        ])->assertForbidden()->assertJsonPath('code', 'not_captain');

        $this->actingAs($captain, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations", [
            'team_id' => $team->id, 'player_ids' => [$captain->id, $partner->id],
        ])->assertCreated()->assertJsonCount(2, 'data.members');
    }

    public function test_pending_invitees_cannot_be_rostered(): void
    {
        $tournament = $this->tournament(['discipline' => 'doubles', 'registration_type' => 'pair']);
        $team = $this->teamWithMembers('padel', 0);
        $pending = $this->player();
        app(TeamService::class)->invite($team, $team->captain, $pending);

        $this->actingAs($team->captain, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations", [
            'team_id' => $team->id, 'player_ids' => [$team->captain_id, $pending->id],
        ])->assertUnprocessable()->assertJsonPath('code', 'not_team_member');
    }

    public function test_roster_size_limits_are_enforced(): void
    {
        $tournament = $this->tournament(['discipline' => 'doubles', 'registration_type' => 'pair']);
        $team = $this->teamWithMembers('padel', 2);
        $ids = $team->activeMemberships()->pluck('user_id')->all();

        $this->actingAs($team->captain, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations", [
            'team_id' => $team->id, 'player_ids' => $ids, // three players for a pair
        ])->assertUnprocessable()->assertJsonPath('code', 'roster_size');
    }

    public function test_a_player_cannot_appear_on_two_rosters_in_one_tournament(): void
    {
        $tournament = $this->tournament(['discipline' => 'doubles', 'registration_type' => 'pair']);
        $teams = app(TeamService::class);

        $shared = $this->player();
        $teamA = $this->teamWithMembers('padel', 0);
        $teamB = $this->teamWithMembers('padel', 0);
        foreach ([$teamA, $teamB] as $team) {
            $teams->acceptInvitation($teams->invite($team, $team->captain, $shared), $shared);
        }

        $this->actingAs($teamA->captain, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations", [
            'team_id' => $teamA->id, 'player_ids' => [$teamA->captain_id, $shared->id],
        ])->assertCreated();

        $this->actingAs($teamB->captain, 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations", [
            'team_id' => $teamB->id, 'player_ids' => [$teamB->captain_id, $shared->id],
        ])->assertStatus(409)->assertJsonPath('code', 'conflicting_registration');
    }

    public function test_roster_snapshot_is_unchanged_by_later_team_changes(): void
    {
        $tournament = $this->tournament(['discipline' => 'doubles', 'registration_type' => 'pair']);
        $team = $this->teamWithMembers('padel', 1);
        $partner = $team->activeMemberships()->where('user_id', '!=', $team->captain_id)->first()->user;
        $originalName = $partner->display_name;

        $entry = app(RegistrationService::class)->register($tournament, $team->captain, $team, [$team->captain_id, $partner->id]);

        // Later: partner renames themselves and leaves the team; the team is renamed.
        $partner->update(['display_name' => 'Totally New Name']);
        app(TeamService::class)->leave($team, $partner);
        app(TeamService::class)->rename($team, $team->captain, 'Renamed Pair');

        $members = $entry->fresh()->activeMembers;
        $this->assertCount(2, $members);
        $this->assertTrue($members->contains('user_id', $partner->id));
        $this->assertSame($originalName, $members->firstWhere('user_id', $partner->id)->display_name_snapshot);
        $this->assertNotSame('Renamed Pair', $entry->fresh()->display_name);
    }

    public function test_paid_entry_tournaments_cannot_be_registered_for_while_compliance_flag_is_off(): void
    {
        // Created while the flag was on, then the flag was turned off again.
        $this->enablePaidEntry(true);
        $tournament = $this->tournament(['entry_fee_minor' => 25000, 'funding_model' => 'player_funded', 'prize_rules' => 'Published prize rules.']);
        $this->enablePaidEntry(false);

        $this->actingAs($this->player(), 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertStatus(409)->assertJsonPath('code', 'paid_entry_disabled');
    }

    public function test_paid_entry_registration_starts_unpaid_when_flag_is_on(): void
    {
        $this->enablePaidEntry(true);
        $tournament = $this->tournament(['entry_fee_minor' => 25000, 'funding_model' => 'player_funded', 'prize_rules' => 'Published prize rules.']);

        $this->actingAs($this->player(), 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertCreated()->assertJsonPath('data.payment_status', 'unpaid');

        $this->getJson("/api/v1/tournaments/{$tournament->slug}")
            ->assertJsonPath('data.entry_fee.amount_minor', 25000)
            ->assertJsonPath('data.entry_fee.formatted', '25,000 IQD')
            ->assertJsonPath('data.entry_fee.payment_method', 'in_person');
    }

    public function test_free_tournaments_work_with_the_compliance_flag_off(): void
    {
        $this->enablePaidEntry(false);
        $tournament = $this->tournament(['entry_fee_minor' => 0]);

        $this->actingAs($this->player(), 'sanctum')->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertCreated();
    }

    public function test_players_cannot_cancel_after_registration_closes(): void
    {
        $tournament = $this->tournament();
        $player = $this->player();
        $entry = app(RegistrationService::class)->register($tournament, $player);

        $this->travelTo($tournament->registration_closes_at->copy()->addMinute());

        $this->actingAs($player, 'sanctum')->postJson("/api/v1/registrations/{$entry->id}/cancel")
            ->assertStatus(409)->assertJsonPath('code', 'cancellation_closed');
    }

    public function test_other_players_cannot_cancel_my_entry(): void
    {
        $tournament = $this->tournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());

        $this->actingAs($this->player(), 'sanctum')->postJson("/api/v1/registrations/{$entry->id}/cancel")
            ->assertForbidden();
    }
}
