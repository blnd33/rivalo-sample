<?php

namespace App\Filament\Seller\Pages;

use App\Filament\Seller\Widgets\SellerOverview;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

/** Seller Hub home: what RIVALO owes, recent sales, listings that need the seller, stock. */
class SellerDashboard extends Dashboard
{
    public static function getNavigationLabel(): string
    {
        return __('Your shop');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Your shop');
    }

    public function getWidgets(): array
    {
        return [SellerOverview::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
