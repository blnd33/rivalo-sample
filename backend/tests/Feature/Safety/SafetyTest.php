<?php

namespace Tests\Feature\Safety;

use App\Enums\InvitationStatus;
use App\Enums\ModerationAction;
use App\Enums\ReportReason;
use App\Enums\ReportSubject;
use App\Enums\RoleName;
use App\Exceptions\DomainRuleException;
use App\Filament\Resources\PolicyPages\Pages\EditPage;
use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserNotification;
use App\Services\AccountService;
use App\Services\ModerationService;
use App\Services\Notifier;
use App\Services\PageService;
use App\Services\TeamService;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class SafetyTest extends TestCase
{
    private ModerationService $moderation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moderation = app(ModerationService::class);
        Storage::fake('public');
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

    private function moderator(RoleName $role = RoleName::SupportStaff): User
    {
        $user = $this->staff($role);
        $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        return $user;
    }

    /** actingAs() sticks for later requests in the same test; this returns to a guest. */
    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function inbox(User $user, string $type): ?UserNotification
    {
        return UserNotification::where('user_id', $user->id)->where('type', $type)->latest('id')->first();
    }

    // ---------------------------------------------------------------- Reporting

    public function test_players_report_a_player_through_the_api_and_get_only_a_receipt(): void
    {
        $reporter = $this->player();
        $target = $this->player(['display_name' => 'Rude Name', 'city' => 'Duhok']);

        $this->actingAs($reporter, 'sanctum')->postJson('/api/v1/reports', [
            'subject_type' => 'user', 'subject' => $target->username, 'reason' => 'offensive_name', 'details' => 'Insulting display name.',
        ])->assertCreated()->assertExactJson(['data' => ['id' => Report::first()->id, 'status' => 'received']]);

        $report = Report::firstOrFail();
        $this->assertSame('open', $report->status->value);
        $this->assertSame('Rude Name', $report->snapshot['display_name'], 'The snapshot keeps what was reported.');
        $this->assertSame("user:{$target->id}", $report->dedupe_key);

        // Same reporter, same subject, still open: refused. Another reporter: accepted.
        $this->actingAs($reporter, 'sanctum')->postJson('/api/v1/reports', ['subject_type' => 'user', 'subject' => $target->username, 'reason' => 'harassment'])
            ->assertStatus(409)->assertJsonPath('code', 'report_exists');
        $this->moderation->report($this->player(), ReportSubject::User, $target, ReportReason::OffensiveName);
        $this->assertSame(2, Report::count());

        $this->asGuest()->postJson('/api/v1/reports', ['subject_type' => 'app', 'reason' => 'app_problem', 'details' => 'x'])->assertUnauthorized();
    }

    public function test_report_rules(): void
    {
        $p = $this->player();
        $team = $this->teamWithMembers('padel', 1);

        $this->expectRule('report_self', fn () => $this->moderation->report($p, ReportSubject::User, $p, ReportReason::Spam));
        $this->expectRule('invalid_reason', fn () => $this->moderation->report($p, ReportSubject::Team, $team, ReportReason::Harassment));
        $this->expectRule('invalid_reason', fn () => $this->moderation->report($p, ReportSubject::App, null, ReportReason::Spam));
        $this->expectRule('report_details_required', fn () => $this->moderation->report($p, ReportSubject::User, $this->player(), ReportReason::Other));
        $this->expectRule('report_details_required', fn () => $this->moderation->report($p, ReportSubject::App, null, ReportReason::Suggestion, '  '));

        $feedback = $this->moderation->report($p, ReportSubject::App, null, ReportReason::AppProblem, 'Crash on the bracket screen', ['app_version' => '1.0.0', 'platform' => 'android']);
        $this->assertSame(['app_version' => '1.0.0', 'platform' => 'android', 'locale' => 'en'], $feedback->context);
        $this->assertNull($feedback->dedupe_key, 'Feedback is never deduplicated.');
    }

    // ---------------------------------------------------------------- Moderation

    public function test_dismissal_tells_only_the_reporter_and_is_audited(): void
    {
        $reporter = $this->player();
        $target = $this->player();
        $report = $this->moderation->report($reporter, ReportSubject::User, $target, ReportReason::Spam);

        $this->moderation->dismiss($report, $this->moderator(), 'Normal profile.');

        $this->assertSame('dismissed', $report->fresh()->status->value);
        $this->assertStringContainsString('did not find a breach', $this->inbox($reporter, 'report_reviewed')->body);
        $this->assertSame(0, UserNotification::where('user_id', $target->id)->count(), 'The reported player is never told about a dismissed report.');
        $this->assertTrue(AuditLog::where('action', 'report.dismissed')->where('reason', 'Normal profile.')->exists());
        $this->expectRule('report_closed', fn () => $this->moderation->dismiss($report, $this->moderator(), 'again'));
    }

    public function test_removing_a_photo_closes_every_open_report_about_that_player(): void
    {
        $target = $this->player();
        app(AccountService::class)->updateAvatar($target, UploadedFile::fake()->image('a.jpg', 200, 200));
        $avatar = $target->fresh()->avatar_path;
        [$r1, $r2] = [$this->player(), $this->player()];
        $first = $this->moderation->report($r1, ReportSubject::User, $target, ReportReason::OffensivePhoto);
        $second = $this->moderation->report($r2, ReportSubject::User, $target, ReportReason::OffensivePhoto);

        $this->moderation->act($first, $this->moderator(), ModerationAction::RemovePhoto, 'Offensive gesture.');

        $this->assertNull($target->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($avatar);
        $this->assertSame('actioned', $second->fresh()->status->value);
        $this->assertStringContainsString("#{$first->id}", $second->fresh()->resolution_note);
        $this->assertSame('page', $this->inbox($target, 'moderation_photo_removed')->link_kind);
        foreach ([$r1, $r2] as $r) {
            $this->assertStringContainsString('took action', $this->inbox($r, 'report_reviewed')->body);
        }
        $this->assertTrue(AuditLog::where('action', 'moderation.avatar_removed')->where('subject_id', $target->id)->exists());
    }

    public function test_display_name_reset_team_rename_and_warning(): void
    {
        $staff = $this->moderator();
        $target = $this->player(['display_name' => 'Bad Words']);
        $report = $this->moderation->report($this->player(), ReportSubject::User, $target, ReportReason::OffensiveName);
        $this->moderation->act($report, $staff, ModerationAction::ResetDisplayName, 'Slur in name.');
        $this->assertNull($target->fresh()->display_name);
        $this->assertNotNull($this->inbox($target, 'moderation_name_reset'));

        $team = $this->teamWithMembers('padel', 1);
        $report = $this->moderation->report($this->player(), ReportSubject::Team, $team, ReportReason::OffensiveName);
        $this->expectRule('invalid_team_name', fn () => $this->moderation->act($report, $staff, ModerationAction::RenameTeam, 'Offensive', ['new_name' => ' ']));
        $this->expectRule('invalid_action', fn () => $this->moderation->act($report, $staff, ModerationAction::Suspend, 'Teams are not suspended'));
        $this->moderation->act($report, $staff, ModerationAction::RenameTeam, 'Offensive team name.', ['new_name' => 'Team Renamed']);
        $this->assertSame('Team Renamed', $team->fresh()->name);
        foreach ($team->activeMemberships()->pluck('user_id') as $memberId) {
            $this->assertSame(1, UserNotification::where('user_id', $memberId)->where('type', 'moderation_team_renamed')->count());
        }

        $other = $this->player();
        $report = $this->moderation->report($this->player(), ReportSubject::User, $other, ReportReason::Harassment, 'Abusive messages at the venue.');
        $this->moderation->act($report, $staff, ModerationAction::Warn, 'Witnessed by referee.', ['message' => 'Please keep it respectful at the venue.']);
        $warning = $this->inbox($other, 'moderation_warning');
        $this->assertSame('Please keep it respectful at the venue.', $warning->body);
        $this->assertSame('account', $warning->category);
        $this->assertSame('skipped_preference', $warning->push_status, 'Safety messages stay in the inbox (no phone notification).');
    }

    public function test_suspension_ends_sessions_and_staff_accounts_need_a_super_admin(): void
    {
        $target = $this->player();
        $target->createToken('phone');
        $report = $this->moderation->report($this->player(), ReportSubject::User, $target, ReportReason::Cheating, 'Used another account.');
        $this->moderation->act($report, $this->moderator(), ModerationAction::Suspend, 'Confirmed account sharing.');

        $target->refresh();
        $this->assertSame('suspended', $target->status->value);
        $this->assertSame('Confirmed account sharing.', $target->suspension_reason);
        $this->assertSame(0, $target->tokens()->count());

        $referee = $this->staff(RoleName::Referee);
        $report = $this->moderation->report($this->player(), ReportSubject::User, $referee, ReportReason::Harassment, 'Shouted at players.');
        $this->expectRule('forbidden', fn () => $this->moderation->act($report, $this->moderator(), ModerationAction::Suspend, 'x'));
        $this->moderation->act($report, $this->moderator(RoleName::SuperAdmin), ModerationAction::Suspend, 'Confirmed by organizer.');
        $this->assertSame('suspended', $referee->fresh()->status->value);
    }

    public function test_staff_cannot_decide_reports_that_involve_them(): void
    {
        $support = $this->moderator();
        $aboutMe = $this->moderation->report($this->player(), ReportSubject::User, $support, ReportReason::Harassment, 'Rude.');
        $this->expectRule('moderation_conflict', fn () => $this->moderation->dismiss($aboutMe, $support, 'Not me'));

        $mine = $this->moderation->report($support, ReportSubject::User, $this->player(), ReportReason::Spam);
        $this->expectRule('moderation_conflict', fn () => $this->moderation->dismiss($mine, $support, 'Mine'));

        $this->expectRule('forbidden', fn () => $this->moderation->dismiss($mine, $this->staff(RoleName::ContentEditor), 'No permission'));
    }

    public function test_moderation_queue_in_the_admin_panel(): void
    {
        $target = $this->player(['display_name' => 'Offensive']);
        $report = $this->moderation->report($this->player(), ReportSubject::User, $target, ReportReason::OffensiveName);

        $this->actingAs($this->moderator(RoleName::ContentEditor));
        $this->get(ReportResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->moderator());
        $this->get(ReportResource::getUrl('index'))->assertOk()->assertSee('@'.$target->username);
        $this->get(ReportResource::getUrl('view', ['record' => $report]))->assertOk()->assertSee('Offensive');
        $this->assertSame('1', ReportResource::getNavigationBadge());

        Livewire::test(ViewReport::class, ['record' => $report->getRouteKey()])
            ->callAction('act', ['action' => 'reset_display_name', 'note' => 'Offensive display name.'])
            ->assertHasNoActionErrors();
        $this->assertSame('actioned', $report->fresh()->status->value);
        $this->assertNull($target->fresh()->display_name);
        $this->assertNull(ReportResource::getNavigationBadge());
    }

    // ---------------------------------------------------------------- Blocking

    public function test_blocking_hides_players_from_search_and_stops_invitations(): void
    {
        $captain = $this->player(['username' => 'captain_a']);
        $pest = $this->player(['username' => 'cap_pest']);
        $team = app(TeamService::class)->create($captain, $this->sport('padel'), 'Blockers');
        $pending = app(TeamService::class)->invite($team, $captain, $pest);

        $this->actingAs($pest, 'sanctum')->postJson('/api/v1/users/captain_a/block')->assertOk()->assertJsonPath('data.blocked', true);

        $this->assertSame(InvitationStatus::Revoked, $pending->fresh()->status, 'Pending invitations between them are withdrawn.');
        $this->expectRule('user_unavailable', fn () => app(TeamService::class)->invite($team, $captain, $pest));
        // Neither finds the other in search. The blocked player is not told they are blocked.
        $this->actingAs($captain, 'sanctum')->getJson('/api/v1/users?q=cap')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($pest, 'sanctum')->getJson('/api/v1/users?q=cap')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($captain, 'sanctum')->getJson('/api/v1/users/cap_pest')->assertOk()->assertJsonPath('data.viewer.blocked', false);

        $this->actingAs($pest, 'sanctum')->getJson('/api/v1/users/captain_a')->assertJsonPath('data.viewer.blocked', true);
        $this->actingAs($pest, 'sanctum')->getJson('/api/v1/me/blocks')->assertOk()->assertJsonPath('data.0.username', 'captain_a');
        $this->actingAs($captain, 'sanctum')->getJson('/api/v1/me/blocks')->assertOk()->assertJsonCount(0, 'data');
        $this->asGuest()->getJson('/api/v1/users/captain_a')->assertOk()->assertJsonPath('data.viewer', null);

        $this->actingAs($pest, 'sanctum')->deleteJson('/api/v1/users/captain_a/block')->assertOk();
        $this->assertFalse(UserBlock::between($captain->id, $pest->id));
        app(TeamService::class)->invite($team, $captain, $pest);
        $this->expectRule('block_self', fn () => $this->moderation->block($pest, $pest));
    }

    // ---------------------------------------------------------------- Policy pages

    public function test_policy_pages_publish_versions_and_never_claim_an_unrecorded_legal_review(): void
    {
        $terms = Page::where('slug', Page::TERMS)->firstOrFail();
        $this->getJson('/api/v1/pages/terms')->assertNotFound();

        $editor = $this->staff(RoleName::ContentEditor);
        app(PageService::class)->publish($terms, $editor, 'First draft for the pilot.');

        $this->getJson('/api/v1/pages/terms')->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.legal_review.status', 'pending')
            ->assertJsonPath('data.translation_pending', false);
        $this->getJson('/api/v1/pages/terms', ['Accept-Language' => 'ar'])->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.translation_pending', true);
        $this->getJson('/api/v1/pages/community-guidelines')->assertNotFound();

        $this->expectRule('page_unchanged', fn () => app(PageService::class)->publish($terms->fresh(), $editor, 'Nothing new'));
        $this->expectRule('forbidden', fn () => app(PageService::class)->recordLegalReview($terms->fresh(), $editor, 'Someone', 'Approved'));

        $admin = $this->staff(RoleName::SuperAdmin);
        app(PageService::class)->recordLegalReview($terms->fresh(), $admin, 'A. Lawyer, Example Law Firm', 'Written approval 2026-11-01');
        $this->getJson('/api/v1/pages/terms')->assertJsonPath('data.legal_review.status', 'reviewed');

        // A new version needs its own review.
        app(PageService::class)->saveDraft($terms->fresh(), $editor, [
            'title_translations' => ['en' => 'Terms of use', 'ar' => 'شروط الاستخدام'],
            'body_translations' => ['en' => 'Changed text.', 'ar' => 'نص معدل.'],
        ]);
        $this->getJson('/api/v1/pages/terms')->assertJsonPath('data.version', 1)->assertJsonPath('data.legal_review.status', 'reviewed');
        app(PageService::class)->publish($terms->fresh(), $editor, 'Clarified wording.');
        $this->getJson('/api/v1/pages/terms', ['Accept-Language' => 'ar'])
            ->assertJsonPath('data.version', 2)->assertJsonPath('data.body', 'نص معدل.')->assertJsonPath('data.legal_review.status', 'pending');

        $this->expectException(LogicException::class);
        PageVersion::first()->update(['change_note' => 'rewrite history']);
    }

    public function test_policy_pages_in_the_admin_panel(): void
    {
        $editor = $this->moderator(RoleName::ContentEditor);
        $this->actingAs($editor);
        $page = Page::where('slug', Page::GUIDELINES)->firstOrFail();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title_translations.en' => 'Community guidelines', 'body_translations.en' => 'Be kind.'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('publish', ['note' => 'Pilot version'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('legalReview');

        $this->assertSame(1, $page->fresh()->current_version);
        $this->getJson('/api/v1/pages/community-guidelines')->assertJsonPath('data.body', 'Be kind.')->assertJsonPath('data.legal_review.status', 'not_required');
        $this->assertTrue(AuditLog::where('action', 'page.published')->exists());
    }

    // ---------------------------------------------------------------- Privacy and security

    public function test_account_deletion_removes_inbox_blocks_and_report_snapshots(): void
    {
        $user = $this->player(['display_name' => 'Real Name']);
        $other = $this->player();
        $this->moderation->block($user, $other);
        $this->moderation->block($other, $user);
        $report = $this->moderation->report($other, ReportSubject::User, $user, ReportReason::OffensiveName);
        app(Notifier::class)->deliver([$user->id], 'report_reviewed', ['outcome' => 'feedback'], null, null);

        app(AccountService::class)->anonymize($user);

        $this->assertSame(0, UserNotification::where('user_id', $user->id)->count());
        $this->assertSame(0, UserBlock::count());
        $this->assertSame(['username' => 'deleted_'.$user->id], $report->fresh()->snapshot);
    }

    public function test_team_logos_are_reencoded_before_they_become_public(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to re-encode images.');
        }
        $team = $this->teamWithMembers('padel', 0);
        $this->actingAs($team->captain, 'sanctum')
            ->post("/api/v1/teams/{$team->slug}/logo", ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk();
        $path = $team->fresh()->logo_path;
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertSame("\xFF\xD8", substr(Storage::disk('public')->get($path), 0, 2), 'Stored as a freshly encoded JPEG.');
    }

    public function test_security_headers_and_private_responses_are_not_cached(): void
    {
        $this->getJson('/api/v1/config')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        $token = $this->player()->createToken('phone')->plainTextToken;
        $cache = $this->withToken($token)->getJson('/api/v1/me')->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);

        $this->get('https://localhost/admin/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_idle_sessions_are_pruned(): void
    {
        $user = $this->player();
        $old = $user->createToken('old phone');
        $old->accessToken->forceFill(['last_used_at' => now()->subDays(200)])->save();
        $fresh = $user->createToken('new phone');

        $this->artisan('rivalo:prune-idle-sessions')->assertSuccessful();

        $this->assertNull($old->accessToken->fresh());
        $this->assertNotNull($fresh->accessToken->fresh());
    }

    public function test_launch_check_refuses_a_non_production_setup(): void
    {
        $this->artisan('rivalo:launch-check')
            ->expectsOutputToContain('APP_ENV is production')
            ->expectsOutputToContain('Not ready')
            ->assertFailed();
    }
}
