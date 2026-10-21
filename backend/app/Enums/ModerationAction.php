<?php

namespace App\Enums;

/** What staff did about a report. Each one is audited with the staff member's reason. */
enum ModerationAction: string
{
    use EnumHelpers;

    case RemovePhoto = 'remove_photo';           // player avatar or team logo
    case ResetDisplayName = 'reset_display_name'; // the username is shown instead
    case RenameTeam = 'rename_team';
    case Warn = 'warn';                           // inbox message to the player (team: the captain)
    case Suspend = 'suspend';                     // blocks sign-in and ends every app session
    case Resolved = 'resolved';                   // app problems and feedback
}
