<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Enums\StaffRole;
use App\Enums\TournamentStatus;
use App\Models\PointsRuleSet;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\FixtureService;
use App\Services\PointsService;
use App\Services\ResultService;
use App\Services\TournamentService;
use App\Services\VenueService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE COMPETITION. Plays part of "[SAMPLE] 9-Ball Night" so fixtures, tables and
 * leaderboards can be seen in the app. The points table is named "[SAMPLE] … development only"
 * and is NOT the owner's decision D3. Refuses to run in production.
 */
class SampleCompetitionSeeder extends Seeder
{
    public function run(TournamentService $tournaments, VenueService $venue, FixtureService $fixtures, ResultService $results, PointsService $points): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleCompetitionSeeder must never run in production.');
        }
        if (PointsRuleSet::where('name', 'like', '[SAMPLE]%')->exists()) {
            $this->command?->warn('Sample competition already present. Skipping.');

            return;
        }

        $admin = User::where('username', 'sample_admin')->first();
        $organizer = User::where('username', 'sample_organizer')->first();
        $referee = User::where('username', 'sample_referee')->first();
        $night = Tournament::where('name', '[SAMPLE] 9-Ball Night')->first();
        if (! $admin || ! $organizer || ! $referee || ! $night || $night->status !== TournamentStatus::RegistrationOpen) {
            $this->command?->warn('Sample tournament not found or not open. Skipping sample competition.');

            return;
        }

        DB::transaction(fn () => $this->play($night, $admin, $organizer, $referee, $tournaments, $venue, $fixtures, $results, $points));
        $this->command?->info('Sample competition created for '.$night->name.'.');
    }

    private function play(Tournament $night, User $admin, User $organizer, User $referee, TournamentService $tournaments, VenueService $venue, FixtureService $fixtures, ResultService $results, PointsService $points): void
    {
        $tournaments->assignStaff($night, $referee, $admin, StaffRole::Referee);
        $night->forceFill(['ranking_eligible' => true])->save();

        $set = PointsRuleSet::create([
            'name' => '[SAMPLE] 9-ball points (development only)',
            'discipline_id' => $night->discipline_id,
            'notes' => 'SAMPLE for development. Not the official points table (decision D3 is open).',
        ]);
        foreach (['match_win' => 3, 'match_draw' => 1, 'participation' => 1, 'place_1' => 10, 'place_2' => 6, 'place_3' => 4] as $trigger => $value) {
            $set->rules()->create(['trigger' => $trigger, 'points' => $value, 'eligible_outcomes' => ['normal']]);
        }
        $points->publish($set, $admin, 'SAMPLE data for development, not an owner decision.');
        $points->assignToTournament($night, $set->fresh(), $admin);

        foreach ($night->entries()->where('registration_status', RegistrationStatus::Reserved->value)->where('check_in_status', '!=', 'checked_in')->get() as $entry) {
            $venue->checkIn($entry, $organizer);
        }
        $night = $tournaments->transition($night->fresh(), $admin, TournamentStatus::RegistrationClosed);
        $fixtures->generate($night, $organizer, ['draw_seed' => 2026]);
        $fixtures->publish($night, $organizer);
        $night = $tournaments->transition($night->fresh(), $admin, TournamentStatus::InProgress);

        // Round 1 played; round 2 has one result approved and one waiting for the referee.
        $scores = [[5, 3], [5, 4], [2, 5]];
        $matches = TournamentMatch::where('tournament_id', $night->id)->orderBy('round')->orderBy('position')->get();
        foreach ($matches->take(3) as $i => $m) {
            $results->submit($m, $referee, ['outcome' => 'normal', 'slot1' => $scores[$i][0], 'slot2' => $scores[$i][1], 'approve' => true]);
        }
        $pending = $matches->get(3);
        $player = $pending?->participants()->where('slot', 1)->first()?->entry?->activeMembers()->first()?->user;
        if ($pending && $player) {
            $results->submit($pending, $player, ['outcome' => 'normal', 'slot1' => 5, 'slot2' => 1, 'note' => 'Submitted by a player (sample)']);
        }
    }
}
