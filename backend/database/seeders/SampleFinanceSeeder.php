<?php

namespace Database\Seeders;

use App\Models\Sponsor;
use App\Models\SponsorContribution;
use App\Models\Tournament;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE FINANCE DATA. Sponsors are named "[SAMPLE] …" and flagged is_sample_data, so
 * they can never be mistaken for real sponsors. The finance rules are left as an empty draft
 * because decision D4 (prize and organizer formula) is still open.
 */
class SampleFinanceSeeder extends Seeder
{
    public function run(FinanceService $finance): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleFinanceSeeder must never run in production.');
        }
        if (Sponsor::where('is_sample_data', true)->exists()) {
            $this->command?->warn('Sample finance data already present. Skipping.');

            return;
        }

        $admin = User::where('username', 'sample_admin')->first();
        $padel = Tournament::where('name', '[SAMPLE] Padel Doubles Open')->first();
        if (! $admin || ! $padel) {
            $this->command?->warn('Run SampleDataSeeder first. Skipping sample finance data.');

            return;
        }

        $shown = Sponsor::create(['name' => '[SAMPLE] Sponsor A', 'description' => 'Development sample sponsor. Not a real company.', 'is_public' => true]);
        $hidden = Sponsor::create(['name' => '[SAMPLE] Sponsor B (not shown in app)', 'description' => 'Development sample sponsor without display consent.']);
        foreach ([$shown, $hidden] as $s) {
            $s->forceFill(['is_sample_data' => true])->save();
        }

        $prizeMoney = $finance->addContribution($padel, $shown, $admin, ['type' => 'cash', 'restriction' => 'prize_only', 'committed_minor' => 150000, 'note' => 'Sample pledge']);
        $finance->receiveContribution($prizeMoney, $admin, 100000, 'Sample: first instalment');
        $trophy = $finance->addContribution($padel, $shown, $admin, ['type' => 'in_kind', 'in_kind_description' => 'Trophy and medals (sample)']);
        $venue = $finance->addContribution($padel, $hidden, $admin, ['type' => 'cash', 'restriction' => 'expenses_only', 'committed_minor' => 40000]);
        SponsorContribution::whereKey([$prizeMoney->id, $trophy->id, $venue->id])->update(['is_sample_data' => true]);

        $finance->savePrize($padel, $admin, ['place' => 1, 'title' => 'Champions', 'basis' => 'fixed', 'amount_minor' => 100000, 'in_kind_description' => 'Trophy', 'label' => 'guaranteed', 'backing_contribution_id' => $prizeMoney->id]);
        $finance->savePrize($padel, $admin, ['place' => 2, 'title' => 'Runners-up', 'basis' => 'fixed', 'amount_minor' => 50000, 'label' => 'estimated']);
        $finance->savePrize($padel, $admin, ['place' => 3, 'basis' => 'fixed', 'in_kind_description' => 'Medals', 'label' => 'finalized']);

        // D4 is undecided: an empty draft, no shares, nothing approved.
        $finance->saveRules($padel, $admin, null, null, 'Waiting for decision D4 (prize and organizer formula).', 'Sample draft');

        $this->command?->info('Sample finance data created for '.$padel->name.'.');
    }
}
