<?php

use App\Exceptions\DomainRuleException;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\OptionalSanctumAuth;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API messages follow the app language (Accept-Language: en | ar | kmr).
        $middleware->api(prepend: [SetApiLocale::class]);

        // nosniff, frame denial, referrer policy, HSTS over HTTPS, no-store for signed-in API calls.
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'auth.optional' => OptionalSanctumAuth::class,
            'account.active' => EnsureAccountActive::class,
        ]);

        // Trust the reverse proxy / load balancer in staging and production (set TRUSTED_PROXIES).
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (DomainRuleException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->status);
            }
        });

        // Business-rule refusals are expected outcomes, not errors worth reporting.
        $exceptions->dontReport([DomainRuleException::class]);

        // Never write these request fields to logs or error reports.
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token']);
    })->create();
