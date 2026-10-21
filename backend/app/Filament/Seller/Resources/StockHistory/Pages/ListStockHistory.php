<?php

namespace App\Filament\Seller\Resources\StockHistory\Pages;

use App\Filament\Seller\Resources\StockHistory\StockHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListStockHistory extends ListRecords
{
    protected static string $resource = StockHistoryResource::class;

    public function getSubheading(): ?string
    {
        return __('To bring more stock, or to take unsold items back, contact RIVALO. Staff record every change here.');
    }
}
