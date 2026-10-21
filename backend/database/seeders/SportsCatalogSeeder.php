<?php

namespace Database\Seeders;

use App\Models\Discipline;
use App\Models\Sport;
use Illuminate\Database\Seeder;

/**
 * Initial sports catalog (admin-editable afterwards). Production-safe and idempotent: it creates
 * missing rows and never overwrites admin edits.
 *
 * Activation policy (see docs/05-DECISIONS.md D1, D9):
 *  - Unambiguous physical disciplines are active with ruleset_status = confirmed AS A DEVELOPMENT
 *    DEFAULT. The owner must review before launch.
 *  - American pool stays inactive: the exact ruleset is unconfirmed (D1).
 *  - E-sports titles stay inactive: title/version/platform/region must be set by an admin first.
 */
class SportsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'sport' => ['slug' => 'football', 'name' => 'Football', 'name_translations' => ['ar' => 'كرة القدم'], 'kind' => 'physical', 'icon' => 'football', 'team_member_limit' => 25, 'sort_order' => 1],
                'disciplines' => [
                    ['slug' => '7v7', 'name' => 'Football 7v7', 'registration_types' => ['team'], 'team_size_min' => 7, 'team_size_max' => 7, 'substitutes_max' => 5, 'active' => true],
                    ['slug' => '11v11', 'name' => 'Football 11v11', 'registration_types' => ['team'], 'team_size_min' => 11, 'team_size_max' => 11, 'substitutes_max' => 7, 'active' => true],
                    ['slug' => '3v3', 'name' => 'Football 3v3', 'registration_types' => ['team'], 'team_size_min' => 3, 'team_size_max' => 3, 'substitutes_max' => 2, 'active' => true],
                    ['slug' => 'futsal', 'name' => 'Futsal', 'registration_types' => ['team'], 'team_size_min' => 5, 'team_size_max' => 5, 'substitutes_max' => 7, 'active' => true],
                ],
            ],
            [
                'sport' => ['slug' => 'padel', 'name' => 'Padel', 'name_translations' => ['ar' => 'بادل'], 'kind' => 'physical', 'icon' => 'padel', 'team_member_limit' => 4, 'sort_order' => 2],
                'disciplines' => [
                    ['slug' => 'doubles', 'name' => 'Padel doubles', 'registration_types' => ['pair'], 'team_size_min' => 2, 'team_size_max' => 2, 'substitutes_max' => 0, 'active' => true],
                ],
            ],
            [
                'sport' => ['slug' => 'cue-sports', 'name' => 'Cue sports', 'name_translations' => ['ar' => 'البلياردو'], 'kind' => 'physical', 'icon' => 'cue', 'team_member_limit' => null, 'sort_order' => 3],
                'disciplines' => [
                    ['slug' => 'american-pool', 'name' => 'American pool', 'registration_types' => ['individual'], 'team_size_min' => 1, 'team_size_max' => 1, 'substitutes_max' => 0, 'active' => false,
                        'ruleset_notes' => 'UNRESOLVED (D1): exact ruleset (e.g. which 8-ball rules) must be confirmed before activation.'],
                    ['slug' => '9-ball', 'name' => '9-ball', 'registration_types' => ['individual'], 'team_size_min' => 1, 'team_size_max' => 1, 'substitutes_max' => 0, 'active' => true],
                    ['slug' => '10-ball', 'name' => '10-ball', 'registration_types' => ['individual'], 'team_size_min' => 1, 'team_size_max' => 1, 'substitutes_max' => 0, 'active' => true],
                    ['slug' => 'snooker', 'name' => 'Snooker', 'registration_types' => ['individual'], 'team_size_min' => 1, 'team_size_max' => 1, 'substitutes_max' => 0, 'active' => true],
                ],
            ],
            [
                'sport' => ['slug' => 'esports', 'name' => 'E-sports', 'name_translations' => ['ar' => 'الرياضات الإلكترونية'], 'kind' => 'esport', 'icon' => 'controller', 'team_member_limit' => 10, 'sort_order' => 4],
                'disciplines' => [
                    ['slug' => 'football-game', 'name' => 'Football video game', 'registration_types' => ['individual'], 'team_size_min' => 1, 'team_size_max' => 1, 'substitutes_max' => 0, 'active' => false,
                        'ruleset_notes' => 'Set the exact game title and version, platform, region and match format before activating.'],
                    ['slug' => 'rainbow-six-siege', 'name' => 'Rainbow Six Siege', 'game_title' => 'Rainbow Six Siege', 'registration_types' => ['team'], 'team_size_min' => 5, 'team_size_max' => 5, 'substitutes_max' => 2, 'active' => false,
                        'ruleset_notes' => 'Set version, platform, region and match format before activating.'],
                    ['slug' => 'valorant', 'name' => 'Valorant', 'game_title' => 'Valorant', 'registration_types' => ['team'], 'team_size_min' => 5, 'team_size_max' => 5, 'substitutes_max' => 2, 'active' => false,
                        'ruleset_notes' => 'Set platform, region and match format before activating.'],
                    ['slug' => 'counter-strike', 'name' => 'Counter-Strike', 'registration_types' => ['team'], 'team_size_min' => 5, 'team_size_max' => 5, 'substitutes_max' => 2, 'active' => false,
                        'ruleset_notes' => 'Set the exact title/version (do not assume), platform, region and match format before activating.'],
                ],
            ],
        ];

        foreach ($catalog as $i => $block) {
            $sport = Sport::firstOrCreate(['slug' => $block['sport']['slug']], $block['sport'] + ['is_active' => true]);

            foreach ($block['disciplines'] as $j => $d) {
                $active = $d['active'];
                unset($d['active']);

                Discipline::firstOrCreate(
                    ['sport_id' => $sport->id, 'slug' => $d['slug']],
                    $d + [
                        'is_active' => $active,
                        'ruleset_status' => $active ? 'confirmed' : 'needs_confirmation',
                        'competition_mode' => $sport->kind->value === 'esport' ? null : 'local',
                        'sort_order' => $j + 1,
                    ],
                );
            }
        }
    }
}
