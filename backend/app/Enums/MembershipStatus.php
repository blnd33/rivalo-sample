<?php

namespace App\Enums;

enum MembershipStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Left = 'left';
    case Removed = 'removed';
}
