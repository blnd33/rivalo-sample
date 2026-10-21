<?php

namespace App\Enums;

/** Kind of sponsor contribution. */
enum ContributionType: string
{
    use EnumHelpers;

    case Cash = 'cash';
    case InKind = 'in_kind';
    case Venue = 'venue';
}
