<?php

namespace App\Enums;

enum EntryMemberRole: string
{
    use EnumHelpers;

    case Captain = 'captain';
    case Player = 'player';
    case Substitute = 'substitute';
}
