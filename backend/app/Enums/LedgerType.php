<?php

namespace App\Enums;

/** Tournament ledger row types. Payouts and expenses record actual cash handed over by staff. */
enum LedgerType: string
{
    use EnumHelpers;

    case EntryRevenue = 'entry_revenue';
    case Refund = 'refund';
    case SponsorCash = 'sponsor_cash';
    case VenueExpense = 'venue_expense';
    case RefereeExpense = 'referee_expense';
    case OtherExpense = 'other_expense';
    case Allocation = 'allocation';
    case OrganizerPayout = 'organizer_payout';
    case PrizePayout = 'prize_payout';
    case Reversal = 'reversal';

    /** @return list<self> */
    public static function expenses(): array
    {
        return [self::VenueExpense, self::RefereeExpense, self::OtherExpense];
    }

    /** Rows created automatically from venue receipts; corrected by voiding the receipt instead. */
    public function isAutomatic(): bool
    {
        return in_array($this, [self::EntryRevenue, self::Refund], true);
    }
}
