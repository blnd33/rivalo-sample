<?php

namespace App\Models;

use App\Enums\AchievementCriteria;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A badge definition. Names and descriptions can be translated per app language. */
#[Fillable(['key', 'name', 'name_translations', 'description', 'description_translations', 'icon', 'criteria_type', 'threshold', 'sort_order', 'status'])]
class Achievement extends Model
{
    protected $attributes = ['status' => 'active', 'threshold' => 1, 'icon' => 'rosette', 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'criteria_type' => AchievementCriteria::class,
            'name_translations' => 'array',
            'description_translations' => 'array',
            'threshold' => 'integer',
        ];
    }

    public function holders(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->name_translations[$locale] ?? $this->name;
    }

    public function localizedDescription(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->description_translations[$locale] ?? $this->description;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
