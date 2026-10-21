<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe reference data always runs. Sample data runs only in local development.
     *   php artisan db:seed                                 (reference data + sample data when APP_ENV=local)
     *   php artisan db:seed --class=SampleDataSeeder        (sample data explicitly)
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            FeatureFlagSeeder::class,
            SportsCatalogSeeder::class,
            AchievementsSeeder::class,
            PolicyPagesSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call([SampleDataSeeder::class, SampleNewsSeeder::class, SampleFinanceSeeder::class, SampleCompetitionSeeder::class, SampleEngagementSeeder::class, SampleSafetySeeder::class, SampleStoreSeeder::class, SampleMarketplaceSeeder::class]);
        }
    }
}
