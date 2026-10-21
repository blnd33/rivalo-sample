<?php

namespace Database\Seeders;

use App\Enums\Fulfillment;
use App\Enums\PayoutMethod;
use App\Enums\RoleName;
use App\Enums\SellerApplicationStatus;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\SellerLedgerEntry;
use App\Models\Sport;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\SellerLedgerService;
use App\Services\SellerService;
use App\Services\StoreService;
use App\Support\FeatureFlags;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE (Milestone 9): two fictional "[SAMPLE]" sellers approved through the real
 * service, their products (one on sale, one waiting for review each), one completed order so the
 * statement has a sale, one recorded payout, and one pending application.
 * The commission is a placeholder for development, not a business decision (D22).
 * Switches marketplace_enabled ON for this development database only. Refuses to run in production.
 * Safe to run again: every step skips what already exists.
 */
class SampleMarketplaceSeeder extends Seeder
{
    private const SHOPS = [
        ['sample_seller_cues', '[SAMPLE] Corner Pocket Cues', 'cue-sports', 'Billiard cues, cases and chalk.', [
            ['corner-pocket-maple-cue', '[SAMPLE] Maple cue 57"', '[SAMPLE] عصا بلياردو من خشب القيقب', '[SAMPLE] Çovê bilyardoyê ji darê mêşeyê', 'equipment', 60000, 'active', [['19 oz', 3], ['20 oz', 2]]],
            ['corner-pocket-cue-case', '[SAMPLE] Cue case', '[SAMPLE] حقيبة عصا', '[SAMPLE] Çenteyê çovê', 'accessories', 30000, 'pending_review', [['One size', 0]]],
        ]],
        ['sample_seller_padel', '[SAMPLE] Padel Point Supplies', 'padel', 'Padel grips, wristbands and bags.', [
            ['padel-point-overgrip', '[SAMPLE] Overgrip (3)', '[SAMPLE] لفافة مقبض (3)', '[SAMPLE] Pêçana destikê (3)', 'equipment', 6000, 'active', [['Pack of 3', 15]]],
            ['padel-point-wristband', '[SAMPLE] Wristbands (2)', '[SAMPLE] أساور معصم (2)', '[SAMPLE] Bazinên zendê (2)', 'apparel', 5000, 'pending_review', [['One size', 0]]],
        ]],
    ];

