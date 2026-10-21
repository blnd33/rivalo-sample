<?php

namespace App\Enums;

enum CheckInStatus: string
{
    use EnumHelpers;

    case NotCheckedIn = 'not_checked_in';
    case CheckedIn = 'checked_in';
}
