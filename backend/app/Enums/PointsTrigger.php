<?php

namespace App\Enums;

use Illuminate\Support\Str;

/** What earns ranking points. */
enum PointsTrigger: string
{
    use EnumHelpers {
        label as private defaultLabel;
    }

    case Participation = 'participation';
    case MatchWin = 'match_win';
    case MatchDraw = 'match_draw';
    case MatchLoss = 'match_loss';
    case Place1 = 'place_1';
    case Place2 = 'place_2';
    case Place3 = 'place_3';

    public function isMatchTrigger(): bool
    {
        return in_array($this, [self::MatchWin, self::MatchDraw, self::MatchLoss], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Place1 => __('1st place'),
            self::Place2 => __('2nd place'),
            self::Place3 => __('3rd place'),
            default => __(Str::ucfirst(str_replace('_', ' ', $this->value))),
        };
    }
}
