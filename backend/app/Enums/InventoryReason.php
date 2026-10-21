<?php

namespace App\Enums;

enum InventoryReason: string
{
    use EnumHelpers;

    case Restock = 'restock';
    case Adjustment = 'adjustment';
    case Order = 'order';
    case OrderCancelled = 'order_cancelled';
    case Return = 'return';
    // Milestone 9: unsold seller stock handed back to the seller.
    case ReturnedToSeller = 'returned_to_seller';
}
