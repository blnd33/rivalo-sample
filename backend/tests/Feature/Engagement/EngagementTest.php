<?php

namespace Tests\Feature\Engagement;

use App\Enums\RoleName;
use App\Enums\StaffRole;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Models\Achievement;
use App\Models\Announcement;
use App\Models\DeviceToken;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserNotification;
use App\Services\AchievementService;
use App\Services\AnnouncementService;
use App\Services\FixtureService;
use App\Services\Notifier;
use App\Services\PointsService;
use App\Services\RegistrationService;
use App\Services\ReminderService;
use App\Services\ResultService;
use App\Services\TeamService;
use App\Services\TournamentService;
use App\Services\VenueService;
use App\Support\Push\FcmPushProvider;
use App\Support\Push\PushProvider;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** Captures phone notifications instead of sending them. */
class FakePush implements PushProvider
{
    public array $sent = [];

    public array $invalid = [];

    public function send(array $tokens, string $title, ?string $body, array $data = []): array
    {
        $this->sent[] = compact('tokens', 'title', 'body', 'data');

        return array_values(array_intersect($tokens, $this->invalid));
    }
}

class EngagementTest extends TestCase
{
    private FakePush $push;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->push = new FakePush;
        $this->app->instance(PushProvider::class, $this->push);
        $this->admin = $this->staff(RoleName::SuperAdmin);
    }

    private function expectRule(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected domain rule [{$code}].");
        } catch (DomainRuleException $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());
        }
    }

    private function withDevice(User $user, string $token = 'device-token-0123456789-abcdef'): User
    {
        DeviceToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android']);

        return $user;
    }

    /** Knockout with $n checked-in players, drawn and published, assigned referee. */
    private function playable(int $n = 2): array
    {
        $organizer = $this->staff(RoleName::TournamentOrganizer);
        $referee = $this->staff(RoleName::Referee);
        $t = $this->tournament(['ranking_eligible' => true, 'max_entrants' => 16], organizer: $organizer);
        app(TournamentService::class)->assignStaff($t, $referee, $this->admin, StaffRole::Referee);
        $players = [];
        for ($i = 0; $i < $n; $i++) {
            $p = $this->player();
            $entry = app(RegistrationService::class)->register($t->fresh(), $p);
            app(VenueService::class)->checkIn($entry, $organizer);
            $players[] = $p;
        }
        $t = app(TournamentService::class)->transition($t->fresh(), $this->admin, TournamentStatus::RegistrationClosed);
        app(FixtureService::class)->generate($t, $organizer, ['draw_seed' => 1]);
        app(FixtureService::class)->publish($t, $organizer);

        return [$t->fresh(), $organizer, $referee, $players];
    }

    private function playFirstReady($t, User $referee, int $s1 = 2, int $s2 = 0): TournamentMatch
    {
        $m = TournamentMatch::where('tournament_id', $t->id)->where('status', 'ready')->orderBy('round')->orderBy('position')->firstOrFail();
        app(ResultService::class)->submit($m, $referee, ['outcome' => 'normal', 'slot1' => $s1, 'slot2' => $s2, 'approve' => true]);

        return $m->fresh();
    }

    // ---------------------------------------------------------------- Inbox

    public function test_team_invitations_land_in_the_inbox_and_can_be_read(): void
    {
        $captain = $this->player();
        $invitee = $this->withDevice($this->player());
        $team = app(TeamService::class)->create($captain, $this->sport('padel'), 'Inbox Team');
        app(TeamService::class)->invite($team, $captain, $invitee);

        $this->actingAs($invitee, 'sanctum')->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.type', 'team_invitation')
            ->assertJsonPath('data.0.title', 'Team invitation')
            ->assertJsonPath('data.0.body', 'Inbox Team invited you to join the team.')
            ->assertJsonPath('data.0.link.kind', 'invitations');
        $this->assertCount(1, $this->push->sent);

        $id = UserNotification::where('user_id', $invitee->id)->value('id');
        // Someone else cannot read or mark it.
        $this->actingAs($captain, 'sanctum')->postJson("/api/v1/me/notifications/{$id}/read")->assertNotFound();
        $this->actingAs($invitee, 'sanctum')->postJson("/api/v1/me/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->actingAs($invitee, 'sanctum')->getJson('/api/v1/me/notifications/unread-count')->assertJsonPath('data.unread', 0);
    }

    public function test_preferences_control_phone_notifications_but_not_the_inbox(): void
    {
        $user = $this->withDevice($this->player(['notification_preferences' => ['team_invitations' => false]]));
        $captain = $this->player();
        $team = app(TeamService::class)->create($captain, $this->sport('padel'), 'Quiet Team');
        app(TeamService::class)->invite($team, $captain, $user);

        $n = UserNotification::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('skipped_preference', $n->push_status);
        $this->assertCount(0, $this->push->sent);
    }

    public function test_nothing_is_sent_when_the_action_rolls_back(): void
    {
        $captain = $this->player();
        $invitee = $this->player();
        $team = app(TeamService::class)->create($captain, $this->sport('padel'), 'Rollback Team');

        try {
            DB::transaction(function () use ($team, $captain, $invitee) {
                app(TeamService::class)->invite($team, $captain, $invitee);
                throw new RuntimeException('later step failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, UserNotification::count());
    }

    public function test_dedupe_keys_and_language_and_invalid_device_tokens(): void
    {
        $user = $this->withDevice($this->player(['locale' => 'ar']), 'stale-token-0123456789-abcdef');
        $this->push->invalid = ['stale-token-0123456789-abcdef'];
        $notifier = app(Notifier::class);

        $notifier->send($user, 'result_approved', ['tournament' => 'Cup', 'score' => 'A 2 – 0 B'], ['match', 1], 'result:1');
        $notifier->send($user, 'result_approved', ['tournament' => 'Cup', 'score' => 'A 2 – 0 B'], ['match', 1], 'result:1');

        $rows = UserNotification::where('user_id', $user->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('تم اعتماد النتيجة', $rows[0]->title);
        $this->assertSame('sent', $rows[0]->fresh()->push_status);
        $this->assertSame(0, DeviceToken::count(), 'A token the provider rejects is removed.');
    }

    public function test_devices_register_against_the_sign_in_and_disappear_on_sign_out(): void
    {
        $user = $this->player();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/me/devices', ['token' => 'fcm-token-0123456789-abcdef', 'platform' => 'ios', 'app_version' => '1.0.0'])->assertCreated();
        $this->assertSame(1, DeviceToken::where('user_id', $user->id)->count());
        $this->assertNotNull(DeviceToken::first()->personal_access_token_id);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    // ---------------------------------------------------------------- Results, badges

    public function test_approved_results_notify_both_sides_and_award_badges(): void
    {
        [$t, , $referee, $players] = $this->playable(2);
        $final = $this->playFirstReady($t, $referee);

        foreach ($players as $p) {
            $this->assertTrue(UserNotification::where('user_id', $p->id)->where('type', 'result_approved')->exists());
            $this->assertTrue(UserNotification::where('user_id', $p->id)->where('type', 'fixtures_published')->exists());
        }
        $winner = User::find($final->winnerEntry->activeMembers()->first()->user_id);
        $keys = UserAchievement::where('user_id', $winner->id)->whereNull('revoked_at')->with('achievement')->get()->pluck('achievement.key')->sort()->values()->all();
        $this->assertSame(['checked_in', 'first_match', 'first_win'], $keys);
        $this->assertTrue(UserNotification::where('user_id', $winner->id)->where('type', 'achievement_unlocked')->exists());

        // Correcting the result withdraws "First win" from the old winner and gives it to the new one.
        app(ResultService::class)->correct($final, $referee, ['outcome' => 'normal', 'slot1' => 0, 'slot2' => 2], 'Wrong way round');
        $firstWin = Achievement::where('key', 'first_win')->first();
        $this->assertNotNull(UserAchievement::where('user_id', $winner->id)->where('achievement_id', $firstWin->id)->value('revoked_at'));
        $newWinner = User::find($final->fresh()->winnerEntry->activeMembers()->first()->user_id);
        $this->assertTrue(UserAchievement::where('user_id', $newWinner->id)->where('achievement_id', $firstWin->id)->whereNull('revoked_at')->exists());

        app(PointsService::class)->finalize($t, $t->organizers()->first());
        $this->assertTrue(UserAchievement::where('user_id', $newWinner->id)->whereHas('achievement', fn ($q) => $q->where('key', 'champion'))->exists());
    }

    public function test_staff_awards_and_revocations_stick(): void
    {
        $editor = $this->staff(RoleName::ContentEditor);
        $user = $this->player();
        $badge = Achievement::where('key', 'first_match')->first();
        $service = app(AchievementService::class);

        $this->expectRule('forbidden', fn () => $service->awardManually($badge, $user, $this->player(), 'x'));
        $row = $service->awardManually($badge, $user, $editor, 'Played in the pilot before results were recorded');
        $this->expectRule('already_held', fn () => $service->awardManually($badge, $user, $editor, 'again'));
        $service->revoke($row, $editor, 'Awarded by mistake');

        // A staff removal is never undone automatically, even if the record qualifies later.
        $service->evaluate($user);
        $this->assertNotNull($row->fresh()->revoked_at);
    }

    public function test_public_profile_badges_follow_the_history_privacy_setting(): void
    {
        $user = $this->player(['username' => 'badge_holder']);
        app(AchievementService::class)->awardManually(Achievement::where('key', 'first_match')->first(), $user, $this->admin, 'Test');

        $this->getJson('/api/v1/users/badge_holder')->assertOk()->assertJsonPath('data.badges.0.key', 'first_match');
        $user->forceFill(['privacy' => ['show_tournament_history' => false]])->save();
        $this->getJson('/api/v1/users/badge_holder')->assertOk()->assertJsonPath('data.badges', null);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/achievements')->assertOk()->assertJsonPath('data.0.key', 'first_match')->assertJsonPath('data.0.earned', true);
    }

    // ---------------------------------------------------------------- Announcements and reminders

    public function test_announcements_reach_the_chosen_audience_and_language(): void
    {
        $editor = $this->staff(RoleName::ContentEditor);
        $followerEn = $this->player(['locale' => 'en']);
        $followerAr = $this->player(['locale' => 'ar']);
        $other = $this->player(['locale' => 'en']);
        $discipline = $this->discipline('9-ball');
        $followerEn->disciplines()->attach($discipline->id);
        $followerAr->disciplines()->attach($discipline->id);
        $service = app(AnnouncementService::class);

        $a = Announcement::create(['title' => '9-ball night moved', 'body' => 'Now at the new hall.', 'audience' => 'discipline', 'audience_ref' => $discipline->id, 'locale' => 'en', 'category' => 'venue_changes']);
        $this->assertSame([$followerEn->id], $service->recipients($a)->pluck('id')->all());

        $this->expectRule('forbidden', fn () => $service->sendNow($a, $this->player()));
        $service->sendNow($a, $editor);

        $this->assertSame('sent', $a->fresh()->status->value);
        $this->assertSame(1, $a->fresh()->recipients_count);
        $this->assertTrue(UserNotification::where('user_id', $followerEn->id)->where('type', 'announcement')->where('title', '9-ball night moved')->exists());
        $this->assertFalse(UserNotification::whereIn('user_id', [$followerAr->id, $other->id])->exists());
        $this->expectRule('already_sent', fn () => $service->sendNow($a->fresh(), $editor));
    }

    public function test_scheduled_announcements_go_out_once_when_due(): void
    {
        $editor = $this->staff(RoleName::ContentEditor);
        $this->player();
        $a = Announcement::create(['title' => 'Season starts', 'body' => 'See you there.', 'audience' => 'everyone', 'category' => 'news']);
        app(AnnouncementService::class)->schedule($a, $editor, now()->addHour());

        $this->assertSame(0, app(AnnouncementService::class)->dispatchDue());
        $this->travel(61)->minutes();
        $this->artisan('rivalo:dispatch-announcements')->assertSuccessful();
        $this->assertSame('sent', $a->fresh()->status->value);
        $this->assertSame(0, app(AnnouncementService::class)->dispatchDue());
        $this->assertGreaterThan(0, $a->fresh()->recipients_count);
    }

    public function test_match_reminders_are_sent_once(): void
    {
        [$t, $organizer, , $players] = $this->playable(2);
        $m = TournamentMatch::where('tournament_id', $t->id)->where('status', 'ready')->first();
        app(FixtureService::class)->schedule($m, $organizer, now()->addMinutes(30), 'Table 3');

        app(ReminderService::class)->run();
        app(ReminderService::class)->run();

        foreach ($players as $p) {
            $this->assertSame(1, UserNotification::where('user_id', $p->id)->where('type', 'match_reminder')->count());
            $this->assertTrue(UserNotification::where('user_id', $p->id)->where('type', 'match_scheduled')->exists());
        }
    }

    // ---------------------------------------------------------------- Admin and FCM

    public function test_admin_composes_and_sends_an_announcement(): void
    {
        $editor = $this->staff(RoleName::ContentEditor);
        $editor->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
        $this->player();
        $this->actingAs($editor);

        Livewire::test(CreateAnnouncement::class)
            ->fillForm(['title' => 'Welcome', 'body' => 'Pilot weekend this Friday.', 'audience' => 'everyone', 'category' => 'news', 'send_push' => true])
            ->call('create')
            ->assertHasNoFormErrors();
        $a = Announcement::where('title', 'Welcome')->firstOrFail();
        $this->assertSame('draft', $a->status->value);

        Livewire::test(EditAnnouncement::class, ['record' => $a->getRouteKey()])->callAction('sendNow')->assertHasNoActionErrors();
        $this->assertSame('sent', $a->fresh()->status->value);
    }

    public function test_fcm_provider_sends_v1_messages_and_reports_dead_tokens(): void
    {
        // Throwaway key generated for this test only (not a real credential).
        $pem = file_get_contents(base_path('tests/Fixtures/fcm-test-key.pem'));
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode(['client_email' => 'svc@example.iam.gserviceaccount.com', 'private_key' => $pem]));

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'projects/p/messages/1'])
                ->push(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);

        $invalid = (new FcmPushProvider('rivalo-test', $path))->send(['good-token', 'dead-token'], 'Result confirmed', 'Cup: A 2 – 0 B', ['type' => 'result_approved']);
        @unlink($path);

        $this->assertSame(['dead-token'], $invalid);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'projects/rivalo-test/messages:send')
            && $r['message']['notification']['title'] === 'Result confirmed'
            && $r->hasHeader('Authorization', 'Bearer test-access'));
    }
}
