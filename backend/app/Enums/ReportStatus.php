<?php

namespace App\Enums;

enum ReportStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Actioned = 'actioned';
    case Dismissed = 'dismissed';
}
