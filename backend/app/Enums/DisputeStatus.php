<?php

namespace App\Enums;

/** Result disputes raised by participants. */
enum DisputeStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Upheld = 'upheld';
    case Rejected = 'rejected';
}
