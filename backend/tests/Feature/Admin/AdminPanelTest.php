<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Disciplines\DisciplineResource;
use App\Filament\Resources\FeatureFlags\FeatureFlagResource;
use App\Filament\Resources\Seasons\SeasonResource;
use App\Filament\Resources\Sports\SportResource;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Resources\Tournaments\Pages\ListTournaments;
use App\Filament\Resources\Tournaments\TournamentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\RegistrationService;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    /** Staff with an enrolled authenticator (MFA satisfied). */
    private function enrolledStaff(RoleName $role): User
    {
        $user = $this->staff($role);
        $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        return $user;
    }

    public function test_super_admin_can_open_every_admin_page(): void
    {
        $admin = $this->enrolledStaff(RoleName::SuperAdmin);
        $tournament = $this->tournament();
        app(RegistrationService::class)->register($tournament, $this->player());
        $team = $this->teamWithMembers('padel', 1);

        $this->actingAs($admin);

        $urls = [
            '/admin',
            SportResource::getUrl('index'),
            SportResource::getUrl('create'),
            SportResource::getUrl('edit', ['record' => $this->sport('padel')]),
            DisciplineResource::getUrl('index'),
            DisciplineResource::getUrl('edit', ['record' => $this->discipline('9-ball')]),
            SeasonResource::getUrl('index'),
            UserResource::getUrl('index'),
            UserResource::getUrl('edit', ['record' => $team->captain]),
            TeamResource::getUrl('index'),
            TeamResource::getUrl('view', ['record' => $team]),
            TournamentResource::getUrl('index'),
            TournamentResource::getUrl('create'),
            TournamentResource::getUrl('edit', ['record' => $tournament]),
            FeatureFlagResource::getUrl('index'),
            FeatureFlagResource::getUrl('edit', ['record' => FeatureFlag::first()]),
            AuditLogResource::getUrl('index'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_organizer_tournament_list_contains_only_assigned_tournaments(): void
    {
        $organizer = $this->enrolledStaff(RoleName::TournamentOrganizer);
        $mine = $this->tournament(organizer: $organizer);
        $other = $this->tournament();

        $this->actingAs($organizer);

        Livewire::test(ListTournaments::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);

        $this->get(TournamentResource::getUrl('edit', ['record' => $other]))->assertNotFound();
    }

    public function test_support_staff_cannot_open_catalog_or_flags(): void
    {
        $this->actingAs($this->enrolledStaff(RoleName::SupportStaff));

        $this->get(SportResource::getUrl('index'))->assertForbidden();
        $this->get(FeatureFlagResource::getUrl('index'))->assertForbidden();
        $this->get(AuditLogResource::getUrl('index'))->assertForbidden();
        $this->get(UserResource::getUrl('index'))->assertOk();
    }
}
