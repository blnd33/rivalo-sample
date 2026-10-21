<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Support\FeatureFlags;
use Illuminate\Database\Seeder;

/**
 * Production-safe: creates flags if missing. Never flips an existing flag's value.
 */
class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            FeatureFlags::PAID_ENTRY => [
                'description' => 'Allow tournaments with an in-person entry fee to be published and registered for. '
                    .'Keep OFF until jurisdiction-specific legal review, payment handling review and app-store eligibility are confirmed (decision D7). '
                    .'Free tournaments work regardless.',
                'is_public' => true,
            ],
            FeatureFlags::STORE => [
                'description' => 'Show the Store tab and commerce APIs (Milestone 8). While off, the tab shows News.',
                'is_public' => true,
            ],
            FeatureFlags::MARKETPLACE => [
                'description' => 'Sell products from approved outside sellers in the store and let players apply to sell (Milestone 9). '
                    .'Needs store_enabled. Approved sellers can prepare listings in the Seller Hub while this is off.',
                'is_public' => true,
            ],
            FeatureFlags::PUSH => [
                'description' => 'Deliver push notifications through the configured provider (Milestone 6). The in-app inbox works regardless.',
                'is_public' => false,
            ],
        ];

        foreach ($flags as $key => $attributes) {
            FeatureFlag::firstOrCreate(['key' => $key], $attributes + ['enabled' => false]);
        }
    }
}
