<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\StaffRole;
use App\Enums\TournamentStatus;
use App\Models\Discipline;
use App\Models\Season;
use App\Models\Sport;
use App\Models\Tournament;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\TeamService;
use App\Services\TournamentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE DATA. Every row is flagged is_sample_data = true and every visible name is
 * prefixed with "[SAMPLE]" so it can never be mistaken for real activity. Refuses to run in production.
 *
 * Sample account password: taken from SAMPLE_DATA_PASSWORD in .env, or generated and printed once.
 */
class SampleDataSeeder extends Seeder
{
    public function run(TeamService $teams, TournamentService $tournaments, RegistrationService $registrations): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleDataSeeder must never run in production.');
        }
        if (User::where('is_sample_data', true)->exists()) {
            $this->command?->warn('Sample data already present. Skipping.');

            return;
        }

        $password = env('SAMPLE_DATA_PASSWORD') ?: Str::password(16, symbols: false);

        $make = function (string $username, string $display, ?RoleName $role = null) use ($password): User {
            $user = User::create([
                'username' => $username,
                'email' => $username.'@sample.rivalo.test',
                'password' => $password,
                'display_name' => '[SAMPLE] '.$display,
                'city' => 'Sample City',
            ]);
            $user->forceFill(['email_verified_at' => now(), 'is_sample_data' => true])->save();
            $user->assignRole(RoleName::Player->value);
            if ($role) {
                $user->assignRole($role->value);
            }

            return $user;
        };

        $admin = $make('sample_admin', 'Super Admin', RoleName::SuperAdmin);
        $organizer = $make('sample_organizer', 'Organizer', RoleName::TournamentOrganizer);
        $referee = $make('sample_referee', 'Referee', RoleName::Referee);
        $make('sample_support', 'Support', RoleName::SupportStaff);

        $players = collect(range(1, 12))->map(fn ($i) => $make(sprintf('sample_player%02d', $i), "Player {$i}"));

        $padel = Discipline::where('slug', 'doubles')->firstOrFail();
        $nineBall = Discipline::where('slug', '9-ball')->firstOrFail();
        $sevens = Discipline::where('slug', '7v7')->firstOrFail();
        foreach ($players as $i => $p) {
            $p->disciplines()->sync($i % 2 === 0 ? [$padel->id, $nineBall->id] : [$sevens->id, $nineBall->id]);
        }

        // Two padel pairs (two-player teams) with accepted members.
        $padelSport = Sport::where('slug', 'padel')->firstOrFail();
        $pairs = [];
        foreach ([[0, 1], [2, 3]] as $n => [$a, $b]) {
            $team = $teams->create($players[$a], $padelSport, '[SAMPLE] Padel Pair '.chr(65 + $n));
            $team->forceFill(['is_sample_data' => true])->save();
            $inv = $teams->invite($team, $players[$a], $players[$b]);
            $teams->acceptInvitation($inv, $players[$b]);
            $pairs[] = [$team, $players[$a], $players[$b]];
        }

        $season = Season::firstOrCreate(['name' => '[SAMPLE] Season 1'], [
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'status' => 'active',
        ]);

        $base = [
            'season_id' => $season->id,
            'timezone' => 'Asia/Baghdad',
            'city' => 'Sample City',
            'venue_name' => '[SAMPLE] Venue',
            'rules' => "SAMPLE RULES FOR DEVELOPMENT ONLY.\nReplace with the official published rules before any real event.",
            'entry_fee_minor' => 0,
            'currency' => 'IQD',
            'funding_model' => 'free',
        ];

        $padelCup = $tournaments->create($admin, array_merge($base, [
            'name' => '[SAMPLE] Padel Doubles Open',
            'discipline_id' => $padel->id,
            'registration_type' => 'pair',
            'min_entrants' => 4, 'max_entrants' => 8, 'roster_min' => 2, 'roster_max' => 2,
            'format' => 'single_elimination',
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(10),
            'starts_at' => now()->addDays(14)->setTime(17, 0),
            'ends_at' => now()->addDays(14)->setTime(23, 0),
            'description' => 'Development sample tournament. Not a real event.',
        ]));
        $tournaments->assignStaff($padelCup, $organizer, $admin, StaffRole::Organizer);
        $tournaments->assignStaff($padelCup, $referee, $admin, StaffRole::Referee);
        $tournaments->transition($padelCup, $admin, TournamentStatus::Published);
        $tournaments->transition($padelCup, $admin, TournamentStatus::RegistrationOpen);

        [$pairA, $capA, $mateA] = $pairs[0];
        $registrations->register($padelCup->fresh(), $capA, $pairA, [$capA->id, $mateA->id]);

        $cueNight = $tournaments->create($admin, array_merge($base, [
            'name' => '[SAMPLE] 9-Ball Night',
            'discipline_id' => $nineBall->id,
            'registration_type' => 'individual',
            'min_entrants' => 4, 'max_entrants' => 4, 'roster_min' => 1, 'roster_max' => 1,
            'format' => 'round_robin',
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(5),
            'starts_at' => now()->addDays(7)->setTime(19, 0),
            'description' => 'Development sample tournament with a small capacity to demonstrate the waitlist.',
        ]));
        $tournaments->assignStaff($cueNight, $organizer, $admin, StaffRole::Organizer);
        $tournaments->transition($cueNight, $admin, TournamentStatus::Published);
        $tournaments->transition($cueNight, $admin, TournamentStatus::RegistrationOpen);
        foreach ($players->take(5) as $p) {
            $registrations->register($cueNight->fresh(), $p); // 5th player is waitlisted
        }

        $sevensCup = $tournaments->create($admin, array_merge($base, [
            'name' => '[SAMPLE] 7v7 League Cup',
            'discipline_id' => $sevens->id,
            'registration_type' => 'team',
            'min_entrants' => 4, 'max_entrants' => 8, 'roster_min' => 7, 'roster_max' => 7, 'substitutes_max' => 5,
            'format' => 'groups_knockout',
            'registration_opens_at' => now()->addDays(3),
            'registration_closes_at' => now()->addDays(20),
            'starts_at' => now()->addDays(30)->setTime(16, 0),
            'description' => 'Development sample: published, registration not yet open.',
        ]));
        $tournaments->transition($sevensCup, $admin, TournamentStatus::Published);

        // A paid-entry draft demonstrates the compliance gate: it cannot be published while
        // paid_entry_participation is disabled.
        $paidDraft = $tournaments->create($admin, array_merge($base, [
            'name' => '[SAMPLE] Paid-Entry Draft (blocked by compliance flag)',
            'discipline_id' => $nineBall->id,
            'registration_type' => 'individual',
            'min_entrants' => 2, 'max_entrants' => 16, 'roster_min' => 1, 'roster_max' => 1,
            'format' => 'single_elimination',
            'entry_fee_minor' => 10000,
            'funding_model' => 'player_funded',
            'prize_rules' => 'SAMPLE ONLY. Prize rules must be finalized and published before registration.',
            'starts_at' => now()->addDays(40),
        ]));

        Tournament::whereKey([$padelCup->id, $cueNight->id, $sevensCup->id, $paidDraft->id])->update(['is_sample_data' => true]);

        $this->command?->info('Sample data created. All sample accounts use the password: '.$password);
        $this->command?->info('Sample emails: sample_admin@sample.rivalo.test, sample_organizer@…, sample_player01@… (see SampleDataSeeder).');
    }
}
