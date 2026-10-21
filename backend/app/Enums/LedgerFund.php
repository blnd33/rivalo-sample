<?php

namespace App\Enums;

/** Where money sits. Restricted funds can only be spent on their purpose. */
enum LedgerFund: string
{
    use EnumHelpers;

    case Unrestricted = 'unrestricted';
    case Prize = 'prize';
    case Expense = 'expense';
    case Organizer = 'organizer';
}
