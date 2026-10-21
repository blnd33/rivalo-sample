<?php

namespace App\Enums;

enum EligibilityStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
}
