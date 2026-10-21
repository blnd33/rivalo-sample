<?php

namespace App\Enums;

enum ReportReason: string
{
    use EnumHelpers;

    case OffensiveName = 'offensive_name';
    case OffensivePhoto = 'offensive_photo';
    case Harassment = 'harassment';
    case Cheating = 'cheating';
    case Impersonation = 'impersonation';
    case Spam = 'spam';
    case Other = 'other';
    case AppProblem = 'app_problem';
    case Suggestion = 'suggestion';
}
