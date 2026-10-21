<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers for the admin panel and API (Milestone 7 security check).
 * Static files under /storage are served by the web server; configure the same headers there
 * (docs/07). A Content-Security-Policy is not set yet: Filament and Livewire rely on inline
 * scripts and styles, so a policy needs nonces and testing first (docs/08-SECURITY.md).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        foreach ($defaults as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Signed-in API responses contain personal data: never store them in shared caches.
        if ($request->is('api/*') && $request->bearerToken()) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
