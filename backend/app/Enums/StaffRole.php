<?php

namespace App\Enums;

enum StaffRole: string
{
    use EnumHelpers;

    case Organizer = 'organizer';
    case Referee = 'referee';
}
