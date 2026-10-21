<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\TournamentStatus;
use App\Filament\Resources\Tournaments\TournamentResource;
use App\Support\Locales;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Tests\TestCase;

/**
 * App: English, Arabic, Kurdish (Kurmanji). Admin panel: English and Arabic only.
 */
class LocalizationTest extends TestCase
{
    public function test_accept_language_maps_to_supported_app_locales(): void
    {
        $this->assertSame('ar', Locales::fromAcceptLanguage('ar-IQ,ar;q=0.9'));
        $this->assertSame('kmr', Locales::fromAcceptLanguage('kmr'));
        $this->assertSame('kmr', Locales::fromAcceptLanguage('ku-Latn-IQ'));
        $this->assertSame('en', Locales::fromAcceptLanguage('fr-FR,de'));
        $this->assertSame('en', Locales::fromAcceptLanguage(null));
    }

    public function test_api_business_rule_messages_follow_the_app_language(): void
    {
        $tournament = $this->tournament(status: TournamentStatus::Published);
        $player = $this->player();

        $this->actingAs($player, 'sanctum')
            ->withHeader('Accept-Language', 'ar')
            ->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertStatus(409)
            ->assertJsonPath('code', 'registration_closed')
            ->assertJsonPath('message', 'التسجيل غير مفتوح لهذه البطولة.');

        // Kurmanji (Milestone 7): same code, Kurdish text.
        $this->actingAs($player, 'sanctum')
            ->withHeader('Accept-Language', 'kmr')
            ->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")
            ->assertJsonPath('code', 'registration_closed')
            ->assertJsonPath('message', 'Tomarbûn ji bo vê tûrnûvayê ne vekirî ye.');
    }

    public function test_messages_with_placeholders_are_translated(): void
    {
        $team = $this->teamWithMembers('padel', 3); // padel limit is 4, so the team is full
        $this->player(['username' => 'one_more']);

        $this->actingAs($team->captain, 'sanctum')
            ->withHeader('Accept-Language', 'ar')
            ->postJson("/api/v1/teams/{$team->slug}/invitations", ['username' => 'one_more'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'يمكن أن يضم الفريق في هذه الرياضة 4 أعضاء كحد أقصى.');
    }

    public function test_validation_errors_are_arabic_for_arabic_clients(): void
    {
        $this->withHeader('Accept-Language', 'ar')
            ->postJson('/api/v1/auth/login', ['device_name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.login.0', 'حقل البريد الإلكتروني أو اسم المستخدم مطلوب.');
    }

    public function test_players_can_choose_kurmanji_for_the_app(): void
    {
        $player = $this->player();

        $this->actingAs($player, 'sanctum')->patchJson('/api/v1/me', ['locale' => 'kmr'])
            ->assertOk()->assertJsonPath('data.locale', 'kmr');

        $this->actingAs($player, 'sanctum')->patchJson('/api/v1/me', ['locale' => 'ckb'])
            ->assertUnprocessable();
    }

    public function test_admin_panel_renders_in_arabic_right_to_left(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin, ['admin_locale' => 'ar']);
        $admin->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        $this->actingAs($admin)->get(TournamentResource::getUrl('index'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('البطولات')
            ->assertSee('المنافسات')
            ->assertSee('English'); // switch back to English in the user menu
    }

    public function test_admin_panel_defaults_to_english(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);
        $admin->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        $this->actingAs($admin)->get(TournamentResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Tournaments')
            ->assertSee('العربية')
            ->assertDontSee('dir="rtl"', false);
    }

    public function test_admin_language_switch_supports_english_and_arabic_only(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);

        $this->actingAs($admin)->get('/admin-language/ar')->assertRedirect();
        $this->assertSame('ar', $admin->fresh()->admin_locale);

        $this->actingAs($admin)->get('/admin-language/kmr')->assertNotFound();
        $this->assertSame('ar', $admin->fresh()->admin_locale);

        // The admin choice does not change the player's app language.
        $this->assertSame('en', $admin->fresh()->locale);
    }
}
