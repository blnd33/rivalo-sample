<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Writes append-only audit records for privileged or consequential actions.
 * Never pass secrets: sensitive keys are stripped defensively.
 */
class Audit
{
    private const REDACTED_KEYS = [
        'password', 'remember_token', 'app_authentication_secret',
        'app_authentication_recovery_codes', 'token', 'current_password',
    ];

    public static function record(
        string $action,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?string $reason = null,
        ?int $actorId = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'actor_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $old ? Arr::except($old, self::REDACTED_KEYS) : null,
            'new_values' => $new ? Arr::except($new, self::REDACTED_KEYS) : null,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
