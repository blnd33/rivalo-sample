<?php

namespace Database\Seeders;

use App\Enums\ReportReason;
use App\Enums\ReportSubject;
use App\Models\Page;
use App\Models\Report;
use App\Models\User;
use App\Services\ModerationService;
use App\Services\PageService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE: publishes the draft policy pages (still marked "pending legal review")
 * so the app can show them, and files two "[SAMPLE]" reports for the moderation queue.
 * No legal review is recorded. Refuses to run in production.
 */
class SampleSafetySeeder extends Seeder
{
    public function run(PageService $pages, ModerationService $moderation): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleSafetySeeder must never run in production.');
        }
        $admin = User::where('username', 'sample_admin')->first();
        if (! $admin) {
            return;
        }

        foreach (Page::where('current_version', 0)->get() as $page) {
            $pages->publish($page, $admin, '[SAMPLE] Development publish of the unreviewed draft.');
        }

        if (Report::whereHas('reporter', fn ($q) => $q->where('is_sample_data', true))->exists()) {
            return;
        }
        $p1 = User::where('username', 'sample_player01')->first();
        $p2 = User::where('username', 'sample_player02')->first();
        if ($p1 && $p2) {
            $moderation->report($p1, ReportSubject::User, $p2, ReportReason::OffensiveName, '[SAMPLE] Example report for the moderation queue.');
            $moderation->report($p2, ReportSubject::App, null, ReportReason::AppProblem, '[SAMPLE] Example app problem: the draw screen was slow to load.', ['app_version' => '0.1.0', 'platform' => 'android']);
        }

        $this->command?->info('Sample policy pages published (pending legal review) and sample reports filed.');
    }
}
