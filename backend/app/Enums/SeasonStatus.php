<?php

namespace App\Enums;

enum SeasonStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
}
