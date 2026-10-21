<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductCategory;
use App\Enums\SellerApplicationStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\SellerResource;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Services\SellerService;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Approved outside sellers (Milestone 9). Shop pages are public while the store and the
 * marketplace are switched on. Applications are private to the applicant.
 */
class MarketplaceController extends Controller
{
    public function __construct(private readonly SellerService $sellers) {}

    /** Shops with something on sale. */
    public function sellers()
    {
        $this->sellers->assertMarketplaceOpen();
        $sellers = Seller::where('status', SellerStatus::Active->value)
            ->whereHas('products', fn ($q) => $q->onSale())
            ->withCount(['products' => fn ($q) => $q->onSale()])
            ->orderBy('name')
            ->limit(100)
            ->get();

        return SellerResource::collection($sellers);
    }

    public function seller(Seller $seller): JsonResponse
    {
        $this->sellers->assertMarketplaceOpen();
        abort_unless($seller->isActive(), 404);

        $products = Product::onSale()->where('seller_id', $seller->id)
            ->with(['variants', 'images', 'sport', 'seller'])
            ->orderBy('sort_order')->orderBy('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => [
            'seller' => SellerResource::make($seller)->resolve(),
            'products' => ProductResource::collection($products)->resolve(),
        ]]);
    }

    /** Everything the app needs for "Sell on RIVALO": the latest application and the shop, if any. */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $seller = Seller::where('user_id', $user->id)->first();
        $application = SellerApplication::where('user_id', $user->id)->latest('id')->first();

        return response()->json(['data' => [
            'open' => SellerService::marketplaceOpen(),
            'can_apply' => SellerService::marketplaceOpen() && ! $seller && $application?->status !== SellerApplicationStatus::Pending,
            'seller' => $seller ? [
                'slug' => $seller->slug,
                'name' => $seller->name,
                'status' => $seller->status->value,
                'status_reason' => $seller->status === SellerStatus::Active ? null : $seller->status_reason,
            ] : null,
            'application' => $application ? [
                'id' => $application->id,
                'status' => $application->status->value,
                'business_name' => $application->business_name,
                'decision_note' => $application->status === SellerApplicationStatus::Rejected ? $application->decision_note : null,
                'submitted_at' => $application->created_at?->toIso8601String(),
            ] : null,
            'hub_url' => url('/seller'),
            'currency' => StoreService::currency(),
        ]]);
    }

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\s-]{6,32}$/'],
            'city' => ['required', 'string', 'min:2', 'max:80'],
            'categories' => ['required', 'array', 'min:1', 'max:4'],
            'categories.*' => [Rule::enum(ProductCategory::class)],
            'description' => ['required', 'string', 'min:20', 'max:1000'],
            'link' => ['nullable', 'string', 'max:255'],
        ]);
        $this->sellers->apply($request->user(), $data);

        return $this->status($request)->setStatusCode(201);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $application = SellerApplication::where('user_id', $request->user()->id)
            ->where('status', SellerApplicationStatus::Pending->value)
            ->firstOrFail();
        $this->sellers->withdraw($application, $request->user());

        return $this->status($request);
    }
}
