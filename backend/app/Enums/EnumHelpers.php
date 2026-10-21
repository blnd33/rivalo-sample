<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Shared helpers for string-backed enums.
 */
trait EnumHelpers
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Human label, e.g. "registration_open" -> "Registration open" (translated: lang/ar.json). */
    public function label(): string
    {
        return __(Str::ucfirst(str_replace('_', ' ', $this->value)));
    }

    /** @return array<string, string> value => label, for admin selects. */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
