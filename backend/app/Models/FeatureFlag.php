<?php

namespace App\Models;

use App\Support\FeatureFlags;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key', 'enabled', 'description', 'is_public', 'updated_by_id', 'last_change_reason'])]
class FeatureFlag extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'is_public' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FeatureFlags::flush());
        static::deleted(fn () => FeatureFlags::flush());
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
