<?php

namespace App\Models;

use App\Enums\PointsTrigger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['points_rule_set_id', 'trigger', 'points', 'eligible_outcomes'])]
class PointsRule extends Model
{
    protected function casts(): array
    {
        return ['trigger' => PointsTrigger::class, 'points' => 'integer', 'eligible_outcomes' => 'array'];
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(PointsRuleSet::class, 'points_rule_set_id');
    }

    /** Match triggers count only the listed outcomes (default: normal results only). */
    public function appliesToOutcome(string $outcome): bool
    {
        return in_array($outcome, $this->eligible_outcomes ?: ['normal'], true);
    }
}
