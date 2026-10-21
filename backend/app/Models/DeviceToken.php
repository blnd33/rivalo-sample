<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone's push token. Never returned by any API. */
#[Fillable(['user_id', 'personal_access_token_id', 'token', 'platform', 'app_version', 'last_seen_at'])]
class DeviceToken extends Model
{
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
