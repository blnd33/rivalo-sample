<?php

namespace App\Filament\Seller\Resources\Sales\Pages;

use App\Filament\Seller\Resources\Sales\SaleResource;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;
}
