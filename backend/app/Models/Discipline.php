<?php

namespace App\Models;

use App\Enums\CompetitionMode;
use App\Enums\RegistrationType;
use App\Enums\RulesetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sport_id', 'slug', 'name', 'name_translations', 'description', 'registration_types',
    'team_size_min', 'team_size_max', 'substitutes_max', 'game_title', 'game_version',
    'platform', 'region', 'match_format', 'competition_mode', 'ruleset_status',
    'ruleset_notes', 'sort_order', 'is_active',
])]
class Discipline extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'registration_types' => 'array',
            'competition_mode' => CompetitionMode::class,
            'ruleset_status' => RulesetStatus::class,
            'is_active' => 'boolean',
            'team_size_min' => 'integer',
            'team_size_max' => 'integer',
            'substitutes_max' => 'integer',
        ];
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->whereHas('sport', fn ($q) => $q->where('is_active', true));
    }

    public function allowsRegistrationType(RegistrationType $type): bool
    {
        return in_array($type->value, $this->registration_types ?? [], true);
    }

    /** A discipline may be used for live tournaments only when it is active and its ruleset is confirmed. */
    public function isUsableForTournaments(): bool
    {
        return $this->is_active
            && $this->ruleset_status === RulesetStatus::Confirmed
            && (bool) $this->sport?->is_active;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->name_translations[$locale] ?? $this->name;
    }
}
