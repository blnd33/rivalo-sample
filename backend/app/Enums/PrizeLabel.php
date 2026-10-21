<?php

namespace App\Enums;

/** Public certainty label. Guaranteed requires received sponsor cash backing it. */
enum PrizeLabel: string
{
    use EnumHelpers;

    case Estimated = 'estimated';
    case Finalized = 'finalized';
    case Guaranteed = 'guaranteed';
}
