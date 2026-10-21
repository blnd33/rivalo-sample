<?php

namespace App\Enums;

/** How sponsor cash may be used. Prize-only money can never fund the organizer. */
enum ContributionRestriction: string
{
    use EnumHelpers;

    case Unrestricted = 'unrestricted';
    case PrizeOnly = 'prize_only';
    case ExpensesOnly = 'expenses_only';

    public function fund(): LedgerFund
    {
        return match ($this) {
            self::Unrestricted => LedgerFund::Unrestricted,
            self::PrizeOnly => LedgerFund::Prize,
            self::ExpensesOnly => LedgerFund::Expense,
        };
    }
}
