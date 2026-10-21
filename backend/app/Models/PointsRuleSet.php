<?php

namespace App\Models;

use App\Enums\PointsRuleSetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Points table for a discipline (and optionally one season). Published sets are immutable. */
#[Fillable(['name', 'discipline_id', 'season_id', 'version', 'status', 'notes', 'published_by_id', 'published_at', 'publish_note'])]
class PointsRuleSet extends Model
{
    protected $attributes = ['status' => 'draft', 'version' => 1];

    protected function casts(): array
    {
        return ['status' => PointsRuleSetStatus::class, 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (PointsRuleSet $set) {
            $original = $set->getOriginal('status');
            $original = $original instanceof PointsRuleSetStatus ? $original : PointsRuleSetStatus::tryFrom((string) $original);
            // Only the status may move on (published → retired). The table itself is frozen.
            if ($original !== PointsRuleSetStatus::Draft && array_diff(array_keys($set->getDirty()), ['status', 'updated_at'])) {
                throw new LogicException('Published points rules cannot be edited. Create a new version instead.');
            }
        });
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PointsRule::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    public function isPublished(): bool
    {
        return $this->status === PointsRuleSetStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === PointsRuleSetStatus::Draft;
    }

    public function displayName(): string
    {
        return $this->name.' · v'.$this->version;
    }
}
