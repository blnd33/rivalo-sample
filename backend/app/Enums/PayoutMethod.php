<?php

namespace App\Enums;

/** How a payout was actually made. Recorded by staff; RIVALO never transfers money automatically. */
enum PayoutMethod: string
{
    use EnumHelpers;

    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';
}
