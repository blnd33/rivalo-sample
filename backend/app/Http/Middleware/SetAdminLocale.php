<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel language (English or Arabic; Arabic switches Filament to right-to-left).
 *
 * The latest explicit choice wins: a language picked with the switch (on the sign-in page or
 * in the user menu) is kept in the session and saved to the staff member's account, so it
 * also applies on their next device. A fresh session uses the account's saved choice.
 * Registered as persistent panel middleware so Livewire requests use it too.
 */
class SetAdminLocale
{
    public const SESSION_KEY = 'admin_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $chosen = $request->session()->get(self::SESSION_KEY);

        if ($chosen && $user && $user->admin_locale !== $chosen && in_array($chosen, Locales::ADMIN, true)) {
            $user->forceFill(['admin_locale' => $chosen])->save();
        }

        $locale = $chosen ?? $user?->admin_locale ?? 'en';

        app()->setLocale(in_array($locale, Locales::ADMIN, true) ? $locale : 'en');

        return $next($request);
    }
}
