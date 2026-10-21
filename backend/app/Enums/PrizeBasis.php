<?php

namespace App\Enums;

/** Fixed amount, or a share of the prize pool computed from approved finance rules. */
enum PrizeBasis: string
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case PoolShare = 'pool_share';
}
