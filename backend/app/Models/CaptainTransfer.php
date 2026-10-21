<?php

namespace App\Models;

use App\Enums\CaptainTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'from_user_id', 'to_user_id', 'initiated_by_id', 'status', 'reason', 'responded_at'])]
class CaptainTransfer extends Model
{
    protected function casts(): array
    {
        return ['status' => CaptainTransferStatus::class, 'responded_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
