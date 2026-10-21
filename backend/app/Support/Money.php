<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer minor-unit money helpers. Floats are never used for money.
 */
final class Money
{
    /** ISO-4217 minor-unit exponents for currencies we expect to use. Extend deliberately. */
    private const EXPONENTS = ['IQD' => 0, 'USD' => 2, 'EUR' => 2];

    public static function exponent(string $currency): int
    {
        $currency = strtoupper($currency);
        if (! array_key_exists($currency, self::EXPONENTS)) {
            throw new InvalidArgumentException("Unsupported currency [{$currency}].");
        }

        return self::EXPONENTS[$currency];
    }

    /** @return list<string> */
    public static function supportedCurrencies(): array
    {
        return array_keys(self::EXPONENTS);
    }

    /** Format minor units for display, e.g. (25000, IQD) -> "25,000 IQD"; (1050, USD) -> "10.50 USD". */
    public static function format(int $minor, string $currency): string
    {
        $exp = self::exponent($currency);
        $negative = $minor < 0;
        $abs = abs($minor);
        $major = intdiv($abs, 10 ** $exp);
        $fraction = $exp > 0 ? '.'.str_pad((string) ($abs % (10 ** $exp)), $exp, '0', STR_PAD_LEFT) : '';

        return ($negative ? '-' : '').number_format($major).$fraction.' '.strtoupper($currency);
    }
}
