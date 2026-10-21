<?php

namespace App\Console\Commands;

use App\Enums\PointsRuleSetStatus;
use App\Enums\SellerStatus;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\DeliveryZone;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\PickupLocation;
use App\Models\PointsRuleSet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Support\FeatureFlags;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pre-pilot / pre-launch readiness check for a real server. Read-only: it changes nothing.
 * FAIL = must fix before real players use the server. WARN = decide consciously.
 * Owner decisions and legal sign-off can't be detected by software; they are listed as reminders.
 */
class LaunchCheck extends Command
{
    public const HEARTBEAT_KEY = 'rivalo:scheduler_heartbeat';

    protected $signature = 'rivalo:launch-check {--json : Machine-readable output}';

    protected $description = 'Check configuration, operations, data and policies before a pilot or launch (read-only)';

    /** @var list<array{section: string, status: string, check: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->configuration();
        $this->operations();
        $this->data();
        $this->policies();

        if ($this->option('json')) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->report();
        }

        return collect($this->results)->contains('status', 'FAIL') ? self::FAILURE : self::SUCCESS;
    }

    private function add(string $section, string $status, string $check, string $detail = ''): void
    {
        $this->results[] = compact('section', 'status', 'check', 'detail');
    }

    private function check(string $section, bool $ok, string $check, string $failDetail, string $failStatus = 'FAIL', string $okDetail = ''): void
    {
        $this->add($section, $ok ? 'PASS' : $failStatus, $check, $ok ? $okDetail : $failDetail);
    }

    private function configuration(): void
    {
        $s = 'Configuration';
        $this->check($s, app()->isProduction(), 'APP_ENV is production', 'APP_ENV='.app()->environment().'. Sample seeders and development helpers stay available outside production.');
        $this->check($s, ! config('app.debug'), 'APP_DEBUG is off', 'APP_DEBUG=true shows stack traces and configuration to anyone who triggers an error.');
        $this->check($s, filled(config('app.key')), 'APP_KEY is set', 'Run php artisan key:generate once and keep the key secret and backed up.');
        $this->check($s, str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS', 'APP_URL='.config('app.url'));
        $this->check($s, (bool) config('session.secure'), 'Admin session cookie is HTTPS-only', 'Set SESSION_SECURE_COOKIE=true.');
        $this->check($s, DB::getDriverName() === 'pgsql', 'Database is PostgreSQL', 'Driver is '.DB::getDriverName().'. Production uses PostgreSQL (some constraints exist only there).');
        $this->check($s, config('queue.default') !== 'sync', 'Queue is not "sync"', 'With QUEUE_CONNECTION=sync, phone notifications and announcements run inside web requests.');
        $this->check($s, ! in_array(config('mail.default'), ['log', 'array'], true), 'Email is really sent', 'MAIL_MAILER='.config('mail.default').': verification and password-reset emails never arrive.');
        $this->check($s, filled(config('rivalo.support_email')), 'Support email is set', 'Set RIVALO_SUPPORT_EMAIL to a monitored inbox. The app shows it in Settings.');
        $this->check($s, extension_loaded('gd'), 'GD image extension is loaded', 'Without GD, uploaded photos keep hidden metadata such as GPS location.');
        $this->check($s, extension_loaded('intl'), 'intl extension is loaded', 'Needed for dates and numbers in Arabic and Kurdish.');
        $this->check($s, filled(env('TRUSTED_PROXIES')), 'TRUSTED_PROXIES is set', 'Needed behind a proxy or load balancer, or rate limits and audit logs see the proxy IP instead of the player IP.', 'WARN');

        $push = config('rivalo.push');
        if (($push['driver'] ?? 'log') === 'fcm') {
            $path = (string) ($push['fcm_credentials_path'] ?? '');
            $this->check($s, $path !== '' && is_readable($path), 'Firebase credentials file is readable', 'FCM_CREDENTIALS_PATH does not point to a readable file.');
            $public = realpath(public_path()) ?: public_path();
            $this->check($s, $path === '' || ! str_starts_with(realpath($path) ?: $path, $public), 'Firebase credentials are outside the web root', 'The service-account file is inside public/ and could be downloaded.');
        } else {
            $this->add($s, 'WARN', 'Phone notifications', 'PUSH_DRIVER=log: messages reach the in-app inbox only (see docs/07, Firebase).');
        }
    }

    private function operations(): void
    {
        $s = 'Operations';
        $beat = Cache::get(self::HEARTBEAT_KEY);
        $age = $beat ? Carbon::parse($beat)->diffInMinutes(now()) : null;
        $this->check($s, $age !== null && $age <= 10, 'Scheduler is running', $beat ? "Last run {$age} minutes ago." : 'No scheduler run recorded. Add the cron entry: * * * * * php artisan schedule:run');

        try {
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $this->check($s, $failed === 0, 'No failed background jobs', "{$failed} failed job(s). Inspect with php artisan queue:failed.", 'WARN');
            if (config('queue.default') === 'database') {
                $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(10)->getTimestamp())->count();
                $this->check($s, $stuck === 0, 'Queue worker is processing jobs', "{$stuck} job(s) waiting over 10 minutes. Is php artisan queue:work running?");
            }
        } catch (Throwable $e) {
            $this->add($s, 'WARN', 'Queue tables', $e->getMessage());
        }

        $this->check($s, is_link(public_path('storage')) || is_dir(public_path('storage')), 'Public storage link exists', 'Run php artisan storage:link, or photos will not load.');
    }

    private function data(): void
    {
        $s = 'Data';
        $sample = [
            'users' => User::where('is_sample_data', true)->count(),
            'teams' => Team::where('is_sample_data', true)->count(),
            'tournaments' => Tournament::where('is_sample_data', true)->count(),
            'news' => NewsArticle::where('is_sample_data', true)->count(),
            'announcements' => Announcement::where('is_sample_data', true)->count(),
            'products' => Product::where('is_sample_data', true)->count(),
            'store_points' => PickupLocation::where('is_sample_data', true)->count() + DeliveryZone::where('is_sample_data', true)->count(),
            'sellers' => Seller::where('is_sample_data', true)->count(),
        ];
        $sampleTotal = array_sum($sample);
        $this->check($s, $sampleTotal === 0, 'No sample data', 'Sample rows present: '.http_build_query($sample, '', ', ').'. Use a fresh production database; never run sample seeders there.');

        $admins = User::role('super_admin')->where('status', UserStatus::Active->value)->get();
        $this->check($s, $admins->isNotEmpty(), 'An active super admin exists', 'Create one with php artisan rivalo:make-admin.');
        $noMfa = User::permission('admin.access')->whereNull('app_authentication_secret')->where('status', UserStatus::Active->value)->count();
        $this->check($s, $noMfa === 0, 'Every staff account has enrolled MFA', "{$noMfa} staff account(s) have not signed in to the admin panel yet (MFA is enforced at first sign-in).", 'WARN');

        $this->add($s, 'INFO', 'Paid entry flag', FeatureFlags::enabled(FeatureFlags::PAID_ENTRY) ? 'ENABLED. Confirm decision D7 is recorded.' : 'Disabled (decision D7 open).');

        $published = PointsRuleSet::where('status', PointsRuleSetStatus::Published->value)->get(['name']);
        $real = $published->reject(fn ($r) => str_contains($r->name, '[SAMPLE]'));
        $this->check($s, $real->isNotEmpty(), 'A real points table is published', 'No published points table without [SAMPLE]: tournaments award no points until the owner decides D3.', 'WARN');

        // Store (Milestone 8): only checked when the store is switched on.
        if (FeatureFlags::enabled(FeatureFlags::STORE)) {
            $this->check($s, PickupLocation::where('is_active', true)->exists() || DeliveryZone::where('is_active', true)->exists(), 'Store: customers can receive orders', 'The store is on but there is no active pickup point or delivery area.');
            $this->check($s, ProductVariant::where('is_active', true)->where('stock', '>', 0)->whereHas('product', fn ($q) => $q->onSale())->exists(), 'Store: something is in stock', 'The store is on but no active product has stock.', 'WARN');
            $this->add($s, 'INFO', 'Store payments', 'Cash on pickup or delivery only; no card payments until a payment provider is verified.');
        } else {
            $this->add($s, 'INFO', 'Store', 'Switched off (store_enabled). The app shows News in the fourth tab.');
        }

        // Marketplace (Milestone 9): only checked when it is switched on.
        if (FeatureFlags::enabled(FeatureFlags::MARKETPLACE)) {
            $this->check($s, FeatureFlags::enabled(FeatureFlags::STORE), 'Marketplace: the store is on', 'marketplace_enabled is on but store_enabled is off: seller products cannot be sold.');
            $this->add($s, 'INFO', 'Marketplace', Seller::where('status', SellerStatus::Active->value)->count().' active seller(s). Commission comes from each signed agreement; payouts are recorded by finance staff (decisions D22, D23).');
        } else {
            $this->add($s, 'INFO', 'Marketplace', 'Switched off (marketplace_enabled). Only official products are sold.');
        }
    }

    private function policies(): void
    {
        $s = 'Policies';
        $required = [Page::TERMS, Page::PRIVACY, Page::GUIDELINES];
        if (FeatureFlags::enabled(FeatureFlags::MARKETPLACE)) {
            $required[] = Page::SELLER_TERMS; // sellers read these before applying
        }
        foreach ($required as $slug) {
            $page = Page::where('slug', $slug)->first();
            if (! $page || ! $page->isPublished()) {
                $this->add($s, 'FAIL', "Policy page \"{$slug}\" is published", 'Publish it in Admin → Community → Policy pages.');

                continue;
            }
            $this->add($s, 'PASS', "Policy page \"{$slug}\" is published", 'Version '.$page->current_version);
            if ($page->requires_legal_review) {
                $this->check($s, $page->legallyReviewed(), "\"{$slug}\" legal review recorded", 'The app shows "Pending legal review". Required before public launch (decision D6); a closed pilot may proceed with the owner\'s agreement.', 'WARN');
            }
            $body = (string) ($page->currentVersion?->body_translations['en'] ?? '');
            $unfinished = preg_match('/\[[A-Z][A-Z ,\-—]{6,}/u', $body) || str_contains($body, 'PENDING LEGAL REVIEW');
            $this->check($s, ! $unfinished, "\"{$slug}\" has no draft placeholders left", 'The published text still contains [BRACKETED] placeholders or the "DRAFT — PENDING LEGAL REVIEW" header.');
        }

        foreach (['D1 pool rules', 'D2 padel "series"', 'D3 points tables', 'D4 prize and organizer shares', 'D5 cancellation rules', 'D6 jurisdiction and age (legal)', 'D7 paid entry (legal)', 'D9 active disciplines', 'D10 infrastructure budget', 'D20 store prices and delivery', 'D23 seller money terms', 'D24 seller verification and terms (legal)'] as $d) {
            $this->add('Owner decisions (not detectable)', 'TODO', $d, 'Confirm in docs/05-DECISIONS.md');
        }
    }

    private function report(): void
    {
        foreach (collect($this->results)->groupBy('section') as $section => $rows) {
            $this->newLine();
            $this->line("<options=bold>{$section}</>");
            foreach ($rows as $r) {
                $tag = match ($r['status']) {
                    'PASS' => '<fg=green>PASS</>',
                    'FAIL' => '<fg=red>FAIL</>',
                    'WARN' => '<fg=yellow>WARN</>',
                    default => "<fg=gray>{$r['status']}</>",
                };
                $this->line("  {$tag}  {$r['check']}".($r['detail'] !== '' ? "  <fg=gray>— {$r['detail']}</>" : ''));
            }
        }
        $counts = collect($this->results)->countBy('status');
        $this->newLine();
        $this->line(sprintf('PASS %d · WARN %d · FAIL %d', $counts['PASS'] ?? 0, $counts['WARN'] ?? 0, $counts['FAIL'] ?? 0));
        if (($counts['FAIL'] ?? 0) > 0) {
            $this->error('Not ready: fix every FAIL before real players use this server.');
        }
    }
}
