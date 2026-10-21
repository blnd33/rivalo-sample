<?php

namespace App\Enums;

enum ThirdPlaceMethod: string
{
    use EnumHelpers;

    case PlayoffMatch = 'playoff_match';
    case SharedThird = 'shared_third';
    case NotAwarded = 'not_awarded';
    case PublishedOther = 'published_other';
}
