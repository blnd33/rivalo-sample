<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One player blocked another. Private to the blocker: never shown to the blocked player. */
#[Fillable(['blocker_id', 'blocked_id'])]
class UserBlock extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    /** Either player has blocked the other. */
    public static function between(int $a, int $b): bool
    {
        return self::query()
            ->where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))
            ->exists();
    }

    /** @return list<int> ids of players this user blocked or was blocked by */
    public static function hiddenFrom(int $userId): array
    {
        return self::query()
            ->where('blocker_id', $userId)->pluck('blocked_id')
            ->merge(self::query()->where('blocked_id', $userId)->pluck('blocker_id'))
            ->unique()->values()->all();
    }
}
