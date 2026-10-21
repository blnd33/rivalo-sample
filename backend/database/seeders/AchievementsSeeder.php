<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * Production-safe default badges. Each is earned only from the player's real, approved record.
 * Idempotent: existing badges keep any edits staff made (only missing ones are created).
 * Kurdish (Kurmanji) wording needs native review.
 */
class AchievementsSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['first_match', 'First match', 'Played your first approved match.', 'flag', 'matches_played', 1,
                ['ar' => 'أول مباراة', 'kmr' => 'Maça yekem'], ['ar' => 'لعبت أول مباراة معتمدة.', 'kmr' => 'Te maça xwe ya yekem a pejirandî lîst.']],
            ['checked_in', 'On site', 'Checked in at the venue for a tournament.', 'qrcode', 'check_ins', 1,
                ['ar' => 'في الموعد', 'kmr' => 'Li cih'], ['ar' => 'سجّلت حضورك في مكان البطولة.', 'kmr' => 'Te li cihê tûrnûvayê amadebûna xwe tomar kir.']],
            ['first_win', 'First win', 'Won your first approved match.', 'checkmark_seal', 'matches_won', 1,
                ['ar' => 'أول فوز', 'kmr' => 'Serketina yekem'], ['ar' => 'فزت بأول مباراة معتمدة.', 'kmr' => 'Te maça xwe ya yekem a pejirandî bi ser xist.']],
            ['wins_10', '10 wins', 'Won 10 approved matches.', 'flame', 'matches_won', 10,
                ['ar' => '10 انتصارات', 'kmr' => '10 serketin'], ['ar' => 'فزت بعشر مباريات معتمدة.', 'kmr' => 'Te 10 maçên pejirandî bi ser xistin.']],
            ['regular', 'Regular', 'Finished 5 tournaments.', 'repeat', 'tournaments_completed', 5,
                ['ar' => 'منتظم', 'kmr' => 'Domdar'], ['ar' => 'أكملت 5 بطولات.', 'kmr' => 'Te 5 tûrnûva qedandin.']],
            ['podium', 'Podium', 'Finished in the top three of a tournament.', 'rosette', 'podium_finishes', 1,
                ['ar' => 'منصة التتويج', 'kmr' => 'Kursiya serkeftinê'], ['ar' => 'أنهيت بطولة ضمن المراكز الثلاثة الأولى.', 'kmr' => 'Te tûrnûvayek di nav sê yên pêşîn de qedand.']],
            ['champion', 'Champion', 'Won a tournament.', 'trophy', 'tournament_wins', 1,
                ['ar' => 'بطل', 'kmr' => 'Şampiyon'], ['ar' => 'فزت ببطولة.', 'kmr' => 'Te tûrnûvayek bi ser xist.']],
        ];

        foreach ($badges as $i => [$key, $name, $description, $icon, $criteria, $threshold, $nameTr, $descTr]) {
            Achievement::firstOrCreate(['key' => $key], [
                'name' => $name,
                'name_translations' => $nameTr,
                'description' => $description,
                'description_translations' => $descTr,
                'icon' => $icon,
                'criteria_type' => $criteria,
                'threshold' => $threshold,
                'sort_order' => ($i + 1) * 10,
                'status' => 'active',
            ]);
        }
    }
}
