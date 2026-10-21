<?php

namespace App\Support\Push;

use Illuminate\Support\Facades\Log;

/**
 * Default provider: writes the message to the log instead of sending it. Used until a Firebase
 * project is configured (PUSH_DRIVER=fcm), and in tests. Tokens are never logged in full.
 */
class LogPushProvider implements PushProvider
{
    public function send(array $tokens, string $title, ?string $body, array $data = []): array
    {
        Log::info('push.log_driver', [
            'devices' => count($tokens),
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return [];
    }
}