    public function run(SellerService $sellers, InventoryService $inventory, StoreService $store, OrderService $orders, SellerLedgerService $ledger): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleMarketplaceSeeder must never run in production.');
        }
        $admin = User::where('username', 'sample_admin')->first();
        $buyer = User::where('username', 'sample_player01')->first();
        $applicant = User::where('username', 'sample_player02')->first();
        if (! $admin || ! $buyer) {
            return;
        }

        FeatureFlag::where('key', FeatureFlags::MARKETPLACE)->update(['enabled' => true]);
        FeatureFlags::flush();
        $password = env('SAMPLE_DATA_PASSWORD') ?: Str::password(16, symbols: false);

        DB::transaction(function () use ($sellers, $inventory, $admin, $password) {
            foreach (self::SHOPS as [$username, $shopName, $sportSlug, $bio, $catalog]) {
                $seller = $this->shop($sellers, $admin, $password, $username, $shopName, $bio);
                $this->products($inventory, $admin, $seller, $sportSlug, $catalog);
            }
        });

        // One completed pickup order through the real services, so the first shop's statement has a
        // sale, and a recorded payout of half of it.
        $first = Seller::whereHas('user', fn ($q) => $q->where('username', self::SHOPS[0][0]))->first();
        $pickup = PickupLocation::where('is_active', true)->first();
        if ($first && $pickup && FeatureFlags::enabled(FeatureFlags::STORE) && ! SellerLedgerEntry::where('seller_id', $first->id)->exists()) {
            DB::transaction(function () use ($first, $pickup, $buyer, $admin, $store, $orders, $ledger) {
                $variant = ProductVariant::whereHas('product', fn ($q) => $q->where('seller_id', $first->id)->where('status', 'active'))
                    ->where('stock', '>', 0)->orderBy('id')->firstOrFail();
                $store->setQuantity($buyer, $variant, 1);
                $order = $store->checkout($buyer, ['fulfillment' => Fulfillment::Pickup->value, 'pickup_location_id' => $pickup->id]);
                $orders->confirm($order, $admin);
                $orders->markReady($order, $admin);
                $orders->recordPayment($order, $admin);
                $orders->complete($order, $admin);
                Order::whereKey($order->id)->update(['is_sample_data' => true]);
                $ledger->recordPayout($first, $admin, intdiv($ledger->summary($first)['owed'], 2), PayoutMethod::Cash, '[SAMPLE] receipt', now());
            });
        }

        if ($applicant && ! Seller::where('user_id', $applicant->id)->exists()
            && ! SellerApplication::where('user_id', $applicant->id)->where('status', SellerApplicationStatus::Pending->value)->exists()) {
            $application = $sellers->apply($applicant, [
                'business_name' => '[SAMPLE] Five-a-side Kit Co.',
                'contact_phone' => '+000 000 0001',
                'city' => 'Sample City',
                'categories' => ['jerseys', 'apparel'],
                'description' => '[SAMPLE] Fictional application for development: custom team kits and training wear.',
            ]);
            $application->forceFill(['is_sample_data' => true])->save();
        }

        $this->command?->info('Sample marketplace created and marketplace_enabled switched on (development database only).');
    }

    private function shop(SellerService $sellers, User $admin, string $password, string $username, string $shopName, string $bio): Seller
    {
        $user = User::where('username', $username)->first();
        if (! $user) {
            $user = User::create([
                'username' => $username,
                'email' => $username.'@sample.rivalo.test',
                'password' => $password,
                'display_name' => $shopName,
                'city' => 'Sample City',
            ]);
            $user->forceFill(['email_verified_at' => now(), 'is_sample_data' => true])->save();
            $user->assignRole(RoleName::Player->value);
        }
        if ($seller = Seller::where('user_id', $user->id)->first()) {
            return $seller;
        }

        $application = SellerApplication::where('user_id', $user->id)->where('status', SellerApplicationStatus::Pending->value)->first()
            ?? $sellers->apply($user, [
                'business_name' => $shopName,
                'contact_phone' => '+000 000 0000',
                'city' => 'Sample City',
                'categories' => ['equipment', 'accessories'],
                'description' => '[SAMPLE] Fictional shop for development. '.$bio,
            ]);
        $application->forceFill(['is_sample_data' => true])->save();
        $seller = $sellers->approve($application, $admin, [
            'name' => $shopName,
            'commission_bps' => 1500,
            'agreement_reference' => '[SAMPLE] No real agreement',
            'verification_note' => '[SAMPLE] Development data. Nothing was checked.',
            'identity_checked' => true,
        ]);
        $seller->forceFill(['is_sample_data' => true, 'bio_translations' => ['en' => '[SAMPLE] '.$bio]])->save();

        return $seller;
    }

    private function products(InventoryService $inventory, User $admin, Seller $seller, string $sportSlug, array $catalog): void
    {
        $sportId = Sport::where('slug', $sportSlug)->value('id');
        foreach ($catalog as $i => [$slug, $en, $ar, $kmr, $category, $price, $status, $sizes]) {
            if (Product::where('slug', $slug)->exists()) {
                continue;
            }
            $product = Product::create([
                'seller_id' => $seller->id,
                'slug' => $slug,
                'name_translations' => ['en' => $en, 'ar' => $ar, 'kmr' => $kmr],
                'description_translations' => ['en' => 'Sample seller product for development. Not a real item or price.'],
                'category' => $category,
                'sport_id' => $sportId,
                'price_minor' => $price,
                'currency' => StoreService::currency(),
                'status' => $status,
                'sort_order' => 10 + $i,
                'submitted_at' => now(),
                'reviewed_at' => $status === 'active' ? now() : null,
                'reviewed_by_id' => $status === 'active' ? $admin->id : null,
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
                    $inventory->restock($variant, $admin, $stock, '[SAMPLE] stock brought in by the seller');
                }
            }
        }
    }
}
