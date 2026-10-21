<?php

namespace App\Support;

/**
 * Supported languages.
 *  - App (mobile): English, Arabic (RTL), Kurdish Kurmanji in Latin script (LTR, code "kmr").
 *  - Admin panel: English and Arabic only.
 */
final class Locales
{
    /** @var list<string> */
    public const APP = ['en', 'ar', 'kmr'];

    /** @var list<string> */
    public const ADMIN = ['en', 'ar'];

    /** @var array<string, string> Language names shown in their own language. */
    public const NATIVE_NAMES = ['en' => 'English', 'ar' => 'العربية', 'kmr' => 'Kurdî (Kurmancî)'];

    /** Pick the best supported app locale from an Accept-Language header value. */
    public static function fromAcceptLanguage(?string $header): string
    {
        foreach (explode(',', (string) $header) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));
            if ($tag === '') {
                continue;
            }
            // "kmr", "ku", "ku-latn" all map to Kurmanji; "ar-iq" maps to Arabic, and so on.
            $primary = explode('-', str_replace('_', '-', $tag))[0];
            if ($primary === 'ku') {
                $primary = 'kmr';
            }
            if (in_array($primary, self::APP, true)) {
                return $primary;
            }
        }

        return 'en';
    }
}
