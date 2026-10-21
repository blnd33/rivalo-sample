<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    use EnumHelpers;

    case Everyone = 'everyone';
    case Discipline = 'discipline';
    case TournamentEntrants = 'tournament_entrants';
}
