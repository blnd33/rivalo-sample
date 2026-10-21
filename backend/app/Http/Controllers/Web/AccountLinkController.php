<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Small browser pages for email links (verification, password reset).
 */
class AccountLinkController extends Controller
{
    public function verifyEmail(Request $request, int $id, string $hash): View
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'This verification link is not valid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return view('account.message', [
            'title' => 'Email verified',
            'message' => 'Your email address is confirmed. Return to the RIVALO app and pull to refresh.',
            'status' => 'success',
        ]);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('account.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): View
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => mb_strtolower($data['email'])] + $data,
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
                Audit::record('account.password_reset', $user, actorId: $user->id);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return view('account.reset-password', [
                'token' => $data['token'],
                'email' => $data['email'],
            ])->withErrors(['email' => __($status)]);
        }

        return view('account.message', [
            'title' => 'Password updated',
            'message' => 'Your password has been changed and all devices were signed out. Sign in again in the RIVALO app.',
            'status' => 'success',
        ]);
    }
}
