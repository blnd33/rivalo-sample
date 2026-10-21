<?php

namespace App\Enums;

/** How a match ended. Every outcome except cancelled has a winner or a draw. */
enum MatchOutcome: string
{
    use EnumHelpers;

    case Normal = 'normal';
    case Walkover = 'walkover';
    case Forfeit = 'forfeit';
    case NoShow = 'no_show';
    case Disqualification = 'disqualification';
    case Cancelled = 'cancelled';
}
