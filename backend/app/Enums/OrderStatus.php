<?php

namespace App\Enums;

/** Order lifecycle. Stock is reserved at "placed" and returned on "cancelled". */
enum OrderStatus: string
{
    use EnumHelpers;

    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** The customer may still cancel. */
    public function customerCancellable(): bool
    {
        return in_array($this, [self::Placed, self::Confirmed], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
