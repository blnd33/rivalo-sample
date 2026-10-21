<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'achievement_id', 'source', 'awarded_by_id', 'reason', 'context', 'awarded_at', 'revoked_at', 'revoked_by_id', 'revoke_reason'])]
class UserAchievement extends Model
{
    protected function casts(): array
    {
        return ['context' => 'array', 'awarded_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    public function scopeHeld(Builder $q): void
    {
        $q->whereNull('revoked_at');
    }
}
