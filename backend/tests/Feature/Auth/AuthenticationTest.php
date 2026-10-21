<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'Rival_One',
            'email' => 'Rival@Example.com',
            'password' => 'strong-pass-123',
            'password_confirmation' => 'strong-pass-123',
            'display_name' => 'Rival One',
            'device_name' => 'Pixel 9',
        ], $overrides);
    }

    public function test_registration_creates_unverified_player_and_sends_verification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload())
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['username', 'email', 'email_verified']])
            ->assertJsonPath('user.username', 'rival_one')
            ->assertJsonPath('user.email', 'rival@example.com')
            ->assertJsonPath('user.email_verified', false);

        $user = User::where('username', 'rival_one')->firstOrFail();
        $this->assertTrue($user->hasRole('player'));
        $this->assertNotEmpty($response->json('token'));
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_username_and_email_are_unique_case_insensitively(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['username' => 'RIVAL_ONE', 'email' => 'other@example.com']))
            ->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['username' => 'someone_else', 'email' => 'RIVAL@example.COM']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_login_by_email_or_username_and_generic_failure_message(): void
    {
        $user = $this->player(['username' => 'padel_pro', 'email' => 'pro@example.com']);

        $this->postJson('/api/v1/auth/login', ['login' => 'PRO@example.com', 'password' => 'password-123', 'device_name' => 'iPhone'])
            ->assertOk()->assertJsonPath('user.username', 'padel_pro');
        $this->postJson('/api/v1/auth/login', ['login' => 'padel_pro', 'password' => 'password-123', 'device_name' => 'iPhone'])
            ->assertOk();

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['login' => 'padel_pro', 'password' => 'nope-nope-1', 'device_name' => 'x'])
            ->assertUnprocessable()->json('errors.login.0');
        $unknownUser = $this->postJson('/api/v1/auth/login', ['login' => 'nobody', 'password' => 'nope-nope-1', 'device_name' => 'x'])
            ->assertUnprocessable()->json('errors.login.0');

        $this->assertSame($wrongPassword, $unknownUser, 'Login errors must not reveal whether an account exists.');
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_suspended_accounts_cannot_sign_in_or_use_existing_tokens(): void
    {
        $user = $this->player(['username' => 'banned_user']);
        $token = $user->createToken('old-device')->plainTextToken;
        $user->forceFill(['status' => 'suspended'])->save();

        $this->postJson('/api/v1/auth/login', ['login' => 'banned_user', 'password' => 'password-123', 'device_name' => 'x'])
            ->assertUnprocessable();

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'account_unavailable');
    }

    public function test_logout_revokes_only_current_token_and_logout_all_revokes_every_token(): void
    {
        $user = $this->player();
        $phone = $user->createToken('phone')->plainTextToken;
        $tablet = $user->createToken('tablet')->plainTextToken;
        $laptop = $user->createToken('laptop')->plainTextToken;

        $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(2, $user->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($tablet)->postJson('/api/v1/auth/logout-all')->assertOk();
        $this->assertSame(0, $user->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($laptop)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_signed_email_verification_link_verifies_without_being_signed_in(): void
    {
        $user = $this->player(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertOk()->assertSee('Email verified');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = $this->player(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->get(str_replace('/'.$user->id.'/', '/'.($user->id + 1).'/', $url))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_forgot_password_response_is_identical_for_unknown_emails(): void
    {
        Notification::fake();
        $user = $this->player(['email' => 'known@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_changes_password_and_signs_out_everywhere(): void
    {
        $user = $this->player(['email' => 'reset@example.com']);
        $user->createToken('device');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'brand-new-pass-9',
            'password_confirmation' => 'brand-new-pass-9',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['login' => 'reset@example.com', 'password' => 'brand-new-pass-9', 'device_name' => 'x'])->assertOk();
    }

    public function test_unverified_users_cannot_create_teams(): void
    {
        $user = $this->player(['email_verified_at' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/teams', ['sport_id' => $this->sport('padel')->id, 'name' => 'Smash'])
            ->assertForbidden();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->player(['username' => 'target']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'target', 'password' => 'wrong-pass-1', 'device_name' => 'x'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['login' => 'target', 'password' => 'wrong-pass-1', 'device_name' => 'x'])->assertTooManyRequests();
    }
}
