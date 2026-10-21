<?php

namespace Tests;

use App\Enums\RoleName;
use App\Enums\StaffRole;
use App\Enums\TournamentStatus;
use App\Models\Discipline;
use App\Models\FeatureFlag;
use App\Models\Sport;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TeamService;
use App\Services\TournamentService;
use App\Support\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Seeds reference data only (roles, permissions, flags, catalog). Sample data never runs in tests. */
    protected bool $seed = true;

    protected function player(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(RoleName::Player->value);

        return $user;
    }

    protected function staff(RoleName $role, array $attributes = []): User
    {
        $user = $this->player($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    protected function discipline(string $slug): Discipline
    {
        return Discipline::where('slug', $slug)->firstOrFail();
    }

    protected function sport(string $slug): Sport
    {
        return Sport::where('slug', $slug)->firstOrFail();
    }

    protected function enablePaidEntry(bool $enabled = true): void
    {
        FeatureFlag::where('key', FeatureFlags::PAID_ENTRY)->update(['enabled' => $enabled]);
        FeatureFlags::flush();
    }

    /**
     * Creates a tournament through the service and moves it to the requested status.
     */
    protected function tournament(array $overrides = [], TournamentStatus $status = TournamentStatus::RegistrationOpen, ?User $organizer = null): Tournament
    {
        $admin = $this->staff(RoleName::SuperAdmin);
        $service = app(TournamentService::class);

        $discipline = $this->discipline($overrides['discipline'] ?? '9-ball');
        unset($overrides['discipline']);

        $type = $overrides['registration_type'] ?? ($discipline->registration_types[0]);
        $defaults = match ($type) {
            'pair' => ['roster_min' => 2, 'roster_max' => 2],
            'team' => ['roster_min' => $discipline->team_size_min, 'roster_max' => $discipline->team_size_max, 'substitutes_max' => $discipline->substitutes_max],
            default => ['roster_min' => 1, 'roster_max' => 1],
        };

        $tournament = $service->create($admin, array_merge([
            'name' => 'Test Cup '.uniqid(),
            'discipline_id' => $discipline->id,
            'registration_type' => $type,
            'min_entrants' => 2,
            'max_entrants' => 8,
            'format' => 'single_elimination',
            'rules' => 'Test rules.',
            'starts_at' => now()->addDays(10),
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(5),
            'currency' => 'IQD',
            'entry_fee_minor' => 0,
        ], $defaults, $overrides));

        if ($organizer) {
            $service->assignStaff($tournament, $organizer, $admin, StaffRole::Organizer);
        }

        $path = [
            TournamentStatus::Published,
            TournamentStatus::RegistrationOpen,
            TournamentStatus::RegistrationClosed,
        ];
        foreach ($path as $step) {
            if ($tournament->status === $status) {
                break;
            }
            $tournament = $service->transition($tournament, $admin, $step);
        }

        return $tournament->fresh();
    }

    /** A team in the given sport with the captain plus $extra accepted members. */
    protected function teamWithMembers(string $sportSlug, int $extra, ?User $captain = null): Team
    {
        $service = app(TeamService::class);
        $captain ??= $this->player();
        $team = $service->create($captain, $this->sport($sportSlug), 'Team '.uniqid());

        for ($i = 0; $i < $extra; $i++) {
            $member = $this->player();
            $invitation = $service->invite($team, $captain, $member);
            $service->acceptInvitation($invitation, $member);
        }

        return $team->fresh();
    }
}
