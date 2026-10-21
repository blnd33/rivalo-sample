<?php

namespace App\Enums;

enum RulesetStatus: string
{
    use EnumHelpers;

    case Confirmed = 'confirmed';
    case NeedsConfirmation = 'needs_confirmation';
}
