<?php

namespace App\Enums;

enum PaymentStatus: string
{
    use EnumHelpers;

    case NotRequired = 'not_required';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
