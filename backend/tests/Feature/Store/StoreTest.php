<?php

namespace Tests\Feature\Store;

use App\Enums\RoleName;
use App\Exceptions\DomainRuleException;
use App\Filament\Pages\StoreReport;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\CartItem;
use App\Models\DeliveryZone;
use App\Models\FeatureFlag;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\StoreService;
use App\Support\FeatureFlags;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class StoreTest extends TestCase
{
    private User $manager;

    private PickupLocation $pickup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openStore();
        $this->manager = $this->staff(RoleName::StoreManager);
        $this->manager->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
        $this->pickup = PickupLocation::create(['name' => 'Venue desk', 'address' => 'Main street 1']);
    }

    private function openStore(bool $open = true): void
    {
        FeatureFlag::where('key', FeatureFlags::STORE)->update(['enabled' => $open]);
        FeatureFlags::flush();
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

    /** A product with sizes => stock. */
    private function product(array $sizes = ['M' => 5], int $price = 20000, string $status = 'active', int $max = 5): Product
    {
        $product = Product::create([
            'slug' => 'item-'.uniqid(), 'name_translations' => ['en' => 'Jersey', 'ar' => 'قميص'], 'category' => 'jerseys',
            'price_minor' => $price, 'currency' => StoreService::currency(), 'status' => $status, 'max_per_order' => $max,
        ]);
        foreach ($sizes as $label => $stock) {
            $v = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.uniqid().$label, 'option_label' => (string) $label]);
            if ($stock > 0) {
                app(InventoryService::class)->restock($v, $this->manager, $stock);
            }
        }

        return $product->fresh();
    }

    private function variant(Product $p, string $label = 'M'): ProductVariant
    {
        return $p->variants()->where('option_label', $label)->firstOrFail();
    }

    private function buyer(): User
    {
        return $this->player();
    }

    private function placeOrder(User $buyer, ProductVariant $v, int $qty = 1, array $extra = []): Order
    {
        app(StoreService::class)->setQuantity($buyer, $v, $qty);

        return app(StoreService::class)->checkout($buyer, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id] + $extra);
    }

    // ---------------------------------------------------------------- Catalog and bag

    public function test_the_store_is_closed_until_the_flag_is_on(): void
    {
        $this->product();
        $this->openStore(false);
        $this->getJson('/api/v1/store/products')->assertNotFound()->assertJsonPath('code', 'store_closed');
        $this->actingAs($this->buyer(), 'sanctum')->getJson('/api/v1/me/bag')->assertNotFound();

        $this->openStore();
        $this->getJson('/api/v1/store/products')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_catalog_shows_active_products_and_availability_without_exact_stock(): void
    {
        $p = $this->product(['S' => 10, 'M' => 2, 'L' => 0]);
        $this->product(status: 'draft');

        $res = $this->getJson('/api/v1/store/products')->assertOk()->assertJsonCount(1, 'data');
        $variants = collect($res->json('data.0.variants'))->keyBy('label');
        $this->assertSame(['in_stock', null], [$variants['S']['availability'], $variants['S']['left']]);
        $this->assertSame(['low_stock', 2], [$variants['M']['availability'], $variants['M']['left']]);
        $this->assertSame('sold_out', $variants['L']['availability']);
        $this->assertArrayNotHasKey('stock', $variants['S']);

        $this->getJson('/api/v1/store/products/'.$p->slug, ['Accept-Language' => 'ar'])->assertOk()->assertJsonPath('data.name', 'قميص');
        $this->getJson('/api/v1/store/products/'.Product::where('status', 'draft')->first()->slug)->assertNotFound();
    }

    public function test_bag_rules(): void
    {
        $buyer = $this->buyer();
        $p = $this->product(['M' => 3], max: 2);
        $v = $this->variant($p);

        $this->actingAs($buyer, 'sanctum')->putJson("/api/v1/me/bag/items/{$v->id}", ['quantity' => 2])->assertOk()
            ->assertJsonPath('data.subtotal_minor', 40000)->assertJsonPath('data.ready', true);
        $this->actingAs($buyer, 'sanctum')->putJson("/api/v1/me/bag/items/{$v->id}", ['quantity' => 3])->assertStatus(422)->assertJsonPath('code', 'quantity_limit');

        $big = $this->variant($this->product(['M' => 1], max: 5));
        $this->actingAs($buyer, 'sanctum')->putJson("/api/v1/me/bag/items/{$big->id}", ['quantity' => 2])->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');

        // Stock drops after it was added: the bag says so and is not ready for checkout.
        app(InventoryService::class)->setCount($v, $this->manager, 1, 'Damaged');
        $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/me/bag')->assertJsonPath('data.lines.0.status', 'reduced_stock')->assertJsonPath('data.ready', false);

        $this->actingAs($buyer, 'sanctum')->deleteJson("/api/v1/me/bag/items/{$v->id}")->assertOk()->assertJsonCount(0, 'data.lines');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me/bag')->assertUnauthorized();
    }

    // ---------------------------------------------------------------- Checkout

    public function test_checkout_reserves_stock_in_the_ledger_and_snapshots_prices(): void
    {
        $buyer = $this->buyer();
        $p = $this->product(['M' => 5]);
        $v = $this->variant($p);
        app(StoreService::class)->setQuantity($buyer, $v, 2);

        $res = $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/orders', [
            'fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id, 'expected_total_minor' => 40000,
        ])->assertCreated();

        $order = Order::firstOrFail();
        $res->assertJsonPath('data.number', sprintf('ORD-%06d', $order->id))
            ->assertJsonPath('data.total_minor', 40000)
            ->assertJsonPath('data.payment_method', 'cash_on_pickup')
            ->assertJsonPath('data.pickup_location.name', 'Venue desk');
        $this->assertSame(3, $v->fresh()->stock);
        $this->assertSame(3, app(InventoryService::class)->ledgerBalance($v), 'The running balance equals the ledger.');
        $this->assertSame(0, CartItem::count(), 'The bag is emptied.');
        $this->assertTrue(UserNotification::where('user_id', $buyer->id)->where('type', 'order_placed')->exists());

        $p->update(['price_minor' => 99999]);
        $this->actingAs($buyer, 'sanctum')->getJson("/api/v1/me/orders/{$order->number}")->assertJsonPath('data.items.0.unit_price_minor', 20000);
    }

    public function test_checkout_refusals_change_nothing(): void
    {
        $buyer = $this->buyer();
        $v = $this->variant($this->product(['M' => 5]));
        app(StoreService::class)->setQuantity($buyer, $v, 1);

        $this->expectRule('price_changed', fn () => app(StoreService::class)->checkout($buyer, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id, 'expected_total_minor' => 1]));
        $this->expectRule('pickup_unavailable', fn () => app(StoreService::class)->checkout($buyer, ['fulfillment' => 'pickup', 'pickup_location_id' => 999]));
        $this->expectRule('delivery_unavailable', fn () => app(StoreService::class)->checkout($buyer, ['fulfillment' => 'delivery']));
        $this->assertSame(0, Order::count());
        $this->assertSame(5, $v->fresh()->stock);

        $unverified = $this->player(['email_verified_at' => null]);
        $this->expectRule('email_unverified', fn () => app(StoreService::class)->checkout($unverified, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id]));
        $this->expectRule('bag_empty', fn () => app(StoreService::class)->checkout($this->buyer(), ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id]));
    }

    public function test_delivery_needs_an_area_and_details_and_adds_its_fee(): void
    {
        $zone = DeliveryZone::create(['name' => 'Centre', 'fee_minor' => 3000, 'estimate' => '1–2 days']);
        $buyer = $this->buyer();
        $v = $this->variant($this->product(['M' => 5]));
        app(StoreService::class)->setQuantity($buyer, $v, 1);

        $this->expectRule('delivery_details_required', fn () => app(StoreService::class)->checkout($buyer, ['fulfillment' => 'delivery', 'delivery_zone_id' => $zone->id, 'recipient_name' => 'A']));

        $order = app(StoreService::class)->checkout($buyer, [
            'fulfillment' => 'delivery', 'delivery_zone_id' => $zone->id, 'recipient_name' => 'Real Name', 'recipient_phone' => '+964 750 000 0000', 'delivery_address' => 'Street 5, house 2',
        ]);
        $this->assertSame([20000, 3000, 23000, 'cash_on_delivery'], [$order->subtotal_minor, $order->delivery_fee_minor, $order->total_minor, $order->payment_method]);
        $this->assertArrayNotHasKey('recipient_phone', $order->toArray(), 'Hidden from accidental serialization.');
    }

    public function test_the_last_item_cannot_be_sold_twice(): void
    {
        $v = $this->variant($this->product(['M' => 1]));
        [$a, $b] = [$this->buyer(), $this->buyer()];
        app(StoreService::class)->setQuantity($a, $v, 1);
        app(StoreService::class)->setQuantity($b, $v, 1);

        app(StoreService::class)->checkout($a, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id]);
        $this->expectRule('insufficient_stock', fn () => app(StoreService::class)->checkout($b, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id]));
        $this->assertSame(0, $v->fresh()->stock);
        $this->assertSame(1, Order::count());
    }

    public function test_open_order_limit_and_customer_cancellation(): void
    {
        $buyer = $this->buyer();
        $v = $this->variant($this->product(['M' => 10]));
        $orders = collect(range(1, StoreService::MAX_OPEN_ORDERS))->map(fn () => $this->placeOrder($buyer, $v));
        app(StoreService::class)->setQuantity($buyer, $v, 1);
        $this->expectRule('too_many_open_orders', fn () => app(StoreService::class)->checkout($buyer, ['fulfillment' => 'pickup', 'pickup_location_id' => $this->pickup->id]));

        $first = $orders->first();
        $this->actingAs($this->buyer(), 'sanctum')->postJson("/api/v1/me/orders/{$first->number}/cancel")->assertNotFound();
        $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/me/orders/{$first->number}/cancel", ['reason' => 'Wrong size'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_cancel', false);
        $this->assertSame(8, $v->fresh()->stock);

        $second = $orders[1];
        app(OrderService::class)->confirm($second, $this->manager);
        app(OrderService::class)->markReady($second, $this->manager);
        $this->expectRule('order_not_cancellable', fn () => app(StoreService::class)->cancelByCustomer($second->fresh(), $buyer));
    }

    // ---------------------------------------------------------------- Staff

    public function test_pickup_order_lifecycle_with_cash_payment(): void
    {
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $this->variant($this->product(['M' => 5])), 2);
        $orders = app(OrderService::class);

        $this->expectRule('forbidden', fn () => $orders->confirm($order, $this->staff(RoleName::ContentEditor)));
        $this->expectRule('invalid_order_transition', fn () => $orders->markReady($order, $this->manager));
        $orders->confirm($order, $this->manager);
        $this->expectRule('wrong_fulfillment', fn () => $orders->markOutForDelivery($order, $this->manager));
        $orders->markReady($order, $this->manager);
        $this->assertStringContainsString('Venue desk', UserNotification::where('user_id', $buyer->id)->where('type', 'order_ready')->first()->body);

        $this->expectRule('payment_required', fn () => $orders->complete($order, $this->manager));
        $orders->recordPayment($order, $this->manager);
        $this->expectRule('already_paid', fn () => $orders->recordPayment($order, $this->manager));
        $order = $orders->complete($order, $this->manager);

        $this->assertSame(['completed', 'paid', 40000], [$order->status->value, $order->payment_status->value, $order->amount_paid_minor]);
        $this->assertSame(sprintf('SR-%06d', $order->id), $order->payment_reference);
    }

    public function test_cancel_and_refund_rules(): void
    {
        $buyer = $this->buyer();
        $v = $this->variant($this->product(['M' => 5]));
        $orders = app(OrderService::class);

        $unpaid = $this->placeOrder($buyer, $v, 1);
        $orders->cancel($unpaid, $this->manager, 'Out of season');
        $this->assertSame(5, $v->fresh()->stock);
        $this->assertSame('Out of season', UserNotification::where('user_id', $buyer->id)->where('type', 'order_cancelled')->first()->body);

        $paid = $this->placeOrder($buyer, $v, 2);
        $orders->confirm($paid, $this->manager);
        $orders->recordPayment($paid, $this->manager);
        $this->expectRule('refund_first', fn () => $orders->cancel($paid, $this->manager, 'Customer asked'));

        $item = $paid->items()->first();
        $this->expectRule('invalid_refund', fn () => $orders->refund($paid, $this->manager, 50000, 'Too much'));
        $orders->refund($paid, $this->manager, 20000, 'One returned unworn', [$item->id => 1]);
        $paid->refresh();
        $this->assertSame(['partially_refunded', 20000, 1], [$paid->payment_status->value, $paid->amount_refunded_minor, $item->fresh()->returned_quantity]);
        $this->assertSame(4, $v->fresh()->stock, 'Returned item back on the shelf.');

        $orders->refund($paid, $this->manager, 20000, 'Second returned', [$item->id => 1]);
        $orders->cancel($paid->fresh(), $this->manager, 'Fully refunded');
        $this->assertSame(['refunded', 'cancelled'], [$paid->fresh()->payment_status->value, $paid->fresh()->status->value]);
        $this->assertSame(5, $v->fresh()->stock, 'Cancelling after returns does not add stock twice.');
        $this->assertSame(5, app(InventoryService::class)->ledgerBalance($v));
    }

    public function test_stock_history_is_append_only_and_counts_need_a_reason(): void
    {
        $v = $this->variant($this->product(['M' => 5]));
        $this->expectRule('reason_required', fn () => app(InventoryService::class)->setCount($v, $this->manager, 3, ''));
        app(InventoryService::class)->setCount($v, $this->manager, 3, 'Two damaged');
        $this->assertSame(-2, InventoryMovement::latest('id')->first()->quantity_delta);

        $this->expectException(LogicException::class);
        InventoryMovement::first()->update(['quantity_delta' => 100]);
    }

    public function test_orders_are_private_to_their_customer(): void
    {
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $this->variant($this->product()));
        $this->placeOrder($this->buyer(), $this->variant($this->product()));

        $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/me/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', $order->number);
        $this->actingAs($this->buyer(), 'sanctum')->getJson("/api/v1/me/orders/{$order->number}")->assertNotFound();
    }

    // ---------------------------------------------------------------- Admin

    public function test_store_admin_screens(): void
    {
        $order = $this->placeOrder($this->buyer(), $this->variant($this->product(['M' => 5])), 1);

        $editor = $this->staff(RoleName::ContentEditor);
        $editor->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
        $this->actingAs($editor)->get(OrderResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->manager);
        foreach ([ProductResource::getUrl('index'), ProductResource::getUrl('create'), OrderResource::getUrl('index'), OrderResource::getUrl('view', ['record' => $order]), StoreReport::getUrl()] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertSame('1', OrderResource::getNavigationBadge());

        Livewire::test(CreateProduct::class)
            ->fillForm(['name_translations.en' => 'Scarf', 'slug' => 'scarf', 'status' => 'active', 'price_minor' => 9000, 'category' => 'accessories', 'max_per_order' => 3])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(StoreService::currency(), Product::where('slug', 'scarf')->first()->currency);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('confirm')->assertHasNoActionErrors()
            ->callAction('ready')->assertHasNoActionErrors()
            ->callAction('complete')->assertHasNoActionErrors();
        $this->assertSame(['completed', 'paid'], [$order->fresh()->status->value, $order->fresh()->payment_status->value]);
    }
}
