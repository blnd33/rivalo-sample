<?php

namespace App\Services;

use App\Enums\EntryMemberRole;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentEntryMember;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tournament registration with capacity, waitlist and conflict prevention.
 *
 * Concurrency: every capacity-affecting method locks the tournament row (SELECT ... FOR UPDATE)
 * so two simultaneous registrations cannot both take the last place. A partial unique index on
 * tournament_entry_members(tournament_id, user_id) is the final safeguard against a player
 * appearing in two active entries of the same tournament.
 *
 * Pairs register as a two-player team. This reuses team invitations, so a partner is never
 * added to an entry without having accepted membership first.
 */
class RegistrationService
{
    public function __construct(private readonly TournamentService $tournaments) {}

    /**
     * @param  list<int>  $playerIds  user ids of the starting roster (team/pair registrations)
     * @param  list<int>  $substituteIds  user ids of substitutes (team registrations)
     */
    public function register(
        Tournament $tournament,
        User $actor,
        ?Team $team = null,
        array $playerIds = [],
        array $substituteIds = [],
    ): TournamentEntry {
        $this->assertActorMayRegister($actor);

        try {
            return DB::transaction(function () use ($tournament, $actor, $team, $playerIds, $substituteIds) {
                /** @var Tournament $tournament */
                $tournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();

                $this->assertRegistrationOpen($tournament);
                $this->tournaments->assertPaidEntryAllowed($tournament);

                [$roster, $displayName] = $this->buildRoster($tournament, $actor, $team, $playerIds, $substituteIds);

                $this->assertNoConflicts($tournament, $roster->pluck('user')->all(), $team);

                $reservedCount = $tournament->reservedEntries()->count();
                $hasPlace = $reservedCount < $tournament->max_entrants;

                $entry = TournamentEntry::create([
                    'tournament_id' => $tournament->id,
                    'entry_type' => $tournament->registration_type,
                    'team_id' => $team?->id,
                    'display_name' => $displayName,
                    'registered_by_id' => $actor->id,
                    'registration_status' => $hasPlace ? RegistrationStatus::Reserved : RegistrationStatus::Waitlisted,
                    'payment_status' => $tournament->isPaidEntry() ? PaymentStatus::Unpaid : PaymentStatus::NotRequired,
                    'waitlist_position' => $hasPlace ? null : $this->nextWaitlistPosition($tournament),
                    'reserved_at' => $hasPlace ? now() : null,
                ]);

                foreach ($roster as $row) {
                    /** @var User $user */
                    $user = $row['user'];
                    TournamentEntryMember::create([
                        'tournament_entry_id' => $entry->id,
                        'tournament_id' => $tournament->id,
                        'user_id' => $user->id,
                        'role' => $row['role'],
                        'username_snapshot' => $user->username,
                        'display_name_snapshot' => $user->display_name,
                        'is_active' => true,
                    ]);
                }

                Audit::record('registration.created', $entry, new: [
                    'tournament_id' => $tournament->id,
                    'status' => $entry->registration_status->value,
                    'members' => $roster->map(fn ($r) => $r['user']->id)->all(),
                ], actorId: $actor->id);

                return $entry->load('activeMembers');
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainRuleException::make('conflicting_registration', 'One or more players are already registered in this tournament.', 409);
        }
    }

    /**
     * Replace the roster of an entry before it is locked (team/pair entries only).
     *
     * @param  list<int>  $playerIds
     * @param  list<int>  $substituteIds
     */
    public function updateRoster(TournamentEntry $entry, User $actor, array $playerIds, array $substituteIds = []): TournamentEntry
    {
        try {
            return DB::transaction(function () use ($entry, $actor, $playerIds, $substituteIds) {
                $tournament = Tournament::whereKey($entry->tournament_id)->lockForUpdate()->firstOrFail();
                $entry = TournamentEntry::whereKey($entry->id)->lockForUpdate()->with('team')->firstOrFail();

                if (! $entry->isActive()) {
                    throw DomainRuleException::make('entry_inactive', 'This registration is no longer active.', 409);
                }
                if ($entry->isRosterLocked() || ! $tournament->isRegistrationWindowOpen()) {
                    throw DomainRuleException::make('roster_locked', 'The roster is locked.', 409);
                }
                if (! $entry->team) {
                    throw DomainRuleException::make('not_team_entry', 'Only team and pair rosters can be edited.');
                }

                [$roster] = $this->buildRoster($tournament, $actor, $entry->team, $playerIds, $substituteIds);

                $oldIds = $entry->activeMembers()->pluck('user_id')->all();
                $entry->activeMembers()->update(['is_active' => false, 'removed_at' => now()]);

                $this->assertNoConflicts($tournament, $roster->pluck('user')->all(), null, ignoreEntryId: $entry->id);

                foreach ($roster as $row) {
                    TournamentEntryMember::create([
                        'tournament_entry_id' => $entry->id,
                        'tournament_id' => $tournament->id,
                        'user_id' => $row['user']->id,
                        'role' => $row['role'],
                        'username_snapshot' => $row['user']->username,
                        'display_name_snapshot' => $row['user']->display_name,
                        'is_active' => true,
                    ]);
                }

                Audit::record('registration.roster_updated', $entry, ['members' => $oldIds], ['members' => $roster->map(fn ($r) => $r['user']->id)->all()], actorId: $actor->id);

                return $entry->load('activeMembers');
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainRuleException::make('conflicting_registration', 'One or more players are already registered in this tournament.', 409);
        }
    }

    /**
     * Cancel an entry. Players may cancel only while registration is open; staff may cancel until
     * the tournament starts. A freed place is offered to the first waitlisted entry in the same transaction.
     */
    public function cancel(TournamentEntry $entry, User $actor, ?string $reason = null, bool $asStaff = false): TournamentEntry
    {
        return DB::transaction(function () use ($entry, $actor, $reason, $asStaff) {
            $tournament = Tournament::whereKey($entry->tournament_id)->lockForUpdate()->firstOrFail();
            $entry = TournamentEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if (! $entry->isActive()) {
                throw DomainRuleException::make('entry_inactive', 'This registration is no longer active.', 409);
            }

            if ($asStaff) {
                if (in_array($tournament->status, [TournamentStatus::InProgress, TournamentStatus::Completed], true)) {
                    throw DomainRuleException::make('tournament_started', 'Use withdrawal or no-show handling once the tournament has started.', 409);
                }
                if (blank($reason)) {
                    throw DomainRuleException::make('reason_required', 'A reason is required when staff cancel a registration.');
                }
            } elseif (! $tournament->isRegistrationWindowOpen()) {
                throw DomainRuleException::make('cancellation_closed', 'Registration has closed. Contact the organizer to withdraw.', 409);
            }

            $wasReserved = $entry->registration_status === RegistrationStatus::Reserved;

            // Refunds for already-paid entries are recorded in person by staff (Milestone 4); payment status is untouched here.
            $entry->update([
                'registration_status' => RegistrationStatus::Cancelled,
                'waitlist_position' => null,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancelled_by_id' => $actor->id,
            ]);
            $entry->activeMembers()->update(['is_active' => false, 'removed_at' => now()]);

            Audit::record('registration.cancelled', $entry, ['status' => $wasReserved ? 'reserved' : 'waitlisted'], ['status' => 'cancelled'], $reason, $actor->id);

            if ($wasReserved) {
                $this->promoteFromWaitlist($tournament);
            }

            return $entry;
        });
    }

    /**
     * Withdraw an entry outside the normal cancellation window (e.g. account deletion, staff action
     * after registration closed). Promotes the waitlist while the tournament has not started.
     */
    public function withdraw(TournamentEntry $entry, ?User $actor, string $reason): TournamentEntry
    {
        return DB::transaction(function () use ($entry, $actor, $reason) {
            $tournament = Tournament::whereKey($entry->tournament_id)->lockForUpdate()->firstOrFail();
            $entry = TournamentEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if (! $entry->isActive()) {
                return $entry;
            }

            $wasReserved = $entry->registration_status === RegistrationStatus::Reserved;
            $entry->update([
                'registration_status' => RegistrationStatus::Withdrawn,
                'waitlist_position' => null,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancelled_by_id' => $actor?->id,
            ]);
            $entry->activeMembers()->update(['is_active' => false, 'removed_at' => now()]);

            Audit::record('registration.withdrawn', $entry, new: ['status' => 'withdrawn'], reason: $reason, actorId: $actor?->id);

            $started = in_array($tournament->status, [TournamentStatus::InProgress, TournamentStatus::Completed], true);
            if ($wasReserved && ! $started) {
                $this->promoteFromWaitlist($tournament);
            }

            return $entry;
        });
    }

    /** Staff marks a reserved entry as a no-show (no points consequence is implied here; see D11). */
    public function markNoShow(TournamentEntry $entry, User $staff, string $reason): TournamentEntry
    {
        return DB::transaction(function () use ($entry, $staff, $reason) {
            $entry = TournamentEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->registration_status !== RegistrationStatus::Reserved) {
                throw DomainRuleException::make('entry_not_reserved', 'Only reserved entries can be marked as no-show.', 409);
            }

            $entry->update(['registration_status' => RegistrationStatus::NoShow]);
            // The roster stays recorded as a historical fact but no longer blocks the players.
            $entry->activeMembers()->update(['is_active' => false, 'removed_at' => now()]);

            Audit::record('registration.no_show', $entry, ['status' => 'reserved'], ['status' => 'no_show'], $reason, $staff->id);

            return $entry;
        });
    }

    // ---------------------------------------------------------------- Internals

    private function promoteFromWaitlist(Tournament $tournament): void
    {
        if ($tournament->reservedEntries()->count() >= $tournament->max_entrants) {
            return;
        }

        $next = $tournament->waitlistedEntries()->orderBy('waitlist_position')->lockForUpdate()->first();
        if (! $next) {
            return;
        }

        $next->update([
            'registration_status' => RegistrationStatus::Reserved,
            'waitlist_position' => null,
            'reserved_at' => now(),
        ]);

        Audit::record('registration.promoted_from_waitlist', $next, ['status' => 'waitlisted'], ['status' => 'reserved']);
        app(Notifier::class)->sendToEntries([$next->id], 'waitlist_promoted', ['tournament' => $tournament->name], ['tournament', $tournament->slug], "promoted:{$next->id}");
    }

    private function nextWaitlistPosition(Tournament $tournament): int
    {
        return ((int) $tournament->waitlistedEntries()->max('waitlist_position')) + 1;
    }

    private function assertActorMayRegister(User $actor): void
    {
        if (! $actor->isActive()) {
            throw DomainRuleException::make('account_inactive', 'Your account cannot register for tournaments.', 403);
        }
        if (! $actor->hasVerifiedEmail()) {
            throw DomainRuleException::make('email_unverified', 'Verify your email address before registering.', 403);
        }
    }

    private function assertRegistrationOpen(Tournament $tournament): void
    {
        if (! $tournament->isRegistrationWindowOpen()) {
            throw DomainRuleException::make('registration_closed', 'Registration is not open for this tournament.', 409);
        }
    }

    /**
     * @return array{0: Collection<int, array{user: User, role: EntryMemberRole}>, 1: string}
     */
    private function buildRoster(Tournament $tournament, User $actor, ?Team $team, array $playerIds, array $substituteIds): array
    {
        if ($tournament->registration_type === RegistrationType::Individual) {
            if ($team) {
                throw DomainRuleException::make('team_not_allowed', 'This tournament accepts individual registrations only.');
            }

            return [collect([['user' => $actor, 'role' => EntryMemberRole::Player]]), $actor->publicName()];
        }

        // Pair and team registrations go through a team.
        if (! $team) {
            throw DomainRuleException::make('team_required', 'Choose a team (pairs register as a two-player team).');
        }
        if (! $team->isActive()) {
            throw DomainRuleException::make('team_disbanded', 'This team has been disbanded.', 409);
        }
        if (! $team->isCaptain($actor)) {
            throw DomainRuleException::make('not_captain', 'Only the team captain can register the team.', 403);
        }
        if ($team->sport_id !== $tournament->sport_id) {
            throw DomainRuleException::make('sport_mismatch', 'This team plays a different sport.');
        }

        $playerIds = array_values(array_unique(array_map('intval', $playerIds)));
        $substituteIds = array_values(array_unique(array_map('intval', $substituteIds)));

        if (array_intersect($playerIds, $substituteIds)) {
            throw DomainRuleException::make('duplicate_roster_member', 'A player cannot be both a starter and a substitute.');
        }
        if (count($playerIds) < $tournament->roster_min || count($playerIds) > $tournament->roster_max) {
            throw DomainRuleException::make('roster_size', 'Choose between :min and :max players.', 422, ['min' => $tournament->roster_min, 'max' => $tournament->roster_max]);
        }
        if (count($substituteIds) > $tournament->substitutes_max) {
            throw DomainRuleException::make('too_many_substitutes', 'At most :count substitutes are allowed.', 422, ['count' => $tournament->substitutes_max]);
        }

        $allIds = array_merge($playerIds, $substituteIds);
        $activeMemberIds = $team->activeMemberships()->whereIn('user_id', $allIds)->pluck('user_id')->all();
        $notMembers = array_diff($allIds, $activeMemberIds);
        if ($notMembers) {
            throw DomainRuleException::make('not_team_member', 'Only accepted, active team members can be added to the roster.');
        }

        $users = User::whereIn('id', $allIds)->get()->keyBy('id');
        foreach ($users as $user) {
            if (! $user->isActive()) {
                throw DomainRuleException::make('member_inactive', ':username cannot be registered.', 422, ['username' => $user->username]);
            }
        }

        $roster = collect();
        foreach ($playerIds as $id) {
            $roster->push(['user' => $users[$id], 'role' => $id === $team->captain_id ? EntryMemberRole::Captain : EntryMemberRole::Player]);
        }
        foreach ($substituteIds as $id) {
            $roster->push(['user' => $users[$id], 'role' => EntryMemberRole::Substitute]);
        }

        return [$roster, $team->name];
    }

    /** @param  list<User>  $users */
    private function assertNoConflicts(Tournament $tournament, array $users, ?Team $team, ?int $ignoreEntryId = null): void
    {
        if ($team && $tournament->entries()->where('team_id', $team->id)
            ->whereIn('registration_status', RegistrationStatus::activeValues())->exists()) {
            throw DomainRuleException::make('already_registered', 'This team is already registered.', 409);
        }

        $ids = array_map(fn (User $u) => $u->id, $users);
        $conflicts = TournamentEntryMember::query()
            ->where('tournament_id', $tournament->id)
            ->where('is_active', true)
            ->whereIn('user_id', $ids)
            ->when($ignoreEntryId, fn ($q) => $q->where('tournament_entry_id', '!=', $ignoreEntryId))
            ->pluck('username_snapshot');

        if ($conflicts->isNotEmpty()) {
            throw DomainRuleException::make(
                $conflicts->count() === 1 && count($ids) === 1 ? 'already_registered' : 'conflicting_registration',
                'Already registered in this tournament: :players.',
                409,
                ['players' => $conflicts->implode(', ')],
            );
        }
    }
}
