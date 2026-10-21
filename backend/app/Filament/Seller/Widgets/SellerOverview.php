<?php

namespace App\Filament\Seller\Widgets;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerLedgerType;
use App\Filament\Seller\Resources\Listings\ListingResource;
use App\Filament\Seller\Resources\Sales\SaleResource;
use App\Filament\Seller\Resources\Statement\StatementResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerLedgerEntry;
use App\Services\SellerLedgerService;
use App\Services\StoreService;
use App\Support\Money;
use App\Support\SellerContext;
use Filament\Widgets\Widget;

/** Seller Hub dashboard. Every figure is a real count or sum for this shop only. */
class SellerOverview extends Widget
{
    protected string $view = 'filament.seller.overview';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    public function data(): array
    {
        $seller = SellerContext::current();
        if (! $seller) {
            return ['seller' => null];
        }
        $currency = StoreService::currency();
        $summary = app(SellerLedgerService::class)->summary($seller, $currency);
        $monthSales = SellerLedgerEntry::where('seller_id', $seller->id)->where('currency', $currency)
            ->whereIn('type', [SellerLedgerType::Sale->value, SellerLedgerType::Return->value])
            ->where('occurred_at', '>=', now()->startOfMonth())
            ->sum('amount_minor');
        $statusCounts = Product::where('seller_id', $seller->id)->toBase()->groupBy('status')->selectRaw('status, COUNT(*) as n')->pluck('n', 'status');
        $inOpenOrders = OrderItem::where('seller_id', $seller->id)
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value]))
            ->sum('quantity');
        $low = ProductVariant::where('is_active', true)->where('stock', '<=', 3)
            ->whereHas('product', fn ($q) => $q->where('seller_id', $seller->id)->where('status', ProductStatus::Active->value))
            ->count();

        return [
            'seller' => $seller,
            'owed' => Money::format($summary['owed'], $currency),
            'owedNegative' => $summary['owed'] < 0,
            'paid' => Money::format($summary['paid'], $currency),
            'month' => Money::format((int) $monthSales, $currency),
            'commission' => $seller->commissionLabel(),
            'inOpenOrders' => (int) $inOpenOrders,
            'onSale' => (int) ($statusCounts[ProductStatus::Active->value] ?? 0),
            'waiting' => (int) ($statusCounts[ProductStatus::PendingReview->value] ?? 0),
            'changes' => (int) ($statusCounts[ProductStatus::ChangesRequested->value] ?? 0),
            'low' => $low,
            'listingsUrl' => ListingResource::getUrl(),
            'salesUrl' => SaleResource::getUrl(),
            'statementUrl' => StatementResource::getUrl(),
        ];
    }
}
