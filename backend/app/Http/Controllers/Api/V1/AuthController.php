<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_.]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'display_name' => ['nullable', 'string', 'max:60'],
            'locale' => ['nullable', Rule::in(User::SUPPORTED_LOCALES)],
            'device_name' => ['required', 'string', 'max:80'],
        ]);

        // Unique checks above are case-sensitive in some databases; normalize and re-check.
        $username = mb_strtolower($data['username']);
        $email = mb_strtolower($data['email']);
        if (User::where('username', $username)->exists()) {
            throw ValidationException::withMessages(['username' => __('validation.unique', ['attribute' => 'username'])]);
        }
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => $data['password'],
            'display_name' => $data['display_name'] ?? null,
            'locale' => $data['locale'] ?? 'en',
        ]);
        $user->assignRole('player');

        event(new Registered($user)); // Sends the verification email.

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => MeResource::make($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
        ]);

        $login = mb_strtolower(trim($data['login']));
        $user = User::where(str_contains($login, '@') ? 'email' : 'username', $login)->first();

        // Same message AND the same work (one hash check) for an unknown account and a wrong
        // password, so neither the answer nor its timing reveals which accounts exist.
        $hash = $user?->password ?? Cache::rememberForever('auth:timing-dummy-hash', fn () => Hash::make(Str::random(40)));
        $valid = Hash::check($data['password'], $hash);
        if (! $user || ! $valid) {
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        if (in_array($user->status, [UserStatus::Suspended, UserStatus::Deleted], true)) {
            throw ValidationException::withMessages(['login' => __('This account is not available. Contact support if you think this is a mistake.')]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($data['device_name'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => MeResource::make($user),
        ]);
    }

    /** Revoke only the token used for this request ("sign out of this device"). */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /** Revoke every token ("sign out of all devices"). */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->tokens()->count();
        $user->tokens()->delete();
        Audit::record('account.signed_out_everywhere', $user, new: ['tokens_revoked' => $count], actorId: $user->id);

        return response()->json(['message' => 'Signed out of all devices.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Always the same response, whether or not the account exists.
        Password::sendResetLink(['email' => mb_strtolower($data['email'])]);

        return response()->json(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
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
                $user->tokens()->delete(); // Sign out everywhere after a reset.
                event(new PasswordReset($user));
                Audit::record('account.password_reset', $user, actorId: $user->id);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
