<?php

namespace App\Enums;

/** What a badge counts. Every automatic badge is based on recorded, approved facts only. */
enum AchievementCriteria: string
{
    use EnumHelpers;

    case MatchesPlayed = 'matches_played';
    case MatchesWon = 'matches_won';
    case TournamentsCompleted = 'tournaments_completed';
    case PodiumFinishes = 'podium_finishes';
    case TournamentWins = 'tournament_wins';
    case CheckIns = 'check_ins';
    case Manual = 'manual';
}
