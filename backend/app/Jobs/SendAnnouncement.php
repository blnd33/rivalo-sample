<?php

namespace App\Jobs;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Delivers an announcement to its audience. Safe to retry: each user receives it once. */
class SendAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $announcementId)
    {
        $this->afterCommit();
    }

    public function handle(AnnouncementService $service): void
    {
        $a = Announcement::find($this->announcementId);
        if ($a && $a->status === AnnouncementStatus::Sending) {
            $service->deliver($a);
        }
    }
}
