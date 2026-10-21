<?php

namespace App\Services;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserStatus;
use App\Exceptions\DomainRuleException;
use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use App\Models\TournamentEntryMember;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Announcements written by staff and delivered to inboxes (and phones, per preference).
 * Audiences: everyone, players following a discipline, or a tournament's entrants. An optional
 * language limits delivery to users of that app language.
 */
class AnnouncementService
{
    public function recipients(Announcement $a): Builder
    {
        return User::query()
            ->whereIn('status', [UserStatus::Active->value, UserStatus::PendingDeletion->value])
            ->when($a->locale, fn ($q) => $q->where('locale', $a->locale))
            ->when($a->audience === AnnouncementAudience::Discipline, fn ($q) => $q->whereHas('disciplines', fn ($d) => $d->where('disciplines.id', $a->audience_ref)))
            ->when($a->audience === AnnouncementAudience::TournamentEntrants, fn ($q) => $q->whereIn('id', TournamentEntryMember::query()
                ->where('tournament_id', $a->audience_ref)
                ->where('is_active', true)
                ->whereHas('entry', fn ($e) => $e->whereIn('registration_status', RegistrationStatus::activeValues()))
                ->select('user_id')));
    }

    public function sendNow(Announcement $a, User $staff): Announcement
    {
        $this->assertCan($staff);

        return DB::transaction(function () use ($a, $staff) {
            $a = Announcement::whereKey($a->id)->lockForUpdate()->firstOrFail();
            $this->assertSendable($a);
            $a->update(['status' => AnnouncementStatus::Sending, 'sent_by_id' => $staff->id]);
            Audit::record('announcement.sent', $a, new: ['audience' => $a->audience->value, 'recipients' => $this->recipients($a)->count()], actorId: $staff->id);
            SendAnnouncement::dispatch($a->id);

            return $a;
        });
    }

    public function schedule(Announcement $a, User $staff, Carbon $at): Announcement
    {
        $this->assertCan($staff);
        if ($at->isPast()) {
            throw DomainRuleException::make('time_in_past', 'Choose a time in the future.', 422);
        }
        $this->assertSendable($a);
        $a->update(['status' => AnnouncementStatus::Scheduled, 'send_at' => $at, 'sent_by_id' => $staff->id]);
        Audit::record('announcement.scheduled', $a, new: ['send_at' => $at->toIso8601String()], actorId: $staff->id);

        return $a;
    }

    public function cancel(Announcement $a, User $staff): Announcement
    {
        $this->assertCan($staff);
        if ($a->status !== AnnouncementStatus::Scheduled) {
            throw DomainRuleException::make('not_scheduled', 'Only a scheduled announcement can be cancelled.', 409);
        }
        $a->update(['status' => AnnouncementStatus::Draft, 'send_at' => null]);
        Audit::record('announcement.unscheduled', $a, actorId: $staff->id);

        return $a;
    }

    /** Called by the scheduler: send every announcement whose time has come. */
    public function dispatchDue(): int
    {
        $count = 0;
        foreach (Announcement::where('status', AnnouncementStatus::Scheduled->value)->where('send_at', '<=', now())->get() as $a) {
            $claimed = Announcement::whereKey($a->id)->where('status', AnnouncementStatus::Scheduled->value)->update(['status' => AnnouncementStatus::Sending->value]);
            if ($claimed) {
                SendAnnouncement::dispatch($a->id);
                $count++;
            }
        }

        return $count;
    }

    /** Deliver to every recipient (runs in the queued job). */
    public function deliver(Announcement $a): int
    {
        $notifier = app(Notifier::class);
        $written = 0;
        $this->recipients($a)->select('id')->chunkById(500, function ($users) use ($a, $notifier, &$written) {
            $written += $notifier->deliver($users->pluck('id')->all(), 'announcement', [], $a->link_kind ? [$a->link_kind, $a->link_ref] : ['announcement', $a->id], "announcement:{$a->id}", [
                'title' => $a->title,
                'body' => $a->body,
                'category' => $a->category,
                'announcement_id' => $a->id,
                'push' => $a->send_push,
            ]);
        });
        $a->update(['status' => AnnouncementStatus::Sent, 'sent_at' => now(), 'recipients_count' => $a->recipients_count + $written]);

        return $written;
    }

    private function assertSendable(Announcement $a): void
    {
        if (! in_array($a->status, [AnnouncementStatus::Draft, AnnouncementStatus::Scheduled], true)) {
            throw DomainRuleException::make('already_sent', 'This announcement was already sent.', 409);
        }
        if (in_array($a->audience, [AnnouncementAudience::Discipline, AnnouncementAudience::TournamentEntrants], true) && ! $a->audience_ref) {
            throw DomainRuleException::make('audience_required', 'Choose who should receive this announcement.', 422);
        }
        if (! in_array($a->category, Notifier::ANNOUNCEMENT_CATEGORIES, true)) {
            throw DomainRuleException::make('invalid_category', 'Choose a valid category.', 422);
        }
    }

    private function assertCan(User $staff): void
    {
        if (! $staff->can('announcements.send')) {
            throw DomainRuleException::make('forbidden', 'Only staff who send announcements can do this.', 403);
        }
    }
}
