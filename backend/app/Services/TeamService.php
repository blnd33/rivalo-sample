<?php

namespace App\Services;

use App\Enums\CaptainTransferStatus;
use App\Enums\InvitationStatus;
use App\Enums\MembershipStatus;
use App\Enums\TeamRole;
use App\Enums\TeamStatus;
use App\Exceptions\DomainRuleException;
use App\Models\CaptainTransfer;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Team lifecycle. Every method that changes membership locks the team row so concurrent
 * acceptances cannot exceed the sport's member limit.
 *
 * Authorization (who may call what) is enforced by TeamPolicy before these methods run;
 * these methods enforce business rules that hold regardless of who calls them.
 */
class TeamService
{
    public const INVITATION_TTL_DAYS = 14;

    public function create(User $captain, Sport $sport, string $name): Team
    {
        if (! $sport->is_active || ! $sport->supportsTeams()) {
            throw DomainRuleException::make('sport_not_team_based', 'Teams are not available for this sport.');
        }

        return DB::transaction(function () use ($captain, $sport, $name) {
            $normalized = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
            if (Team::where('sport_id', $sport->id)->where('name_normalized', $normalized)->exists()) {
                throw DomainRuleException::make('team_name_taken', 'A team with this name already exists in this sport.', 409);
            }

            $team = Team::create([
                'sport_id' => $sport->id,
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'captain_id' => $captain->id,
            ]);

            TeamMembership::create([
                'team_id' => $team->id,
                'user_id' => $captain->id,
                'role' => TeamRole::Captain,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            Audit::record('team.created', $team, new: ['name' => $team->name, 'sport_id' => $sport->id], actorId: $captain->id);

            return $team;
        });
    }

    public function rename(Team $team, User $actor, string $name): Team
    {
        $this->assertActive($team);
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
        $taken = Team::where('sport_id', $team->sport_id)
            ->where('name_normalized', $normalized)
            ->whereKeyNot($team->id)
            ->exists();
        if ($taken) {
            throw DomainRuleException::make('team_name_taken', 'A team with this name already exists in this sport.', 409);
        }

        $old = $team->name;
        $team->update(['name' => $name]);
        Audit::record('team.renamed', $team, ['name' => $old], ['name' => $team->name], actorId: $actor->id);

        return $team;
    }

    public function invite(Team $team, User $actor, User $invitee, TeamRole $role = TeamRole::Member): TeamInvitation
    {
        $this->assertActive($team);

        if ($role === TeamRole::Captain) {
            throw DomainRuleException::make('invalid_role', 'Captaincy is assigned through a captain transfer, not an invitation.');
        }
        // A block in either direction looks the same as any other unavailable player.
        if (! $invitee->isActive() || UserBlock::between($actor->id, $invitee->id)) {
            throw DomainRuleException::make('user_unavailable', 'This player cannot be invited.');
        }
        if ($team->hasActiveMember($invitee)) {
            throw DomainRuleException::make('already_member', 'This player is already a member of the team.', 409);
        }

        return DB::transaction(function () use ($team, $actor, $invitee, $role) {
            $this->expireStaleInvitations($team);

            $pending = TeamInvitation::where('team_id', $team->id)
                ->where('invited_user_id', $invitee->id)
                ->where('status', InvitationStatus::Pending->value)
                ->exists();
            if ($pending) {
                throw DomainRuleException::make('invitation_pending', 'This player already has a pending invitation.', 409);
            }

            // Must have room for one more member now; acceptance re-checks under a row lock.
            $this->assertCapacity($team, extra: 1);

            $invitation = TeamInvitation::create([
                'team_id' => $team->id,
                'invited_user_id' => $invitee->id,
                'invited_by_id' => $actor->id,
                'role' => $role,
                'status' => InvitationStatus::Pending,
                'expires_at' => now()->addDays(self::INVITATION_TTL_DAYS),
            ]);

            Audit::record('team.invitation_sent', $team, new: ['invited_user_id' => $invitee->id, 'role' => $role->value], actorId: $actor->id);
            app(Notifier::class)->send($invitee, 'team_invitation', ['team' => $team->name], ['invitations', null], "team_invitation:{$invitation->id}");

            return $invitation;
        });
    }

    public function acceptInvitation(TeamInvitation $invitation, User $user): TeamMembership
    {
        if ($invitation->invited_user_id !== $user->id) {
            throw DomainRuleException::make('not_invitee', 'This invitation is addressed to another player.', 403);
        }

        return DB::transaction(function () use ($invitation, $user) {
            /** @var Team $team */
            $team = Team::whereKey($invitation->team_id)->lockForUpdate()->firstOrFail();
            $invitation = TeamInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            $this->assertActive($team);
            if (! $invitation->isOpen()) {
                if ($invitation->status === InvitationStatus::Pending) {
                    $invitation->update(['status' => InvitationStatus::Expired]);
                }
                throw DomainRuleException::make('invitation_closed', 'This invitation is no longer open.', 409);
            }
            if ($team->hasActiveMember($user)) {
                throw DomainRuleException::make('already_member', 'You are already a member of this team.', 409);
            }

            $this->assertCapacity($team, extra: 1);

            $invitation->update(['status' => InvitationStatus::Accepted, 'responded_at' => now()]);

            $membership = TeamMembership::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            Audit::record('team.member_joined', $team, new: ['user_id' => $user->id, 'role' => $invitation->role->value], actorId: $user->id);

            return $membership;
        });
    }

    public function declineInvitation(TeamInvitation $invitation, User $user): TeamInvitation
    {
        if ($invitation->invited_user_id !== $user->id) {
            throw DomainRuleException::make('not_invitee', 'This invitation is addressed to another player.', 403);
        }
        if ($invitation->status !== InvitationStatus::Pending) {
            throw DomainRuleException::make('invitation_closed', 'This invitation is no longer open.', 409);
        }

        $invitation->update(['status' => InvitationStatus::Declined, 'responded_at' => now()]);

        return $invitation;
    }

    public function revokeInvitation(TeamInvitation $invitation, User $actor): TeamInvitation
    {
        if ($invitation->status !== InvitationStatus::Pending) {
            throw DomainRuleException::make('invitation_closed', 'This invitation is no longer open.', 409);
        }

        $invitation->update(['status' => InvitationStatus::Revoked, 'responded_at' => now()]);
        Audit::record('team.invitation_revoked', $invitation->team, new: ['invitation_id' => $invitation->id], actorId: $actor->id);

        return $invitation;
    }

    public function changeMemberRole(Team $team, User $actor, User $member, TeamRole $role): TeamMembership
    {
        $this->assertActive($team);
        if ($role === TeamRole::Captain || $team->isCaptain($member)) {
            throw DomainRuleException::make('invalid_role', 'Use a captain transfer to change the captain.');
        }

        $membership = $team->activeMemberships()->where('user_id', $member->id)->first();
        if (! $membership) {
            throw DomainRuleException::make('not_member', 'This player is not an active member.', 404);
        }

        $old = $membership->role->value;
        $membership->update(['role' => $role]);
        Audit::record('team.member_role_changed', $team, ['user_id' => $member->id, 'role' => $old], ['role' => $role->value], actorId: $actor->id);

        return $membership;
    }

    /** A member leaves voluntarily. The captain must transfer captaincy first (or disband if alone). */
    public function leave(Team $team, User $user): void
    {
        if ($team->isCaptain($user)) {
            throw DomainRuleException::make('captain_cannot_leave', 'Transfer captaincy before leaving the team, or disband it.', 409);
        }

        $this->endMembership($team, $user, MembershipStatus::Left, 'left', $user);
    }

    public function removeMember(Team $team, User $actor, User $member, ?string $reason = null): void
    {
        if ($team->isCaptain($member)) {
            throw DomainRuleException::make('captain_cannot_be_removed', 'The captain cannot be removed. Transfer captaincy first.', 409);
        }

        $this->endMembership($team, $member, MembershipStatus::Removed, $reason ?: 'removed_by_captain', $actor);
    }

    public function requestCaptainTransfer(Team $team, User $actor, User $nominee): CaptainTransfer
    {
        $this->assertActive($team);
        if (! $team->isCaptain($actor)) {
            throw DomainRuleException::make('not_captain', 'Only the captain can transfer captaincy.', 403);
        }
        if ($nominee->id === $actor->id || ! $team->hasActiveMember($nominee)) {
            throw DomainRuleException::make('invalid_nominee', 'The new captain must be another active member of the team.');
        }

        return DB::transaction(function () use ($team, $actor, $nominee) {
            $team->captainTransfers()->where('status', CaptainTransferStatus::Pending->value)
                ->update(['status' => CaptainTransferStatus::Cancelled->value, 'responded_at' => now()]);

            $transfer = CaptainTransfer::create([
                'team_id' => $team->id,
                'from_user_id' => $actor->id,
                'to_user_id' => $nominee->id,
                'initiated_by_id' => $actor->id,
                'status' => CaptainTransferStatus::Pending,
            ]);

            Audit::record('team.captain_transfer_requested', $team, new: ['to_user_id' => $nominee->id], actorId: $actor->id);

            return $transfer;
        });
    }

    public function respondToCaptainTransfer(CaptainTransfer $transfer, User $user, bool $accept): CaptainTransfer
    {
        if ($transfer->to_user_id !== $user->id) {
            throw DomainRuleException::make('not_nominee', 'This transfer is addressed to another player.', 403);
        }

        return DB::transaction(function () use ($transfer, $user, $accept) {
            $team = Team::whereKey($transfer->team_id)->lockForUpdate()->firstOrFail();
            $transfer = CaptainTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== CaptainTransferStatus::Pending) {
                throw DomainRuleException::make('transfer_closed', 'This transfer is no longer pending.', 409);
            }
            if ($team->captain_id !== $transfer->from_user_id) {
                $transfer->update(['status' => CaptainTransferStatus::Cancelled, 'responded_at' => now()]);
                throw DomainRuleException::make('transfer_stale', 'The team captain has changed since this transfer was requested.', 409);
            }

            if (! $accept) {
                $transfer->update(['status' => CaptainTransferStatus::Declined, 'responded_at' => now()]);

                return $transfer;
            }

            $this->applyCaptainChange($team, $user);
            $transfer->update(['status' => CaptainTransferStatus::Accepted, 'responded_at' => now()]);
            Audit::record('team.captain_transferred', $team, ['captain_id' => $transfer->from_user_id], ['captain_id' => $user->id], actorId: $user->id);

            return $transfer;
        });
    }

    public function cancelCaptainTransfer(CaptainTransfer $transfer, User $actor): CaptainTransfer
    {
        if ($transfer->status !== CaptainTransferStatus::Pending) {
            throw DomainRuleException::make('transfer_closed', 'This transfer is no longer pending.', 409);
        }
        $transfer->update(['status' => CaptainTransferStatus::Cancelled, 'responded_at' => now()]);

        return $transfer;
    }

    /** Staff override (e.g. captain unreachable). Requires a reason; always audited. */
    public function forceCaptainTransfer(Team $team, User $staff, User $newCaptain, string $reason): CaptainTransfer
    {
        if (trim($reason) === '') {
            throw DomainRuleException::make('reason_required', 'A reason is required for a forced captain transfer.');
        }
        if (! $team->hasActiveMember($newCaptain)) {
            throw DomainRuleException::make('invalid_nominee', 'The new captain must be an active member of the team.');
        }

        return DB::transaction(function () use ($team, $staff, $newCaptain, $reason) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $from = $team->captain_id;

            $team->captainTransfers()->where('status', CaptainTransferStatus::Pending->value)
                ->update(['status' => CaptainTransferStatus::Cancelled->value, 'responded_at' => now()]);

            $this->applyCaptainChange($team, $newCaptain);

            $transfer = CaptainTransfer::create([
                'team_id' => $team->id,
                'from_user_id' => $from,
                'to_user_id' => $newCaptain->id,
                'initiated_by_id' => $staff->id,
                'status' => CaptainTransferStatus::Forced,
                'reason' => $reason,
                'responded_at' => now(),
            ]);

            Audit::record('team.captain_forced', $team, ['captain_id' => $from], ['captain_id' => $newCaptain->id], $reason, $staff->id);

            return $transfer;
        });
    }

