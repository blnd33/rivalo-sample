<?php

namespace App\Enums;

/** How a stage is played. */
enum StageType: string
{
    use EnumHelpers;

    case SingleElimination = 'single_elimination';
    case RoundRobin = 'round_robin';
    case Groups = 'groups';
    case Knockout = 'knockout';

    /** Stages decided by a table (draws allowed) rather than a bracket. */
    public function isTable(): bool
    {
        return in_array($this, [self::RoundRobin, self::Groups], true);
    }
}
