<?php

namespace App\Models;

use App\Enums\StageStatus;
use App\Enums\StageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tournament_id', 'type', 'name', 'sequence', 'status', 'settings', 'created_by_id'])]
class Stage extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['type' => StageType::class, 'status' => StageStatus::class, 'settings' => 'array'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(StageGroup::class)->orderBy('sequence');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('round')->orderBy('position');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    public function displayName(): string
    {
        return __($this->name);
    }

    public function isPublic(): bool
    {
        return $this->status !== StageStatus::Draft;
    }
}
