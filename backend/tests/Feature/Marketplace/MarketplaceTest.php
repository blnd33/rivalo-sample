<?php

namespace Tests\Feature\Marketplace;

use App\Enums\PayoutMethod;
use App\Enums\RoleName;
use App\Exceptions\DomainRuleException;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\SellerApplications\Pages\ViewSellerApplication;
use App\Filament\Resources\SellerApplications\SellerApplicationResource;
use App\Filament\Resources\Sellers\Pages\ViewSeller;
use App\Filament\Resources\Sellers\SellerResource;
use App\Filament\Seller\Pages\SellerDashboard;
use App\Filament\Seller\Pages\ShopProfile;
use App\Filament\Seller\Resources\Listings\ListingResource;
use App\Filament\Seller\Resources\Listings\Pages\CreateListing;
use App\Filament\Seller\Resources\Listings\Pages\EditListing;
use App\Filament\Seller\Resources\Listings\Pages\ListListings;
use App\Filament\Seller\Resources\Sales\Pages\ListSales;
use App\Filament\Seller\Resources\Sales\SaleResource;
use App\Filament\Seller\Resources\Statement\Pages\ListStatement;
use App\Filament\Seller\Resources\Statement\StatementResource;
use App\Filament\Seller\Resources\StockHistory\StockHistoryResource;
use App\Filament\Seller\Widgets\SellerOverview;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AccountService;
use App\Services\InventoryService;
use App\Services\ListingService;
use App\Services\OrderService;
use App\Services\SellerLedgerService;
use App\Services\SellerService;
use App\Services\StoreService;
use App\Support\FeatureFlags;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    private User $manager;

    private User $admin;

    private PickupLocation $pickup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flags(store: true, marketplace: true);
        $this->manager = $this->withMfa($this->staff(RoleName::StoreManager));
        $this->admin = $this->withMfa($this->staff(RoleName::SuperAdmin));
        $this->pickup = PickupLocation::create(['name' => 'Venue desk', 'address' => 'Main street 1']);
    }

    private function flags(bool $store = true, bool $marketplace = true): void
    {
        FeatureFlag::where('key', FeatureFlags::STORE)->update(['enabled' => $store]);
        FeatureFlag::where('key', FeatureFlags::MARKETPLACE)->update(['enabled' => $marketplace]);
        FeatureFlags::flush();
    }

    private function withMfa(User $user): User
    {
        $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        return $user;
    }

    private function expectRule(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected domain rule [{$code}].");
        } catch (DomainRuleException $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());
        }
    }

    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function applicationData(array $extra = []): array
    {
        return $extra + [
            'business_name' => 'Corner Pocket', 'contact_phone' => '+964 750 000 0000', 'city' => 'Duhok',
            'categories' => ['equipment'], 'description' => 'Billiard cues, cases and chalk for local clubs.',
        ];
    }

    private function approveData(array $extra = []): array
    {
        return $extra + ['name' => 'Corner Pocket', 'commission_bps' => 1500, 'agreement_reference' => 'AGR-2026-01',
            'verification_note' => 'Saw business registration and ID card.', 'identity_checked' => true];
    }

    /** An approved seller (through the services) with its owner account. */
    private function seller(int $bps = 1500, string $name = 'Corner Pocket'): Seller
    {
        $owner = $this->player();
        $application = app(SellerService::class)->apply($owner, $this->applicationData(['business_name' => $name]));

        return app(SellerService::class)->approve($application, $this->manager, $this->approveData(['name' => $name, 'commission_bps' => $bps]));
    }

    /** A seller listing with sizes => stock, optionally approved and on sale. */
    private function listing(Seller $seller, array $sizes = ['One size' => 5], int $price = 10000, bool $approve = true): Product
    {
        $product = Product::create([
            'seller_id' => $seller->id, 'slug' => 'listing-'.uniqid(), 'name_translations' => ['en' => 'Maple cue'], 'category' => 'equipment',
            'price_minor' => $price, 'currency' => StoreService::currency(), 'status' => 'draft',
        ]);
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/test.jpg']);
        foreach ($sizes as $label => $stock) {
            $v = ProductVariant::create(['product_id' => $product->id, 'sku' => 'S-'.uniqid().$label, 'option_label' => (string) $label]);
            if ($stock > 0) {
                app(InventoryService::class)->restock($v, $this->manager, $stock);
            }
        }
        if ($approve) {
            app(ListingService::class)->submit($product, $seller->user);
            app(ListingService::class)->approve($product, $this->manager);
        }

        return $product->fresh();
    }

    private function completedOrder(User $buyer, ProductVariant $v, int $qty = 1): Order
    {
        app(StoreService::class)->setQuantity($buyer, $v, $qty);
        $order = app(StoreService::class)->checkout($buyer, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id]);
        $orders = app(OrderService::class);
        $orders->confirm($order, $this->manager);
        $orders->markReady($order, $this->manager);
        $orders->recordPayment($order, $this->manager);

        return $orders->complete($order, $this->manager);
    }

    private function enterHub(Seller $seller): void
    {
        Filament::setCurrentPanel(Filament::getPanel('seller'));
        $this->actingAs($this->withMfa($seller->user));
    }

    // ---------------------------------------------------------------- Applications

    public function test_players_apply_in_the_app_only_while_the_marketplace_is_open(): void
    {
        $player = $this->player();
        $this->flags(store: true, marketplace: false);
        $this->actingAs($player, 'sanctum')->getJson('/api/v1/me/selling')->assertOk()->assertJsonPath('data.open', false)->assertJsonPath('data.can_apply', false);
        $this->actingAs($player, 'sanctum')->postJson('/api/v1/me/seller-application', $this->applicationData())->assertNotFound()->assertJsonPath('code', 'marketplace_closed');

        $this->flags();
        $this->actingAs($player, 'sanctum')->postJson('/api/v1/me/seller-application', ['business_name' => 'X'])->assertStatus(422);
        $this->actingAs($player, 'sanctum')->postJson('/api/v1/me/seller-application', $this->applicationData())
            ->assertCreated()->assertJsonPath('data.application.status', 'pending')->assertJsonPath('data.can_apply', false);
        $this->actingAs($player, 'sanctum')->postJson('/api/v1/me/seller-application', $this->applicationData())->assertStatus(409)->assertJsonPath('code', 'application_pending');
        $this->actingAs($player, 'sanctum')->postJson('/api/v1/me/seller-application/withdraw')->assertOk()
            ->assertJsonPath('data.application.status', 'withdrawn')->assertJsonPath('data.can_apply', true);

        $unverified = $this->player(['email_verified_at' => null]);
        $this->actingAs($unverified, 'sanctum')->postJson('/api/v1/me/seller-application', $this->applicationData())->assertForbidden()->assertJsonPath('code', 'email_unverified');
    }

    public function test_approval_needs_the_agreement_terms_and_never_reviews_your_own_shop(): void
    {
        $owner = $this->player();
        $application = app(SellerService::class)->apply($owner, $this->applicationData());
        $sellers = app(SellerService::class);

        $this->expectRule('forbidden', fn () => $sellers->approve($application, $this->staff(RoleName::ContentEditor), $this->approveData()));
        $this->expectRule('verification_required', fn () => $sellers->approve($application, $this->manager, $this->approveData(['identity_checked' => false])));
        $this->expectRule('agreement_required', fn () => $sellers->approve($application, $this->manager, $this->approveData(['agreement_reference' => ''])));
        $this->expectRule('invalid_commission', fn () => $sellers->approve($application, $this->manager, $this->approveData(['commission_bps' => 10001])));

        $ownApplication = app(SellerService::class)->apply($this->manager, $this->applicationData());
        $this->expectRule('conflict_of_interest', fn () => $sellers->approve($ownApplication, $this->manager, $this->approveData()));

        $seller = $sellers->approve($application, $this->manager, $this->approveData());
        $this->assertTrue($owner->fresh()->hasRole(RoleName::Seller->value));
        $this->assertSame([1500, 'AGR-2026-01', $this->manager->id], [$seller->commission_bps, $seller->agreement_reference, $seller->approved_by_id]);
        $this->assertSame('Welcome, Corner Pocket!', UserNotification::where('user_id', $owner->id)->where('type', 'seller_application_approved')->first()->title);
        $this->expectRule('application_closed', fn () => $sellers->reject($application, $this->manager, 'Twice'));

        $other = $this->player();
        $rejected = $sellers->reject($sellers->apply($other, $this->applicationData()), $this->manager, 'We already stock these brands.');
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/me/selling')
            ->assertJsonPath('data.application.status', 'rejected')
            ->assertJsonPath('data.application.decision_note', 'We already stock these brands.')
            ->assertJsonPath('data.can_apply', true);
        $this->assertSame('rejected', $rejected->status->value);
    }

    // ---------------------------------------------------------------- Listings

    public function test_listings_go_on_sale_only_after_staff_approval(): void
    {
        $seller = $this->seller();
        $product = $this->listing($seller, ['M' => 3], approve: false);
        $listings = app(ListingService::class);

        ProductImage::where('product_id', $product->id)->delete();
        $this->expectRule('listing_incomplete', fn () => $listings->submit($product, $seller->user));
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/test.jpg']);
        $this->expectRule('not_found', fn () => $listings->submit($product, $this->player()));

        $listings->submit($product, $seller->user);
        $this->getJson("/api/v1/store/products/{$product->slug}")->assertNotFound();
        $this->expectRule('conflict_of_interest', fn () => $listings->approve($product, $this->withSellerRole($seller->user)));

        $listings->approve($product, $this->manager);
        $this->getJson("/api/v1/store/products/{$product->slug}")->assertOk()
            ->assertJsonPath('data.seller', ['slug' => $seller->slug, 'name' => 'Corner Pocket']);
        $this->getJson('/api/v1/store/products?official=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/store/products?seller={$seller->slug}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/store/sellers')->assertOk()->assertJsonPath('data.0.slug', $seller->slug)->assertJsonPath('data.0.product_count', 1);
        $this->getJson("/api/v1/store/sellers/{$seller->slug}")->assertOk()->assertJsonCount(1, 'data.products');
        $this->assertNotNull(UserNotification::where('user_id', $seller->user_id)->where('type', 'seller_listing_approved')->first());

        $listings->requestChanges($product, $this->manager, 'Use a photo of the real cue.');
        $this->assertSame(['changes_requested', 'Use a photo of the real cue.'], [$product->fresh()->status->value, $product->fresh()->review_note]);
        $this->getJson("/api/v1/store/products/{$product->slug}")->assertNotFound();

        $listings->submit($product, $seller->user);
        $listings->approve($product, $this->manager);
        $listings->withdraw($product, $seller->user);
        $this->assertSame('draft', $product->fresh()->status->value);
        $this->getJson("/api/v1/store/products/{$product->slug}")->assertNotFound();
    }

    /** A staff member who also sells: still can't review their own shop. */
    private function withSellerRole(User $user): User
    {
        $user->assignRole(RoleName::StoreManager->value);

        return $user->fresh();
    }

    public function test_paused_shops_and_a_closed_marketplace_take_seller_products_off_sale(): void
    {
        $seller = $this->seller();
        $product = $this->listing($seller);
        $official = Product::create(['slug' => 'scarf', 'name_translations' => ['en' => 'Scarf'], 'category' => 'accessories', 'price_minor' => 5000, 'currency' => StoreService::currency(), 'status' => 'active']);
        $buyer = $this->player();
        app(StoreService::class)->setQuantity($buyer, $product->variants->first(), 1);

        app(SellerService::class)->suspend($seller, $this->manager, 'Checking a complaint');
        $this->getJson("/api/v1/store/products/{$product->slug}")->assertNotFound();
        $this->getJson("/api/v1/store/sellers/{$seller->slug}")->assertNotFound();
        $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/me/bag')->assertJsonPath('data.lines.0.status', 'unavailable')->assertJsonPath('data.ready', false);
        $this->assertSame('Checking a complaint', UserNotification::where('user_id', $seller->user_id)->where('type', 'seller_suspended')->first()->body);

        app(SellerService::class)->reinstate($seller, $this->manager);
        $this->asGuest()->getJson("/api/v1/store/products/{$product->slug}")->assertOk();

        $this->flags(store: true, marketplace: false);
        $this->getJson("/api/v1/store/products/{$product->slug}")->assertNotFound();
        $this->getJson('/api/v1/store/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $official->slug);
        $this->getJson('/api/v1/store/sellers')->assertNotFound();
    }

    // ---------------------------------------------------------------- Money

    public function test_completed_sales_post_to_the_statement_at_the_commission_agreed_when_ordered(): void
    {
        $seller = $this->seller(1250);
        $variant = $this->listing($seller, ['One size' => 10], 10333)->variants->first();
        $buyer = $this->player();

        // Placed at 12.5 %, then the commission changes: the order keeps the old rate.
        app(StoreService::class)->setQuantity($buyer, $variant, 3);
        $order = app(StoreService::class)->checkout($buyer, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id]);
        app(SellerService::class)->changeCommission($seller, $this->manager, 2000, 'AGR-2026-02');
        $this->assertSame([$seller->id, 1250], [$order->items->first()->seller_id, $order->items->first()->commission_bps]);
        $this->assertSame(0, SellerLedgerEntry::count(), 'Nothing is owed before the customer has paid and collected.');

        $orders = app(OrderService::class);
        $orders->confirm($order, $this->manager);
        $orders->markReady($order, $this->manager);
        $orders->recordPayment($order, $this->manager);
        $orders->complete($order, $this->manager);

        $sale = SellerLedgerEntry::sole();
        $gross = 3 * 10333; // 30999
        $commission = intdiv($gross * 1250, 10000); // 3874 (rounded down, in the seller's favour)
        $this->assertSame(['sale', $gross, $commission, $gross - $commission], [$sale->type->value, $sale->gross_minor, $sale->commission_minor, $sale->amount_minor]);
        app(SellerLedgerService::class)->postSales($order, $this->manager->id);
        $this->assertSame(1, SellerLedgerEntry::count(), 'Posting is idempotent.');
        $this->assertSame($gross - $commission, $seller->fresh()->balance());

        // Cancelled orders and official products never touch a seller statement.
        app(StoreService::class)->setQuantity($buyer, $variant, 1);
        $cancelled = app(StoreService::class)->checkout($buyer, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id]);
        $orders->cancel($cancelled, $this->manager, 'Changed mind');
        $this->assertSame(1, SellerLedgerEntry::count());
    }

    public function test_returns_reverse_the_sale_but_a_refund_without_a_return_does_not(): void
    {
        $seller = $this->seller(1000);
        $variant = $this->listing($seller, ['One size' => 5], 20000)->variants->first();
        $order = $this->completedOrder($this->player(), $variant, 2);
        $item = $order->items()->first();
        $this->assertSame(36000, $seller->balance());

        $orders = app(OrderService::class);
        $orders->refund($order, $this->manager, 5000, 'Scratched, customer kept it');
        $this->assertSame(1, SellerLedgerEntry::count(), 'Goodwill refunds are RIVALO\'s unless staff record an adjustment.');

        $orders->refund($order->fresh(), $this->manager, 15000, 'Returned unused', [$item->id => 1]);
        $return = SellerLedgerEntry::where('type', 'return')->sole();
        $this->assertSame([-20000, -2000, -18000, 1], [$return->gross_minor, $return->commission_minor, $return->amount_minor, $return->quantity]);
        $this->assertSame(18000, $seller->fresh()->balance());
        $this->assertSame(4, $variant->fresh()->stock, 'The returned item is back in the stock RIVALO holds.');
    }

    public function test_payouts_are_recorded_by_finance_staff_and_never_exceed_what_is_owed(): void
    {
        $seller = $this->seller(1500);
        $this->completedOrder($this->player(), $this->listing($seller, ['One size' => 5], 10000)->variants->first(), 2);
        $ledger = app(SellerLedgerService::class);
        $this->assertSame(17000, $seller->balance());

        $this->expectRule('forbidden', fn () => $ledger->recordPayout($seller, $this->manager, 1000, PayoutMethod::Cash, null, now()));
        $this->expectRule('payout_exceeds_balance', fn () => $ledger->recordPayout($seller, $this->admin, 17001, PayoutMethod::Cash, null, now()));
        $this->expectRule('reference_required', fn () => $ledger->recordPayout($seller, $this->admin, 1000, PayoutMethod::BankTransfer, '', now()));
        $this->expectRule('invalid_date', fn () => $ledger->recordPayout($seller, $this->admin, 1000, PayoutMethod::Cash, null, Carbon::tomorrow()));

        $payout = $ledger->recordPayout($seller, $this->admin, 12000, PayoutMethod::BankTransfer, 'TRX-55', now());
        $this->assertSame([-12000, sprintf('PO-%06d', $payout->id)], [$payout->amount_minor, $payout->payoutNumber()]);
        $this->assertSame(5000, $seller->fresh()->balance());
        $this->assertStringContainsString('12,000', UserNotification::where('user_id', $seller->user_id)->where('type', 'seller_payout_recorded')->first()->body);

        $ledger->recordAdjustment($seller, $this->admin, -2000, 'Damaged item, seller agreed to bear it');
        $this->assertSame(['sales' => 20000, 'commission' => 3000, 'returns' => 0, 'adjustments' => -2000, 'paid' => 12000, 'owed' => 3000],
            array_intersect_key($ledger->summary($seller), array_flip(['owed', 'sales', 'commission', 'returns', 'adjustments', 'paid'])));

        $this->expectException(LogicException::class);
        $payout->update(['amount_minor' => -1]);
    }

    // ---------------------------------------------------------------- Privacy and stock

    public function test_public_responses_never_show_private_seller_fields(): void
    {
        $seller = $this->seller();
        $product = $this->listing($seller);
        $seller->update(['contact_phone' => '+964 750 123 4567', 'business_address' => 'Secret street 9']);

        foreach (["/api/v1/store/sellers/{$seller->slug}", "/api/v1/store/products/{$product->slug}", '/api/v1/store/sellers'] as $url) {
            $body = $this->getJson($url)->assertOk()->getContent();
            foreach (['contact_phone', '750 123', 'Secret street', 'commission', 'AGR-2026', 'verification', 'Saw business'] as $secret) {
                $this->assertStringNotContainsString($secret, $body, "{$url} leaks {$secret}");
            }
        }
    }

    public function test_unsold_stock_goes_back_to_the_seller_and_closing_archives_the_shop(): void
    {
        $seller = $this->seller();
        $variant = $this->listing($seller, ['One size' => 4])->variants->first();
        $official = Product::create(['slug' => 'scarf', 'name_translations' => ['en' => 'Scarf'], 'category' => 'accessories', 'price_minor' => 5000, 'currency' => StoreService::currency(), 'status' => 'active']);
        $officialVariant = ProductVariant::create(['product_id' => $official->id, 'sku' => 'SCARF', 'option_label' => 'One size']);
        $inventory = app(InventoryService::class);

        $this->expectRule('not_a_listing', fn () => $inventory->returnToSeller($officialVariant, $this->manager, 1));
        $this->expectRule('insufficient_stock', fn () => $inventory->returnToSeller($variant, $this->manager, 5));
        $inventory->returnToSeller($variant, $this->manager, 3, 'Collected by the owner');
        $this->assertSame([1, 1], [$variant->fresh()->stock, $inventory->ledgerBalance($variant)]);

        app(SellerService::class)->close($seller, $this->manager, 'Agreement ended');
        $this->assertSame(['closed', 'archived'], [$seller->fresh()->status->value, $variant->product->fresh()->status->value]);
        $this->expectRule('invalid_seller_transition', fn () => app(SellerService::class)->reinstate($seller, $this->manager));
    }

    public function test_deleting_the_account_closes_the_shop_and_keeps_the_statement(): void
    {
        $seller = $this->seller();
        $this->completedOrder($this->player(), $this->listing($seller)->variants->first());
        $seller->update(['contact_phone' => '+964 750 123 4567']);

        app(AccountService::class)->anonymize($seller->user);

        $seller->refresh();
        $this->assertSame(['closed', null, null], [$seller->status->value, $seller->contact_phone, $seller->contact_email]);
        $this->assertSame(1, SellerLedgerEntry::where('seller_id', $seller->id)->count());
        $this->assertSame(0, SellerApplication::where('user_id', $seller->user_id)->count());
    }

    // ---------------------------------------------------------------- Seller Hub

    public function test_only_sellers_enter_the_seller_hub_and_sellers_never_enter_the_admin_panel(): void
    {
        $hub = Filament::getPanel('seller');
        $seller = $this->seller();

        $this->assertTrue($hub->isMultiFactorAuthenticationRequired());
        $this->assertTrue($seller->user->fresh()->canAccessPanel($hub));
        $this->assertFalse($seller->user->fresh()->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($this->player()->canAccessPanel($hub));
        $this->assertFalse($this->admin->canAccessPanel($hub), 'Staff without a shop have nothing to do in the Seller Hub.');

        $owner = $this->withMfa($seller->user->fresh());
        $mine = $this->listing($seller);
        $theirs = $this->listing($this->seller(name: 'Padel Point'));
        $this->actingAs($owner);
        foreach ([SellerDashboard::getUrl(panel: 'seller'), ListingResource::getUrl('index', panel: 'seller'), ListingResource::getUrl('create', panel: 'seller'),
            ListingResource::getUrl('view', ['record' => $mine], panel: 'seller'), SaleResource::getUrl(panel: 'seller'),
            StatementResource::getUrl(panel: 'seller'), StockHistoryResource::getUrl(panel: 'seller'), ShopProfile::getUrl(panel: 'seller')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(ListingResource::getUrl('view', ['record' => $theirs], panel: 'seller'))->assertNotFound();
        $this->get(ListingResource::getUrl('edit', ['record' => $mine], panel: 'seller'))->assertForbidden(); // on sale: take it off sale first
        $this->get('/admin')->assertForbidden();
    }

    public function test_the_seller_hub_shows_only_your_own_shop_and_no_customer_details(): void
    {
        $seller = $this->seller(1000);
        $other = $this->seller(name: 'Padel Point');
        $buyer = $this->player(['display_name' => 'Customer Person']);
        $this->completedOrder($buyer, $this->listing($seller)->variants->first());
        $this->completedOrder($buyer, $this->listing($other)->variants->first());

        $this->enterHub($seller);
        // The dashboard widget loads in its own Livewire request and must still know the shop.
        Livewire::test(SellerOverview::class)->assertSee('Corner Pocket')->assertSee('9,000')->assertDontSee('No shop is linked');
        Livewire::test(ListListings::class)->assertCountTableRecords(1);
        Livewire::test(ListSales::class)->assertCountTableRecords(1)->assertDontSee('Customer Person')->assertDontSee($buyer->email);
        Livewire::test(ListStatement::class)->assertCountTableRecords(1)->assertSee('9,000');

        // A new listing is always this shop's draft; sizes and photos are added next, then it is submitted.
        Livewire::test(CreateListing::class)
            ->fillForm(['name_translations.en' => 'Cue case', 'price_minor' => 30000, 'category' => 'accessories', 'max_per_order' => 2, 'status' => 'active', 'seller_id' => $other->id])
            ->call('create')->assertHasNoFormErrors();
        $created = Product::where('name_translations->en', 'Cue case')->sole();
        $this->assertSame([$seller->id, 'draft', StoreService::currency()], [$created->seller_id, $created->status->value, $created->currency]);

        ProductImage::create(['product_id' => $created->id, 'path' => 'products/test.jpg']);
        ProductVariant::create(['product_id' => $created->id, 'sku' => 'CASE-1', 'option_label' => 'One size']);
        Livewire::test(EditListing::class, ['record' => $created->getRouteKey()])->callAction('submit')->assertHasNoActionErrors();
        $this->assertSame('pending_review', $created->fresh()->status->value);
    }

    // ---------------------------------------------------------------- Admin

    public function test_admin_marketplace_screens(): void
    {
        $application = app(SellerService::class)->apply($this->player(), $this->applicationData());
        $seller = $this->seller();
        $pending = $this->listing($seller, approve: false);
        app(ListingService::class)->submit($pending, $seller->user);

        $this->actingAs($this->manager);
        foreach ([SellerApplicationResource::getUrl('index'), SellerApplicationResource::getUrl('view', ['record' => $application]),
            SellerResource::getUrl('index'), SellerResource::getUrl('view', ['record' => $seller]), ProductResource::getUrl('edit', ['record' => $pending])] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertSame('1', SellerApplicationResource::getNavigationBadge());

        Livewire::test(ViewSellerApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('approve', ['name' => 'Kit Co', 'commission_percent' => 12.5, 'agreement_reference' => 'AGR-7',
                'verification_note' => 'Business licence seen at the shop.', 'identity_checked' => true])
            ->assertHasNoActionErrors();
        $this->assertSame(1250, Seller::where('name', 'Kit Co')->sole()->commission_bps);

        Livewire::test(EditProduct::class, ['record' => $pending->getRouteKey()])->callAction('approveListing')->assertHasNoActionErrors();
        $this->assertSame('active', $pending->fresh()->status->value);

        // Store managers review sellers; only finance staff record payouts.
        Livewire::test(ViewSeller::class, ['record' => $seller->getRouteKey()])->assertActionHidden('payout');
        $this->actingAs($this->admin);
        $this->completedOrder($this->player(), $pending->variants->first());
        Livewire::test(ViewSeller::class, ['record' => $seller->getRouteKey()])
            ->callAction('payout', ['amount_minor' => 1000, 'method' => 'cash', 'paid_at' => now()->toDateString()])
            ->assertHasNoActionErrors();
        $this->assertSame(1, SellerLedgerEntry::where('type', 'payout')->count());
    }
}
