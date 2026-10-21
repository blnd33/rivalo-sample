<?php

namespace Tests\Feature\Profile;

use App\Enums\UserStatus;
use App\Services\AccountService;
use App\Services\RegistrationService;
use Tests\TestCase;

class ProfilePrivacyTest extends TestCase
{
    private const PRIVATE_KEYS = [
        'email', 'phone', 'privacy', 'notification_preferences', 'status', 'password',
        'suspension_reason', 'app_authentication_secret', 'email_verified', 'is_staff', 'id',
    ];

    public function test_public_profile_never_exposes_private_fields(): void
    {
        $user = $this->player([
            'username' => 'open_book',
            'email' => 'secret@example.com',
            'phone' => '+964 770 123 4567',
            'suspension_reason' => 'internal moderation note',
        ]);

        $response = $this->getJson('/api/v1/users/open_book')->assertOk();
        $data = $response->json('data');

        foreach (self::PRIVATE_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $data, "Public profile leaked [{$key}].");
        }
        $body = $response->getContent();
        $this->assertStringNotContainsString('secret@example.com', $body);
        $this->assertStringNotContainsString('770 123 4567', $body);
        $this->assertStringNotContainsString('internal moderation note', $body);
    }

    public function test_privacy_settings_control_public_display_name_and_city(): void
    {
        $user = $this->player(['username' => 'quiet_one', 'display_name' => 'Real Name', 'city' => 'Erbil']);

        // Defaults: display name shown, city hidden.
        $this->getJson('/api/v1/users/quiet_one')
            ->assertJsonPath('data.display_name', 'Real Name')
            ->assertJsonPath('data.city', null);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me/privacy', ['show_display_name' => false, 'show_city' => true, 'show_teams' => false])
            ->assertOk();

        $this->getJson('/api/v1/users/quiet_one')
            ->assertJsonPath('data.display_name', null)
            ->assertJsonPath('data.city', 'Erbil')
            ->assertJsonPath('data.teams', null);
    }

    public function test_owner_sees_private_fields_on_me_endpoint(): void
    {
        $user = $this->player(['email' => 'me@example.com', 'phone' => '+964 750 111 2222']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'me@example.com')
            ->assertJsonPath('data.phone', '+964 750 111 2222');
    }

    public function test_deleted_and_suspended_profiles_are_not_public(): void
    {
        $this->player(['username' => 'gone_user', 'status' => UserStatus::Deleted]);
        $this->player(['username' => 'paused_user', 'status' => UserStatus::Suspended]);

        $this->getJson('/api/v1/users/gone_user')->assertNotFound();
        $this->getJson('/api/v1/users/paused_user')->assertNotFound();
    }

    public function test_selecting_inactive_or_unknown_disciplines_is_rejected(): void
    {
        $user = $this->player();
        $active = $this->discipline('9-ball');
        $inactive = $this->discipline('american-pool');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/me/disciplines', ['discipline_ids' => [$active->id]])
            ->assertOk()->assertJsonPath('data.discipline_ids', [$active->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/me/disciplines', ['discipline_ids' => [$active->id, $inactive->id]])
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_discipline');
    }

    public function test_account_deletion_requires_password_has_grace_period_and_can_be_cancelled(): void
    {
        $user = $this->player();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/deletion-request', ['password' => 'wrong-password-1'])
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_password');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/deletion-request', ['password' => 'password-123'])
            ->assertAccepted();
        $this->assertSame(UserStatus::PendingDeletion, $user->fresh()->status);

        $this->actingAs($user->fresh(), 'sanctum')->deleteJson('/api/v1/me/deletion-request')->assertOk();
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_due_deletions_anonymize_personal_data_but_keep_history(): void
    {
        $user = $this->player(['username' => 'leaving_soon', 'email' => 'leave@example.com', 'phone' => '+964 1']);
        $tournament = $this->tournament();
        app(RegistrationService::class)->register($tournament, $user);

        app(AccountService::class)->requestDeletion($user, 'password-123');

        $this->travel(AccountService::DELETION_GRACE_DAYS + 1)->days();
        app(AccountService::class)->processDueDeletions();

        $user->refresh();
        $this->assertSame(UserStatus::Deleted, $user->status);
        $this->assertSame('deleted_'.$user->id, $user->username);
        $this->assertNull($user->phone);
        $this->assertNull($user->display_name);
        $this->assertStringNotContainsString('leave@example.com', $user->email);
        $this->assertSame(0, $user->tokens()->count());

        // History row still exists, but shows no personal name.
        $snapshot = $user->entryMemberships()->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('Deleted player', $snapshot->display_name_snapshot);
    }

    public function test_captain_of_shared_team_must_transfer_before_deleting(): void
    {
        $team = $this->teamWithMembers('padel', 1);

        $this->actingAs($team->captain, 'sanctum')
            ->postJson('/api/v1/me/deletion-request', ['password' => 'password-123'])
            ->assertStatus(409)->assertJsonPath('code', 'captain_transfer_required');
    }
}
