<?php

namespace App\Enums;

enum RegistrationType: string
{
    use EnumHelpers;

    case Individual = 'individual';
    case Pair = 'pair';
    case Team = 'team';
}
