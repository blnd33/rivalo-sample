<?php

namespace App\Enums;

enum SportKind: string
{
    use EnumHelpers;

    case Physical = 'physical';
    case Esport = 'esport';
}
