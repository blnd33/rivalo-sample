<?php

namespace App\Models;

use App\Enums\FinanceRulesStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tournament money rules (decision D4 is still open, so these start as drafts).
 * Shares are basis points of NET entry revenue: 2500 = 25%.
 */
#[Fillable(['tournament_id', 'prize_pool_bp', 'organizer_share_bp', 'status', 'notes', 'approved_by_id', 'approved_at', 'approval_note'])]
class TournamentFinanceRule extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => FinanceRulesStatus::class,
            'prize_pool_bp' => 'integer',
            'organizer_share_bp' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function isApproved(): bool
    {
        return $this->status === FinanceRulesStatus::Approved;
    }

    public function isComplete(): bool
    {
        return $this->prize_pool_bp !== null && $this->organizer_share_bp !== null;
    }

    public static function percent(?int $bp): string
    {
        return $bp === null ? '—' : rtrim(rtrim(number_format($bp / 100, 2, '.', ''), '0'), '.').'%';
    }
}
