<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API responses (validation and business-rule messages) follow the app's language, sent by
 * the mobile client as Accept-Language. Missing translations fall back to English.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Locales::fromAcceptLanguage($request->header('Accept-Language')));

        return $next($request);
    }
}
