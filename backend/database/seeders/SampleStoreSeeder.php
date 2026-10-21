<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\FeatureFlag;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sport;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StoreService;
use App\Support\FeatureFlags;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE: "[SAMPLE]" products with sizes and stock, one pickup point and one
 * delivery area, and the store_enabled flag switched ON for this development database only.
 * Prices are placeholders, not a pricing decision. Refuses to run in production.
 */
class SampleStoreSeeder extends Seeder
{
    public function run(InventoryService $inventory): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleStoreSeeder must never run in production.');
        }
        if (Product::where('is_sample_data', true)->exists()) {
            $this->command?->warn('Sample store data already present. Skipping.');

            return;
        }
        $admin = User::where('username', 'sample_admin')->first();
        if (! $admin) {
            return;
        }

        DB::transaction(function () use ($inventory, $admin) {
            $currency = StoreService::currency();
            $football = Sport::where('slug', 'football')->first();
            $padel = Sport::where('slug', 'padel')->first();
            $cue = Sport::where('slug', 'cue-sports')->first();

            $catalog = [
                ['rivalo-home-jersey', '[SAMPLE] RIVALO home jersey', '[SAMPLE] قميص ريفالو الأساسي', '[SAMPLE] Kirasa malê ya RIVALO', 'jerseys', $football?->id, 25000, true,
                    [['S', 4], ['M', 8], ['L', 2], ['XL', 0]]],
                ['rivalo-training-tee', '[SAMPLE] Training T-shirt', '[SAMPLE] قميص تدريب', '[SAMPLE] Tîşorta perwerdeyê', 'apparel', null, 15000, false,
                    [['M', 10], ['L', 10]]],
                ['rivalo-cap', '[SAMPLE] RIVALO cap', '[SAMPLE] قبعة ريفالو', '[SAMPLE] Kumika RIVALO', 'accessories', null, 10000, false,
                    [['One size', 12]]],
                ['padel-balls-3', '[SAMPLE] Padel balls (3)', '[SAMPLE] كرات بادل (3)', '[SAMPLE] Topên padelê (3)', 'equipment', $padel?->id, 8000, false,
                    [['Pack of 3', 20]]],
                ['cue-chalk', '[SAMPLE] Cue chalk (2)', '[SAMPLE] طباشير العصا (2)', '[SAMPLE] Tebaşîra çovê (2)', 'equipment', $cue?->id, 4000, false,
                    [['Pack of 2', 3]]],
            ];

            foreach ($catalog as $i => [$slug, $en, $ar, $kmr, $category, $sportId, $price, $featured, $sizes]) {
                $product = Product::create([
                    'slug' => $slug,
                    'name_translations' => ['en' => $en, 'ar' => $ar, 'kmr' => $kmr],
                    'description_translations' => ['en' => 'Sample product for development. Not a real item or price.'],
                    'category' => $category,
                    'sport_id' => $sportId,
                    'price_minor' => $price,
                    'currency' => $currency,
                    'status' => 'active',
                    'is_featured' => $featured,
                    'sort_order' => $i,
                ]);
                $product->forceFill(['is_sample_data' => true])->save();

                foreach ($sizes as $j => [$label, $stock]) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => strtoupper(substr(md5($slug), 0, 6)).'-'.strtoupper(str_replace(' ', '', $label)),
                        'option_label' => $label,
                        'sort_order' => $j,
                    ]);
                    if ($stock > 0) {
                        $inventory->restock($variant, $admin, $stock, '[SAMPLE] opening stock');
                    }
                }
            }

            $pickup = PickupLocation::create(['name' => '[SAMPLE] RIVALO desk at the venue', 'address' => '[SAMPLE] Example street 1', 'opening_hours' => 'Sat–Thu 16:00–22:00']);
            $pickup->forceFill(['is_sample_data' => true])->save();
            $zone = DeliveryZone::create(['name' => '[SAMPLE] City centre', 'fee_minor' => 3000, 'estimate' => '1–2 days']);
            $zone->forceFill(['is_sample_data' => true])->save();

            // Development only: lets the app show the Store tab against this database.
            FeatureFlag::where('key', FeatureFlags::STORE)->update(['enabled' => true]);
            FeatureFlags::flush();
        });

        $this->command?->info('Sample store created and store_enabled switched on (development database only).');
    }
}
