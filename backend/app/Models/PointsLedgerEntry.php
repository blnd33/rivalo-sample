<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Append-only ranking points. Every row explains itself (rule, match or placement, reason). */
#[Fillable([
    'user_id', 'tournament_id', 'tournament_entry_id', 'discipline_id', 'season_id', 'points_rule_set_id',
    'rule_set_version', 'points_rule_id', 'match_result_id', 'award_type', 'points_delta', 'reason',
    'idempotency_key', 'reverses_id', 'created_by_id',
])]
class PointsLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'points_ledger';

    protected function casts(): array
    {
        return ['points_delta' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Points are append-only. Record a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Points are never deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(MatchResult::class, 'match_result_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }
}
