<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\User;

/**
 * Staff authorization for tournaments (admin panel and staff API).
 * Players read published tournaments through public API endpoints, which never use these abilities.
 */
class TournamentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tournaments.manage_all')
            || $user->can('tournaments.manage_assigned')
            || $user->can('results.review_assigned');
    }

    public function view(User $user, Tournament $tournament): bool
    {
        return $user->can('tournaments.manage_all') || $user->isAssignedTo($tournament);
    }

    public function create(User $user): bool
    {
        return $user->can('tournaments.create') || $user->can('tournaments.manage_all');
    }

    public function update(User $user, Tournament $tournament): bool
    {
        if (in_array($tournament->status, [TournamentStatus::Completed, TournamentStatus::Cancelled], true)) {
            return $user->can('tournaments.manage_all');
        }

        return $user->can('tournaments.manage_all')
            || ($user->can('tournaments.manage_assigned') && $user->isAssignedTo($tournament, StaffRole::Organizer->value));
    }

    public function delete(User $user, Tournament $tournament): bool
    {
        return $user->can('tournaments.manage_all')
            && $tournament->status === TournamentStatus::Draft
            && ! $tournament->entries()->exists();
    }

    public function manageStaff(User $user, Tournament $tournament): bool
    {
        return $user->can('tournaments.manage_all');
    }

    /** Venue check-in: assigned organizers with the check-in permission, or global managers. */
    public function recordCheckIn(User $user, Tournament $tournament): bool
    {
        return $user->can('tournaments.manage_all')
            || ($user->can('checkin.record') && $user->isAssignedTo($tournament, StaffRole::Organizer->value));
    }

    /** In-person cash payments, refunds and corrections. Players can never hold this. */
    public function recordPayments(User $user, Tournament $tournament): bool
    {
        return $user->can('tournaments.manage_all')
            || ($user->can('payments.record') && $user->isAssignedTo($tournament, StaffRole::Organizer->value));
    }

    public function manageRegistrations(User $user, Tournament $tournament): bool
    {
        return $user->can('tournaments.manage_all')
            || ($user->can('registrations.manage_assigned') && $user->isAssignedTo($tournament, StaffRole::Organizer->value));
    }

    /** Draws, fixtures, schedule, entering results: assigned organizers or global managers. */
    public function manageFixtures(User $user, Tournament $tournament): bool
    {
        return $user->can('tournaments.manage_all')
            || ($user->can('tournaments.manage_assigned') && $user->isAssignedTo($tournament, StaffRole::Organizer->value));
    }

    /** Approving results and resolving disputes: assigned referees or global managers. */
    public function reviewResults(User $user, Tournament $tournament): bool
    {
        return $user->can('tournaments.manage_all')
            || ($user->can('results.review_assigned') && $user->isAssignedTo($tournament, StaffRole::Referee->value));
    }
}
