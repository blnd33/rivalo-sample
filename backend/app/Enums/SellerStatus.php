<?php

namespace App\Enums;

/** Suspended and closed sellers' products are not sold. Their statements stay readable. */
enum SellerStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
