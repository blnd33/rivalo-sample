<?php

namespace Tests\Feature\Permissions;

use App\Enums\RoleName;
use App\Enums\StaffRole;
use App\Enums\TournamentStatus;
use App\Models\TournamentEntry;
use App\Services\RegistrationService;
use App\Services\TournamentService;
use Filament\Facades\Filament;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    public function test_players_cannot_access_the_admin_panel(): void
    {
        $player = $this->player();
        $panel = Filament::getPanel('admin');

        $this->assertFalse($player->canAccessPanel($panel));
        $this->actingAs($player)->get('/admin')->assertForbidden();
    }

    public function test_staff_must_have_verified_email_to_access_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($this->staff(RoleName::SupportStaff)->canAccessPanel($panel));
        $this->assertFalse($this->staff(RoleName::SupportStaff, ['email_verified_at' => null])->canAccessPanel($panel));
        $this->assertFalse($this->staff(RoleName::Seller)->canAccessPanel($panel), 'Sellers have no admin access.');
    }

    public function test_admin_panel_requires_multi_factor_authentication_for_staff(): void
    {
        $panel = Filament::getPanel('admin');
        $this->assertTrue($panel->isMultiFactorAuthenticationRequired());

        // A staff member without an enrolled authenticator is sent to set it up before using the panel.
        $organizer = $this->staff(RoleName::TournamentOrganizer);
        $response = $this->actingAs($organizer)->get('/admin');
        $response->assertRedirect();
        $this->assertStringContainsString('multi-factor-authentication', (string) $response->headers->get('Location'));
    }

    public function test_organizers_manage_only_assigned_tournaments(): void
    {
        $organizer = $this->staff(RoleName::TournamentOrganizer);
        $assigned = $this->tournament(organizer: $organizer);
        $other = $this->tournament();

        $this->assertTrue($organizer->can('update', $assigned));
        $this->assertTrue($organizer->can('manageRegistrations', $assigned));
        $this->assertFalse($organizer->can('update', $other));
        $this->assertFalse($organizer->can('manageRegistrations', $other));
        $this->assertFalse($organizer->can('delete', $assigned));
        $this->assertFalse($organizer->can('manageStaff', $assigned));
    }

    public function test_referees_cannot_edit_tournaments_even_when_assigned(): void
    {
        $referee = $this->staff(RoleName::Referee);
        $tournament = $this->tournament();
        app(TournamentService::class)->assignStaff($tournament, $referee, $this->staff(RoleName::SuperAdmin), StaffRole::Referee);

        $this->assertTrue($referee->can('view', $tournament));
        $this->assertFalse($referee->can('update', $tournament));
        $this->assertFalse($referee->can('manageRegistrations', $tournament));
    }

    public function test_players_cannot_perform_staff_actions_on_their_own_entry(): void
    {
        // Covers "a player cannot mark their own entry fee as paid": payment, check-in and
        // eligibility changes require staffManage, which an entrant never holds for their entry.
        $player = $this->player();
        $entry = app(RegistrationService::class)->register($this->tournament(), $player);

        $this->assertTrue($player->can('cancel', $entry));
        $this->assertFalse($player->can('staffManage', $entry));
        $this->assertFalse($player->can('update', $entry));

        // The public API exposes no way to change payment/check-in status.
        $status = $this->actingAs($player, 'sanctum')
            ->patchJson('/api/v1/registrations/'.$entry->id, ['payment_status' => 'paid'])
            ->status();
        $this->assertContains($status, [404, 405]);
        $this->assertSame('not_required', TournamentEntry::find($entry->id)->payment_status->value);
    }

    public function test_only_super_admins_hold_every_permission(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);
        $support = $this->staff(RoleName::SupportStaff);

        $this->assertTrue($admin->can('feature_flags.manage'));
        $this->assertTrue($admin->can('audit.view'));
        $this->assertFalse($support->can('feature_flags.manage'));
        $this->assertFalse($support->can('catalog.manage'));
        $this->assertTrue($support->can('teams.moderate'));
    }

    public function test_draft_tournaments_are_hidden_from_the_public_api(): void
    {
        $draft = $this->tournament(status: TournamentStatus::Draft);

        $this->getJson('/api/v1/tournaments/'.$draft->slug)->assertNotFound();
        $this->getJson('/api/v1/tournaments')->assertOk()->assertJsonMissing(['slug' => $draft->slug]);
    }
}
