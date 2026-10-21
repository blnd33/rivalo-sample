<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks suspended and deleted accounts even if they still hold a token.
 * Accounts pending deletion stay usable so the owner can cancel the request.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->status, [UserStatus::Suspended, UserStatus::Deleted], true)) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => __('This account is not available. Contact support if you think this is a mistake.'),
                'code' => 'account_unavailable',
            ], 403);
        }

        return $next($request);
    }
}
