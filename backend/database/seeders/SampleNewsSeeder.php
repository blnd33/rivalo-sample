<?php

namespace Database\Seeders;

use App\Models\NewsArticle;
use App\Models\Sport;
use App\Models\Tournament;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE NEWS. Every article is flagged is_sample_data and titled "[SAMPLE]".
 * The articles explain real app features; they make no claims about players, results or adoption.
 * No photos are seeded: the app draws sport artwork until an editor uploads a real cover photo.
 */
class SampleNewsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleNewsSeeder must never run in production.');
        }
        if (NewsArticle::where('is_sample_data', true)->exists()) {
            $this->command?->warn('Sample news already present. Skipping.');

            return;
        }

        $sport = fn (string $slug) => Sport::where('slug', $slug)->value('id');
        $tournament = fn (string $name) => Tournament::where('name', $name)->value('id');

        $articles = [
            [
                'title' => '[SAMPLE] Welcome to RIVALO',
                'summary' => 'Find tournaments, build your team and follow the rankings in one place.',
                'category' => 'announcement',
                'is_featured' => true,
                'hours_ago' => 2,
                'body' => "This is sample news for development. Replace it with real announcements before launch.\n\n"
                    ."RIVALO brings local football, padel, pool and e-sports competitions into one app. Browse upcoming tournaments, register on your own or with your team, and keep your check-in pass on your phone.\n\n"
                    .'Rankings appear once official results are approved by referees. Points are never estimated or invented.',
            ],
            [
                'title' => '[SAMPLE] Padel Doubles Open: registration is open',
                'summary' => 'Pairs can register now. Places are limited and a waitlist opens when the draw is full.',
                'category' => 'tournament',
                'is_featured' => true,
                'sport' => 'padel',
                'tournament' => '[SAMPLE] Padel Doubles Open',
                'hours_ago' => 6,
                'body' => "Sample article linked to a sample tournament.\n\n"
                    ."Captains register their pair from the tournament page. Both players must have accepted the team invitation before the pair can be entered.\n\n"
                    .'When every place is taken, new pairs join the waitlist in order and move up automatically if a reserved pair cancels.',
            ],
            [
                'title' => '[SAMPLE] How check-in works at the venue',
                'summary' => 'Open your check-in pass, show the QR code to the organizer, and you are in.',
                'category' => 'guide',
                'is_featured' => true,
                'hours_ago' => 26,
                'body' => "Sample guide for development.\n\n"
                    ."1. Open the tournament and tap \"Show check-in code\". Each player has a personal code.\n\n"
                    ."2. The organizer scans it and compares your photo and name with the roster.\n\n"
                    ."3. If the tournament has an entry fee, the organizer records your payment and gives you a receipt number. Paid entry is only available where it has been approved.\n\n"
                    .'4. After check-in you are marked eligible to play. The pass also works without signal once you have opened it.',
            ],
            [
                'title' => '[SAMPLE] 9-Ball Night: waitlist is open',
                'summary' => 'Every reserved place is taken. Join the waitlist to be next in line.',
                'category' => 'tournament',
                'sport' => 'cue-sports',
                'tournament' => '[SAMPLE] 9-Ball Night',
                'hours_ago' => 30,
                'body' => "Sample article linked to a sample tournament with a small capacity.\n\n"
                    .'Waitlisted players are promoted in order when a reserved player cancels before registration closes.',
            ],
            [
                'title' => '[SAMPLE] Building your team',
                'summary' => 'Create a team, invite players by username and choose your roster for each tournament.',
                'category' => 'guide',
                'sport' => 'football',
                'hours_ago' => 50,
                'body' => "Sample guide for development.\n\n"
                    ."Captains create a team for one sport and invite players by username. Invited players accept from their inbox.\n\n"
                    .'For each tournament the captain chooses the starting roster and substitutes from accepted members. The roster is saved as it was when you registered, so history stays accurate.',
            ],
            [
                'title' => '[نموذج] أهلاً بك في RIVALO',
                'summary' => 'خبر تجريبي باللغة العربية يظهر فقط لمن يستخدم التطبيق بالعربية.',
                'category' => 'announcement',
                'locale' => 'ar',
                'hours_ago' => 3,
                'body' => "هذا خبر تجريبي لأغراض التطوير. استبدله بإعلانات حقيقية قبل الإطلاق.\n\n"
                    .'يمكنك تصفح البطولات القادمة، والتسجيل بمفردك أو مع فريقك، وإظهار رمز تسجيل الحضور من هاتفك.',
            ],
        ];

        foreach ($articles as $a) {
            $article = new NewsArticle([
                'title' => $a['title'],
                'summary' => $a['summary'],
                'body' => $a['body'],
                'category' => $a['category'],
                'locale' => $a['locale'] ?? null,
                'is_featured' => $a['is_featured'] ?? false,
                'sport_id' => isset($a['sport']) ? $sport($a['sport']) : null,
                'tournament_id' => isset($a['tournament']) ? $tournament($a['tournament']) : null,
                'status' => 'published',
                'published_at' => now()->subHours($a['hours_ago']),
            ]);
            $article->is_sample_data = true;
            $article->save();
        }

        $this->command?->info('Sample news created ('.count($articles).' articles).');
    }
}
