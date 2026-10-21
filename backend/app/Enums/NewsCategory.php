<?php

namespace App\Enums;

enum NewsCategory: string
{
    use EnumHelpers;

    case Announcement = 'announcement';
    case Tournament = 'tournament';
    case Results = 'results';
    case Community = 'community';
    case Guide = 'guide';
}