    public function disband(Team $team, User $actor, ?string $reason = null): Team
    {
        $this->assertActive($team);

        return DB::transaction(function () use ($team, $actor, $reason) {
            $hasActiveEntries = $team->entries()
                ->whereIn('registration_status', ['reserved', 'waitlisted'])
                ->exists();
            if ($hasActiveEntries) {
                throw DomainRuleException::make('team_has_active_entries', 'Cancel the team\'s active tournament registrations before disbanding.', 409);
            }

            $team->activeMemberships()->update([
                'status' => MembershipStatus::Left->value,
                'left_at' => now(),
                'left_reason' => 'team_disbanded',
            ]);
            $team->invitations()->where('status', InvitationStatus::Pending->value)
                ->update(['status' => InvitationStatus::Revoked->value, 'responded_at' => now()]);
            $team->update(['status' => TeamStatus::Disbanded, 'disbanded_at' => now()]);

            Audit::record('team.disbanded', $team, reason: $reason, actorId: $actor->id);

            return $team;
        });
    }

    // ---------------------------------------------------------------- Internals

    private function applyCaptainChange(Team $team, User $newCaptain): void
    {
        $team->activeMemberships()->where('user_id', $team->captain_id)
            ->update(['role' => TeamRole::Member->value]);
        $team->activeMemberships()->where('user_id', $newCaptain->id)
            ->update(['role' => TeamRole::Captain->value]);
        $team->update(['captain_id' => $newCaptain->id]);
    }

