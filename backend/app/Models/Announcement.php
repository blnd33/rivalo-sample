<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title', 'body', 'category', 'audience', 'audience_ref', 'locale', 'link_kind', 'link_ref', 'send_push',
    'status', 'send_at', 'sent_at', 'recipients_count', 'created_by_id', 'sent_by_id',
])]
class Announcement extends Model
{
    protected $attributes = ['status' => 'draft', 'audience' => 'everyone', 'category' => 'news', 'send_push' => true, 'is_sample_data' => false];

    protected function casts(): array
    {
        return [
            'status' => AnnouncementStatus::class,
            'audience' => AnnouncementAudience::class,
            'send_push' => 'boolean',
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
            'is_sample_data' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [AnnouncementStatus::Draft, AnnouncementStatus::Scheduled], true);
    }
}
