<?php

namespace App\Enums;

enum CompetitionMode: string
{
    use EnumHelpers;

    case Local = 'local';
    case Online = 'online';
    case Lan = 'lan';
}