    private function endMembership(Team $team, User $member, MembershipStatus $status, string $reason, User $actor): void
    {
        DB::transaction(function () use ($team, $member, $status, $reason, $actor) {
            $membership = $team->activeMemberships()->where('user_id', $member->id)->lockForUpdate()->first();
            if (! $membership) {
                throw DomainRuleException::make('not_member', 'This player is not an active member.', 404);
            }

            // Ending a team membership never edits submitted tournament rosters (they are snapshots).
            $membership->update(['status' => $status, 'left_at' => now(), 'left_reason' => mb_substr($reason, 0, 120)]);

            $team->captainTransfers()->where('status', CaptainTransferStatus::Pending->value)
                ->where('to_user_id', $member->id)
                ->update(['status' => CaptainTransferStatus::Cancelled->value, 'responded_at' => now()]);

            Audit::record('team.member_'.$status->value, $team, new: ['user_id' => $member->id], reason: $reason, actorId: $actor->id);
        });
    }

    private function assertActive(Team $team): void
    {
        if (! $team->isActive()) {
            throw DomainRuleException::make('team_disbanded', 'This team has been disbanded.', 409);
        }
    }

    private function assertCapacity(Team $team, int $extra): void
    {
        $limit = $team->sport->team_member_limit;
        if ($limit === null) {
            return;
        }

        $active = $team->activeMemberships()->count();
        if ($active + $extra > $limit) {
            throw DomainRuleException::make('team_full', 'Teams in this sport may have at most :limit members.', 409, ['limit' => $limit]);
        }
    }

    private function expireStaleInvitations(Team $team): void
    {
        $team->invitations()
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => InvitationStatus::Expired->value]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'team';
        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (Team::where('slug', $slug)->exists());

        return $slug;
    }
}
