<?php

namespace App\Enums;

/** How the customer gets the order. Payment is cash at handover in both cases. */
enum Fulfillment: string
{
    use EnumHelpers;

    case Pickup = 'pickup';
    case Delivery = 'delivery';

    public function paymentMethod(): string
    {
        return $this === self::Pickup ? 'cash_on_pickup' : 'cash_on_delivery';
    }
}
