<?php

namespace App\Enums;

enum CaptainTransferStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Forced = 'forced';
}
