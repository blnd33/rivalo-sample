<?php

namespace App\Filament\Resources\Sellers\RelationManagers;

use App\Enums\ProductStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The seller's listings. Reviewed and stocked from the product page. */
class ListingsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Products');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('variants'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->state(fn (Product $p) => $p->name())->weight('semibold'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProductStatus $state) => $state->label())
                    ->color(fn (ProductStatus $state) => $state->color()),
                TextColumn::make('price_minor')->label(__('Price'))->formatStateUsing(fn (Product $p) => Money::format($p->price_minor, $p->currency)),
                TextColumn::make('stock')->label(__('Stock'))->state(fn (Product $p) => $p->variants->sum('stock')),
            ])
            ->recordActions([
                Action::make('open')->label(__('Open'))->url(fn (Product $p) => ProductResource::getUrl('edit', ['record' => $p])),
            ]);
    }
}
