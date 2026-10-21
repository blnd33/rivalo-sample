<?php

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Enums\ModerationAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReportSubject;
use App\Enums\UserStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Page;
use App\Models\Report;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reports, moderation and blocking (Milestone 7).
 *
 * - Players report a player, a team, or a problem with the app. A snapshot keeps what the
 *   profile or team looked like at the time, because it may change before staff look.
 * - Staff dismiss a report or act on it. Every action needs a reason and is audited; the
 *   affected player is told what was removed; the reporter is told the report was reviewed,
 *   never what happened to the other player.
 * - Blocking is private. It hides the two players from each other's search and stops team
 *   invitations between them. It does not remove anyone from a team they already share.
 */
class ModerationService
{
    public const MAX_BLOCKS = 500;

    public function __construct(
        private readonly Notifier $notifier,
        private readonly AccountService $accounts,
        private readonly TeamService $teams,
    ) {}

    // ---------------------------------------------------------------- Reporting

    /**
     * @param  array{app_version?: ?string, platform?: ?string}  $context
     */
    public function report(User $reporter, ReportSubject $type, User|Team|null $subject, ReportReason $reason, ?string $details = null, array $context = []): Report
    {
        if (! in_array($reason, $type->reasons(), true)) {
            throw DomainRuleException::make('invalid_reason', 'Choose a reason that fits what you are reporting.');
        }
        if ($type !== ReportSubject::App && $subject === null) {
            throw DomainRuleException::make('report_subject_missing', 'Choose who or what you are reporting.');
        }
        if ($subject instanceof User && $subject->is($reporter)) {
            throw DomainRuleException::make('report_self', 'You cannot report yourself.');
        }
        if ($subject instanceof User && $subject->status === UserStatus::Deleted) {
            throw DomainRuleException::make('report_subject_missing', 'This account no longer exists.', 404);
        }

        $details = filled($details) ? mb_substr(trim($details), 0, 1000) : null;
        if ($reason === ReportReason::Other && $details === null) {
            throw DomainRuleException::make('report_details_required', 'Tell us briefly what happened.');
        }
        if ($type === ReportSubject::App && $details === null) {
            throw DomainRuleException::make('report_details_required', 'Tell us briefly what happened.');
        }

        try {
            return Report::create([
                'reporter_id' => $reporter->id,
                'subject_type' => $type,
                'subject_user_id' => $subject instanceof User ? $subject->id : null,
                'subject_team_id' => $subject instanceof Team ? $subject->id : null,
                'dedupe_key' => $subject ? $type->value.':'.$subject->getKey() : null,
                'reason' => $reason,
                'details' => $details,
                'snapshot' => $this->snapshot($subject),
                'context' => $type === ReportSubject::App ? array_filter([
                    'app_version' => isset($context['app_version']) ? mb_substr((string) $context['app_version'], 0, 40) : null,
                    'platform' => isset($context['platform']) ? mb_substr((string) $context['platform'], 0, 20) : null,
                    'locale' => $reporter->locale,
                ]) : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw DomainRuleException::make('report_exists', 'You already reported this. Our team will review it.', 409);
        }
    }

    /** What the subject looked like when reported. Public profile fields only. */
    private function snapshot(User|Team|null $subject): ?array
    {
        return match (true) {
            $subject instanceof User => [
                'username' => $subject->username,
                'display_name' => $subject->display_name,
                'avatar_path' => $subject->avatar_path,
                'city' => $subject->city,
            ],
            $subject instanceof Team => [
                'name' => $subject->name,
                'slug' => $subject->slug,
                'logo_path' => $subject->logo_path,
                'captain_id' => $subject->captain_id,
            ],
            default => null,
        };
    }

    // ---------------------------------------------------------------- Staff decisions

    public function dismiss(Report $report, User $staff, string $note): Report
    {
        $this->assertCanHandle($report, $staff);

        return DB::transaction(function () use ($report, $staff, $note) {
            $report = $this->lockOpen($report);
            $report->update([
                'status' => ReportStatus::Dismissed,
                'resolution_note' => $note,
                'handled_by_id' => $staff->id,
                'handled_at' => now(),
            ]);
            Audit::record('report.dismissed', $report, new: ['reason' => $report->reason->value], reason: $note, actorId: $staff->id);
            $this->tellReporter($report, 'dismissed');

            return $report;
        });
    }

    /**
     * @param  array{message?: ?string, new_name?: ?string}  $options  message: shown to the warned player
     */
    public function act(Report $report, User $staff, ModerationAction $action, string $note, array $options = []): Report
    {
        $this->assertCanHandle($report, $staff);
        if (! in_array($action, $report->subject_type->actions(), true)) {
            throw DomainRuleException::make('invalid_action', 'This action does not apply to this report.');
        }

        return DB::transaction(function () use ($report, $staff, $action, $note, $options) {
            $report = $this->lockOpen($report);
            $user = $report->subjectUser;
            $team = $report->subjectTeam;

            if ($report->subject_type === ReportSubject::User && ! $user) {
                throw DomainRuleException::make('report_subject_missing', 'This account no longer exists.', 404);
            }
            if ($report->subject_type === ReportSubject::Team && ! $team) {
                throw DomainRuleException::make('report_subject_missing', 'This team no longer exists.', 404);
            }

            match ($action) {
                ModerationAction::RemovePhoto => $user ? $this->removeAvatar($user, $staff, $note) : $this->removeLogo($team, $staff, $note),
                ModerationAction::ResetDisplayName => $this->resetDisplayName($user, $staff, $note),
                ModerationAction::RenameTeam => $this->renameTeam($team, $staff, (string) ($options['new_name'] ?? ''), $note),
                ModerationAction::Warn => $this->warn($user ?? $team->captain, $staff, $options['message'] ?? null, $note, $report),
                ModerationAction::Suspend => $this->suspend($user, $staff, $note),
                ModerationAction::Resolved => null,
            };

            $resolved = [
                'status' => ReportStatus::Actioned,
                'action' => $action,
                'resolution_note' => $note,
                'handled_by_id' => $staff->id,
                'handled_at' => now(),
            ];
            $report->update($resolved);
            Audit::record('report.actioned', $report, new: ['action' => $action->value], reason: $note, actorId: $staff->id);
            $this->tellReporter($report, $report->subject_type === ReportSubject::App ? 'feedback' : 'actioned');

            // Other open reports about the same player or team are closed by the same decision.
            if ($report->dedupe_key !== null) {
                $others = Report::open()->where('dedupe_key', $report->dedupe_key)->whereKeyNot($report->id)->lockForUpdate()->get();
                foreach ($others as $other) {
                    $other->update(['resolution_note' => __('Closed with report #:id', ['id' => $report->id]).' — '.$note] + $resolved);
                    $this->tellReporter($other, 'actioned');
                }
            }

            return $report;
        });
    }

    private function assertCanHandle(Report $report, User $staff): void
    {
        if (! $staff->can('moderation.manage')) {
            throw DomainRuleException::make('forbidden', 'You do not have permission to handle reports.', 403);
        }
        // No one decides a report about themselves or that they filed.
        $team = $report->subject_team_id ? Team::find($report->subject_team_id) : null;
        if ($report->subject_user_id === $staff->id || $report->reporter_id === $staff->id || $team?->hasActiveMember($staff)) {
            throw DomainRuleException::make('moderation_conflict', 'Another staff member must handle a report that involves you.', 403);
        }
    }

    private function lockOpen(Report $report): Report
    {
        $report = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
        if (! $report->isOpen()) {
            throw DomainRuleException::make('report_closed', 'This report has already been handled.', 409);
        }

        return $report;
    }

    private function removeAvatar(User $user, User $staff, string $note): void
    {
        if (! $user->avatar_path) {
            throw DomainRuleException::make('nothing_to_remove', 'This player has no profile photo now.');
        }
        $this->accounts->removeAvatar($user);
        Audit::record('moderation.avatar_removed', $user, reason: $note, actorId: $staff->id);
        $this->notifier->send($user, 'moderation_photo_removed', [], ['page', Page::GUIDELINES], null);
    }

    private function removeLogo(Team $team, User $staff, string $note): void
    {
        if (! $team->logo_path) {
            throw DomainRuleException::make('nothing_to_remove', 'This team has no logo now.');
        }
        $path = $team->logo_path;
        $team->forceFill(['logo_path' => null])->save();
        DB::afterCommit(fn () => Storage::disk('public')->delete($path));
        Audit::record('moderation.team_logo_removed', $team, reason: $note, actorId: $staff->id);
        $this->notifier->send([$team->captain_id], 'moderation_logo_removed', ['team' => $team->name], ['page', Page::GUIDELINES], null);
    }

    private function resetDisplayName(User $user, User $staff, string $note): void
    {
        if (blank($user->display_name)) {
            throw DomainRuleException::make('nothing_to_remove', 'This player has no display name now.');
        }
        $old = $user->display_name;
        $user->forceFill(['display_name' => null])->save();
        Audit::record('moderation.display_name_reset', $user, ['display_name' => $old], ['display_name' => null], $note, $staff->id);
        $this->notifier->send($user, 'moderation_name_reset', [], ['page', Page::GUIDELINES], null);
    }

    private function renameTeam(Team $team, User $staff, string $newName, string $note): void
    {
        $newName = trim($newName);
        if (mb_strlen($newName) < 2 || mb_strlen($newName) > 60) {
            throw DomainRuleException::make('invalid_team_name', 'Enter a new team name (2 to 60 characters).');
        }
        $old = $team->name;
        $this->teams->rename($team, $staff, $newName);
        Audit::record('moderation.team_renamed', $team, ['name' => $old], ['name' => $newName], $note, $staff->id);
        $this->notifier->send($team->activeMemberships()->pluck('user_id'), 'moderation_team_renamed', ['old' => $old, 'team' => $newName], ['team', $team->slug], null);
    }

    private function warn(User $user, User $staff, ?string $message, string $note, Report $report): void
    {
        $message = filled($message) ? mb_substr(trim($message), 0, 1000) : null;
        Audit::record('moderation.warned', $user, new: ['message' => $message], reason: $note, actorId: $staff->id);
        $this->notifier->send($user, 'moderation_warning', ['message' => $message], ['page', Page::GUIDELINES], "moderation_warning:report:{$report->id}");
    }

    private function suspend(User $user, User $staff, string $note): void
    {
        if ($user->isStaff() && ! $staff->can('users.manage')) {
            throw DomainRuleException::make('forbidden', 'Only a super admin can suspend a staff account.', 403);
        }
        if ($user->status !== UserStatus::Active) {
            throw DomainRuleException::make('user_not_active', 'This account is not active.');
        }
        $user->forceFill(['status' => UserStatus::Suspended, 'suspension_reason' => $note])->save();
        $user->tokens()->delete(); // ends every app session (and removes its push tokens)
        Audit::record('user.suspended', $user, ['status' => 'active'], ['status' => 'suspended'], $note, $staff->id);
    }

    private function tellReporter(Report $report, string $outcome): void
    {
        if ($report->reporter_id) {
            $this->notifier->send([$report->reporter_id], 'report_reviewed', ['outcome' => $outcome], null, "report_reviewed:{$report->id}");
        }
    }

    // ---------------------------------------------------------------- Blocking

    public function block(User $blocker, User $blocked): void
    {
        if ($blocker->is($blocked)) {
            throw DomainRuleException::make('block_self', 'You cannot block yourself.');
        }
        if (UserBlock::where('blocker_id', $blocker->id)->count() >= self::MAX_BLOCKS) {
            throw DomainRuleException::make('block_limit', 'You have reached the limit of blocked players.');
        }

        DB::transaction(function () use ($blocker, $blocked) {
            UserBlock::firstOrCreate(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id]);

            // Pending team invitations between the two players are withdrawn.
            TeamInvitation::query()
                ->where('status', InvitationStatus::Pending->value)
                ->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('invited_user_id', $blocker->id)->where('invited_by_id', $blocked->id))
                    ->orWhere(fn ($q) => $q->where('invited_user_id', $blocked->id)->where('invited_by_id', $blocker->id)))
                ->update(['status' => InvitationStatus::Revoked->value, 'updated_at' => now()]);
        });
    }

    public function unblock(User $blocker, User $blocked): void
    {
        UserBlock::where('blocker_id', $blocker->id)->where('blocked_id', $blocked->id)->delete();
    }
}
