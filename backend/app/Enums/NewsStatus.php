<?php

namespace App\Enums;

enum NewsStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
