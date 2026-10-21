<?php

namespace App\Enums;

enum FundingModel: string
{
    use EnumHelpers;

    case Free = 'free';
    case PlayerFunded = 'player_funded';
    case SponsorSupported = 'sponsor_supported';
}
