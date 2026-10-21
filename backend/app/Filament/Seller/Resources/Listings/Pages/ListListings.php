<?php

namespace App\Filament\Seller\Resources\Listings\Pages;

use App\Filament\Seller\Resources\Listings\ListingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('Add product'))];
    }
}
