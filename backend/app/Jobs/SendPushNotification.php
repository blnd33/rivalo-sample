<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\UserNotification;
use App\Support\Push\PushProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Sends one inbox message to the user's phones. Runs on the queue after the database commit. */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $notificationId)
    {
        $this->afterCommit();
    }

    public function handle(PushProvider $push): void
    {
        $n = UserNotification::with('user')->find($this->notificationId);
        if (! $n || ! $n->user || ! $n->user->isActive()) {
            return;
        }
        $tokens = DeviceToken::where('user_id', $n->user_id)->pluck('token')->all();
        if ($tokens === []) {
            $n->update(['push_status' => 'no_device']);

            return;
        }

        try {
            $invalid = $push->send($tokens, $n->title, $n->body, array_filter([
                'notification_id' => (string) $n->id,
                'type' => $n->type,
                'link_kind' => $n->link_kind,
                'link_ref' => $n->link_ref,
            ]));
        } catch (Throwable $e) {
            $n->update(['push_status' => 'failed']);
            throw $e;
        }

        if ($invalid !== []) {
            DeviceToken::whereIn('token', $invalid)->delete();
        }
        $n->update(['push_status' => 'sent', 'pushed_at' => now()]);
    }
}
