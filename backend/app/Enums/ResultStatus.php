<?php

namespace App\Enums;

/** Result versions: only one approved per match; corrections supersede. */
enum ResultStatus: string
{
    use EnumHelpers;

    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
