<?php

namespace App\Enums;

/** Only published rule sets award points. Published sets are never edited; a new version is made instead. */
enum PointsRuleSetStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';
}
