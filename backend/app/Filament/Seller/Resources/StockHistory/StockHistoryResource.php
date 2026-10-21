<?php

namespace App\Filament\Seller\Resources\StockHistory;

use App\Enums\InventoryReason;
use App\Filament\Seller\Resources\StockHistory\Pages\ListStockHistory;
use App\Models\InventoryMovement;
use App\Support\SellerContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Every change to the stock RIVALO holds for this seller: counted in, sold, cancelled, returned. */
class StockHistoryResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $slug = 'stock-history';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        $sellerId = SellerContext::id() ?? 0;

        return parent::getEloquentQuery()->whereHas('variant.product', fn ($q) => $q->where('seller_id', $sellerId));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['variant.product', 'order:id,number']))
            ->columns([
                TextColumn::make('created_at')->label(__('When'))->dateTime('j M Y, H:i'),
                TextColumn::make('variant.product.name_translations')->label(__('Product'))
                    ->state(fn (InventoryMovement $m) => $m->variant->product->name())
                    ->description(fn (InventoryMovement $m) => $m->variant->option_label.' · '.$m->variant->sku),
                TextColumn::make('quantity_delta')->label(__('Change'))->weight('bold')
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('reason')->badge()->formatStateUsing(fn (InventoryReason $state) => $state->label()),
                TextColumn::make('order.number')->label(__('Order'))->placeholder('—'),
                TextColumn::make('note')->label(__('Note'))->placeholder('—')->wrap(),
            ])
            ->filters([SelectFilter::make('reason')->options(InventoryReason::options())]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Stock history');
    }

    public static function getModelLabel(): string
    {
        return __('Stock movement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Stock history');
    }

    public static function getPages(): array
    {
        return ['index' => ListStockHistory::route('/')];
    }
}
