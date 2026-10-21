<?php

namespace App\Enums;

/** Pending: waiting for participants. Ready: both known. Bye: one side advanced without playing. */
enum MatchStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Bye = 'bye';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Bye], true);
    }
}
