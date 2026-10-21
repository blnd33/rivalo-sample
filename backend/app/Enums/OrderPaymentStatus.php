<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    use EnumHelpers;

    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
