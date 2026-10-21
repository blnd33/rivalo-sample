<?php

namespace App\Enums;

/** Draft stages are visible to staff only. */
enum StageStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
}
