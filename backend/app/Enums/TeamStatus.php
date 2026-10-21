<?php

namespace App\Enums;

enum TeamStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Disbanded = 'disbanded';
}
