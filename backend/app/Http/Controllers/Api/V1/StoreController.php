<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Fulfillment;
use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Official store (Milestone 8). Every endpoint answers 404 "store_closed" while the
 * store_enabled flag is off. Orders are only ever visible to the customer who placed them.
 */
class StoreController extends Controller
{
    public function __construct(private readonly StoreService $store) {}

    public function products(Request $request)
    {
        $this->store->assertOpen();
        $data = $request->validate([
            'category' => ['nullable', Rule::enum(ProductCategory::class)],
            'sport' => ['nullable', 'integer'],
            'seller' => ['nullable', 'string', 'max:80'],
            'official' => ['nullable', 'boolean'],
        ]);

        $products = Product::onSale()
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($data['sport'] ?? null, fn ($q, $s) => $q->where('sport_id', $s))
            ->when($data['seller'] ?? null, fn ($q, $slug) => $q->whereIn('seller_id', Seller::select('id')->where('slug', $slug)))
            ->when($request->boolean('official'), fn ($q) => $q->whereNull('seller_id'))
            ->with(['variants', 'images', 'sport', 'seller'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id')
            ->limit(200)
            ->get();

        return ProductResource::collection($products)->additional(['meta' => ['currency' => StoreService::currency()]]);
    }

    public function product(Product $product): ProductResource
    {
        $this->store->assertOpen();
        abort_unless($product->isOnSale(), 404);

        return ProductResource::make($product->load(['variants', 'images', 'sport', 'seller']));
    }

    /** Pickup points and delivery areas (with fees). Delivery is unavailable when the list is empty. */
    public function fulfillment(): JsonResponse
    {
        $this->store->assertOpen();

        return response()->json(['data' => [
            'currency' => StoreService::currency(),
            'payment' => 'cash_on_handover',
            'pickup_locations' => PickupLocation::where('is_active', true)->orderBy('name')->get(['id', 'name', 'address', 'opening_hours']),
            'delivery_zones' => DeliveryZone::where('is_active', true)->orderBy('name')->get(['id', 'name', 'fee_minor', 'estimate']),
        ]]);
    }

    public function bag(Request $request): JsonResponse
    {
        $this->store->assertOpen();

        return response()->json(['data' => $this->store->bag($request->user())]);
    }

    public function setBagItem(Request $request, ProductVariant $variant): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);
        $this->store->setQuantity($request->user(), $variant, $data['quantity']);

        return response()->json(['data' => $this->store->bag($request->user())]);
    }

    public function removeBagItem(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->store->setQuantity($request->user(), $variant, 0);

        return response()->json(['data' => $this->store->bag($request->user())]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fulfillment' => ['required', Rule::enum(Fulfillment::class)],
            'pickup_location_id' => ['nullable', 'integer'],
            'delivery_zone_id' => ['nullable', 'integer'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'recipient_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\s-]{6,32}$/'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'expected_total_minor' => ['nullable', 'integer', 'min:0'],
        ]);
        $order = $this->store->checkout($request->user(), $data);

        return OrderResource::make($this->loadOrder($order))->response()->setStatusCode(201);
    }

    public function orders(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)->latest('placed_at')->limit(50)->get();

        return OrderResource::collection($orders->each(fn (Order $o) => $this->loadOrder($o)));
    }

    public function order(Request $request, Order $order): OrderResource
    {
        // Someone else's order is simply "not found": order numbers reveal nothing.
        abort_unless($order->user_id === $request->user()->id, 404);

        return OrderResource::make($this->loadOrder($order));
    }

    public function cancel(Request $request, Order $order): OrderResource
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return OrderResource::make($this->loadOrder($this->store->cancelByCustomer($order, $request->user(), $data['reason'] ?? null)));
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load(['items.variant.product.images', 'items.seller', 'pickupLocation', 'deliveryZone']);
    }
}
