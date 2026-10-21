<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\StoreService;
use App\Support\Audit;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['currency'] = StoreService::currency();

        return $data;
    }

    protected function afterCreate(): void
    {
        Audit::record('product.created', $this->record, new: ['slug' => $this->record->slug, 'price_minor' => $this->record->price_minor]);
    }

    protected function getRedirectUrl(): string
    {
        // Straight to editing, where sizes, stock and photos are added.
        return ProductResource::getUrl('edit', ['record' => $this->record]);
    }
}
