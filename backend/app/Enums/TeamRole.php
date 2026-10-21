<?php

namespace App\Enums;

enum TeamRole: string
{
    use EnumHelpers;

    case Captain = 'captain';
    case Member = 'member';
    case Substitute = 'substitute';
}
