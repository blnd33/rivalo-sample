<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\AnnouncementService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE: works out sample players' badges from their (sample) results and sends one
 * "[SAMPLE]" announcement so the inbox has content. Refuses to run in production.
 */
class SampleEngagementSeeder extends Seeder
{
    public function run(AchievementService $achievements, AnnouncementService $announcements): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleEngagementSeeder must never run in production.');
        }
        if (Announcement::where('is_sample_data', true)->exists()) {
            $this->command?->warn('Sample engagement data already present. Skipping.');

            return;
        }

        foreach (User::where('is_sample_data', true)->get() as $user) {
            $achievements->evaluate($user);
        }

        $admin = User::where('username', 'sample_admin')->first();
        if (! $admin) {
            return;
        }
        $a = Announcement::create([
            'title' => '[SAMPLE] Welcome to the RIVALO pilot',
            'body' => 'This is a sample announcement for development. Real announcements are written by staff in the admin panel.',
            'audience' => 'everyone',
            'category' => 'news',
            'send_push' => false,
            'created_by_id' => $admin->id,
        ]);
        $a->forceFill(['is_sample_data' => true])->save();
        $announcements->sendNow($a, $admin);

        $this->command?->info('Sample badges and announcement created.');
    }
}
