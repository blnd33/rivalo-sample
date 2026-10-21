<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Enums\AdminNavigationGroup;
use App\Enums\InventoryReason;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only stock history: every item in and out, with who and why. */
class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Store;

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['variant.product', 'order', 'createdBy']))
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y H:i')->label(__('When')),
                TextColumn::make('item')->label(__('Item'))
                    ->state(fn (InventoryMovement $m) => $m->variant->product->name().' · '.$m->variant->option_label)->weight('semibold'),
                TextColumn::make('quantity_delta')->label(__('Change'))
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('reason')->formatStateUsing(fn (InventoryReason $state) => $state->label())->badge(),
                TextColumn::make('order.number')->label(__('Order'))->placeholder('—'),
                TextColumn::make('note')->placeholder('—')->limit(50),
                TextColumn::make('createdBy.username')->label(__('By'))->prefix('@')->placeholder(__('System')),
            ])
            ->filters([SelectFilter::make('reason')->options(InventoryReason::options())]);
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
        return ['index' => Pages\ListInventoryMovements::route('/')];
    }
}
