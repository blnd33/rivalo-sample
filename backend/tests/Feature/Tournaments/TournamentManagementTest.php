<?php

namespace Tests\Feature\Tournaments;

use App\Enums\RoleName;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Services\RegistrationService;
use App\Services\TournamentService;
use Tests\TestCase;

class TournamentManagementTest extends TestCase
{
    private function expectRule(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected domain rule [{$code}].");
        } catch (DomainRuleException $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());
        }
    }

    public function test_paid_entry_tournament_cannot_be_published_while_flag_is_off(): void
    {
        $this->enablePaidEntry(false);
        $draft = $this->tournament(['entry_fee_minor' => 10000, 'funding_model' => 'player_funded', 'prize_rules' => 'Rules.'], TournamentStatus::Draft);
        $admin = $this->staff(RoleName::SuperAdmin);

        $this->expectRule('paid_entry_disabled', fn () => app(TournamentService::class)->transition($draft, $admin, TournamentStatus::Published));

        $this->enablePaidEntry(true);
        $published = app(TournamentService::class)->transition($draft, $admin, TournamentStatus::Published);
        $this->assertSame(TournamentStatus::Published, $published->status);
    }

    public function test_tournaments_on_unconfirmed_disciplines_cannot_be_published(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);
        $draft = $this->tournament(['discipline' => 'american-pool', 'registration_type' => 'individual'], TournamentStatus::Draft);

        $this->expectRule('discipline_not_ready', fn () => app(TournamentService::class)->transition($draft, $admin, TournamentStatus::Published));
    }

    public function test_invalid_status_transitions_are_refused(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);
        $draft = $this->tournament(status: TournamentStatus::Draft);

        $this->expectRule('invalid_transition', fn () => app(TournamentService::class)->transition($draft, $admin, TournamentStatus::Completed));
        $this->expectRule('reason_required', fn () => app(TournamentService::class)->transition($draft, $admin, TournamentStatus::Cancelled));

        $cancelled = app(TournamentService::class)->transition($draft, $admin, TournamentStatus::Cancelled, 'Venue unavailable');
        $this->expectRule('invalid_transition', fn () => app(TournamentService::class)->transition($cancelled, $admin, TournamentStatus::Published));
    }

    public function test_opening_registration_locks_material_rules(): void
    {
        $organizer = $this->staff(RoleName::TournamentOrganizer);
        $admin = $this->staff(RoleName::SuperAdmin);
        $tournament = $this->tournament(organizer: $organizer);
        $service = app(TournamentService::class);

        $this->assertNotNull($tournament->financial_rules_locked_at);

        // Organizer can still edit non-material details (venue).
        $service->update($tournament, $organizer, ['venue_name' => 'Hall B']);
        $this->assertSame('Hall B', $tournament->fresh()->venue_name);

        // Organizer cannot change locked rules at all.
        $this->expectRule('rules_locked', fn () => $service->update($tournament->fresh(), $organizer, ['max_entrants' => 8, 'prize_rules' => 'Changed']));

        // Super admin needs a written reason, and the correction is audited.
        $this->expectRule('reason_required', fn () => $service->update($tournament->fresh(), $admin, ['prize_rules' => 'Changed']));
        $service->update($tournament->fresh(), $admin, ['prize_rules' => 'Corrected typo in published prize rules.'], 'Typo fix approved by owner');
        $this->assertDatabaseHas('audit_logs', ['action' => 'tournament.locked_rules_corrected', 'actor_id' => $admin->id, 'reason' => 'Typo fix approved by owner']);
    }

    public function test_closing_registration_locks_rosters(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);
        $tournament = $this->tournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());

        app(TournamentService::class)->transition($tournament, $admin, TournamentStatus::RegistrationClosed);

        $this->assertNotNull($entry->fresh()->roster_locked_at);
    }

    public function test_tournament_with_entries_cannot_return_to_draft(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);
        $tournament = $this->tournament();
        app(RegistrationService::class)->register($tournament, $this->player());
        $service = app(TournamentService::class);
        $service->transition($tournament, $admin, TournamentStatus::Postponed, 'Weather');

        $this->expectRule('invalid_transition', fn () => $service->transition($tournament->fresh(), $admin, TournamentStatus::Draft));
    }

    public function test_capacity_and_roster_consistency_rules(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);
        $service = app(TournamentService::class);
        $base = [
            'name' => 'Bad Cup', 'discipline_id' => $this->discipline('9-ball')->id, 'registration_type' => 'individual',
            'format' => 'single_elimination', 'roster_min' => 1, 'roster_max' => 1,
        ];

        $this->expectRule('invalid_capacity', fn () => $service->create($admin, $base + ['min_entrants' => 8, 'max_entrants' => 4]));
        $this->expectRule('registration_type_not_allowed', fn () => $service->create($admin, array_merge($base, ['registration_type' => 'team', 'min_entrants' => 2, 'max_entrants' => 4])));
        $this->expectRule('invalid_currency', fn () => $service->create($admin, $base + ['min_entrants' => 2, 'max_entrants' => 4, 'currency' => 'XYZ']));
    }

    public function test_public_listing_shows_capacity_without_private_data(): void
    {
        $tournament = $this->tournament(['max_entrants' => 2, 'lobby_details' => 'Lobby password 1234']);
        app(RegistrationService::class)->register($tournament, $this->player());

        $this->getJson("/api/v1/tournaments/{$tournament->slug}")
            ->assertOk()
            ->assertJsonPath('data.capacity.reserved', 1)
            ->assertJsonPath('data.capacity.places_left', 1)
            ->assertJsonPath('data.lobby_details', null)
            ->assertJsonPath('data.features.bracket', false);
    }
}
