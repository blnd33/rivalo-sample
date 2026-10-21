<?php

namespace App\Enums;

/** Draft rules only produce labelled projections; approved rules unlock formula-based payouts. */
enum FinanceRulesStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Approved = 'approved';
}
