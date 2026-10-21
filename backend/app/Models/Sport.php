<?php

namespace App\Models;

use App\Enums\SportKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'name_translations', 'kind', 'icon', 'team_member_limit', 'sort_order', 'is_active'])]
class Sport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'kind' => SportKind::class,
            'is_active' => 'boolean',
            'team_member_limit' => 'integer',
        ];
    }

    public function disciplines(): HasMany
    {
        return $this->hasMany(Discipline::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function supportsTeams(): bool
    {
        return $this->team_member_limit !== null && $this->team_member_limit > 0;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->name_translations[$locale] ?? $this->name;
    }
}
