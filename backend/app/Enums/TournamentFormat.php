<?php

namespace App\Enums;

enum TournamentFormat: string
{
    use EnumHelpers;

    case SingleElimination = 'single_elimination';
    case RoundRobin = 'round_robin';
    case GroupsKnockout = 'groups_knockout';
}
