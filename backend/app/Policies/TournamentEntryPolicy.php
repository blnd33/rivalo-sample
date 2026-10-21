<?php

namespace App\Policies;

use App\Models\TournamentEntry;
use App\Models\User;

class TournamentEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tournaments.manage_all') || $user->can('registrations.manage_assigned');
    }

    public function view(User $user, TournamentEntry $entry): bool
    {
        return $entry->isOwnedBy($user)
            || $entry->members()->where('user_id', $user->id)->exists()
            || $user->can('manageRegistrations', $entry->tournament);
    }

    /** Owner-side changes (registrant or current team captain). */
    public function cancel(User $user, TournamentEntry $entry): bool
    {
        return $entry->isOwnedBy($user);
    }

    public function updateRoster(User $user, TournamentEntry $entry): bool
    {
        return $entry->team !== null && $entry->team->isCaptain($user);
    }

    /** Staff-side changes for assigned organizers. A player can never reach these on their own entry. */
    public function staffManage(User $user, TournamentEntry $entry): bool
    {
        return $user->can('manageRegistrations', $entry->tournament);
    }

    public function create(User $user): bool
    {
        return false; // Registrations are created only through RegistrationService via the player API.
    }

    public function update(User $user, TournamentEntry $entry): bool
    {
        return $this->staffManage($user, $entry);
    }

    public function delete(User $user, TournamentEntry $entry): bool
    {
        return false; // Entries are cancelled, never deleted.
    }
}
