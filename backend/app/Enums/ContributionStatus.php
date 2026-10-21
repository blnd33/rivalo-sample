<?php

namespace App\Enums;

/** Pledge lifecycle; "received" is derived from ledger receipts. */
enum ContributionStatus: string
{
    use EnumHelpers;

    case Pledged = 'pledged';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';
}
