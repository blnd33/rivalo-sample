<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    use EnumHelpers;

    case Reserved = 'reserved';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
    case Withdrawn = 'withdrawn';
    case Rejected = 'rejected';
    case NoShow = 'no_show';

    /** Entries that hold a place (reserved) or a queue position (waitlisted). */
    public function isActive(): bool
    {
        return in_array($this, [self::Reserved, self::Waitlisted], true);
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Reserved->value, self::Waitlisted->value];
    }
}
