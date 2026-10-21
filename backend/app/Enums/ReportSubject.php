<?php

namespace App\Enums;

enum ReportSubject: string
{
    use EnumHelpers;

    case User = 'user';
    case Team = 'team';
    case App = 'app'; // a problem with the app, or feedback

    /** @return list<ReportReason> */
    public function reasons(): array
    {
        return match ($this) {
            self::User => [ReportReason::OffensiveName, ReportReason::OffensivePhoto, ReportReason::Harassment, ReportReason::Cheating, ReportReason::Impersonation, ReportReason::Spam, ReportReason::Other],
            self::Team => [ReportReason::OffensiveName, ReportReason::OffensivePhoto, ReportReason::Cheating, ReportReason::Impersonation, ReportReason::Spam, ReportReason::Other],
            self::App => [ReportReason::AppProblem, ReportReason::Suggestion],
        };
    }

    /** @return list<ModerationAction> actions staff can take for this kind of report */
    public function actions(): array
    {
        return match ($this) {
            self::User => [ModerationAction::RemovePhoto, ModerationAction::ResetDisplayName, ModerationAction::Warn, ModerationAction::Suspend],
            self::Team => [ModerationAction::RemovePhoto, ModerationAction::RenameTeam, ModerationAction::Warn],
            self::App => [ModerationAction::Resolved],
        };
    }
}
