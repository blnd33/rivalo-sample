<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a user's in-app inbox. Text is rendered in the user's language when sent. */
#[Fillable([
    'user_id', 'type', 'category', 'title', 'body', 'link_kind', 'link_ref', 'data', 'dedupe_key',
    'read_at', 'push_status', 'pushed_at', 'announcement_id',
])]
class UserNotification extends Model
{
    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime', 'pushed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
