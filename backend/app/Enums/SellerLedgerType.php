<?php

namespace App\Enums;

/**
 * Seller statement lines. Sale: a completed, paid order line (item value minus commission).
 * Return: a returned item after the sale was counted. Adjustment: a correction staff record with
 * a reason. Payout: money RIVALO actually paid the seller.
 */
enum SellerLedgerType: string
{
    use EnumHelpers;

    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Payout = 'payout';
}
