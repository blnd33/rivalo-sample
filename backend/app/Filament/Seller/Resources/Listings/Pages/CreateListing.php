<?php

namespace App\Filament\Seller\Resources\Listings\Pages;

use App\Enums\ProductStatus;
use App\Filament\Seller\Resources\Listings\ListingResource;
use App\Models\Product;
use App\Services\StoreService;
use App\Support\Audit;
use App\Support\SellerContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

/** A new listing always starts as this shop's draft, in the store currency. */
class CreateListing extends CreateRecord
{
    protected static string $resource = ListingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $seller = SellerContext::current();
        abort_unless($seller && SellerContext::canWrite(), 403);

        $base = Str::limit(Str::slug($seller->slug.' '.($data['name_translations']['en'] ?? 'product')), 70, '');
        $slug = $base;
        for ($i = 2; Product::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return [
            ...$data,
            'seller_id' => $seller->id,
            'slug' => $slug,
            'currency' => StoreService::currency(),
            'status' => ProductStatus::Draft,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    protected function afterCreate(): void
    {
        Audit::record('listing.created', $this->record, new: ['slug' => $this->record->slug, 'price_minor' => $this->record->price_minor]);
    }

    protected function getRedirectUrl(): string
    {
        // Straight to editing, where sizes and photos are added.
        return ListingResource::getUrl('edit', ['record' => $this->record]);
    }
}
