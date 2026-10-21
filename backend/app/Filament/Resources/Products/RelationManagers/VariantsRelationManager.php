<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Concerns\HandlesDomainRules;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\StoreService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Sizes / options with their own stock. Stock changes only by "Stock arrived" or "Count stock". */
class VariantsRelationManager extends RelationManager
{
    use HandlesDomainRules;

    protected static string $relationship = 'variants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Sizes and stock');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('option_label')->label(__('Size or option'))->required()->maxLength(40)->helperText(__('For example S, M, XL or One size.')),
            TextInput::make('sku')->label(__('SKU'))->required()->maxLength(40)->unique(ignoreRecord: true),
            TextInput::make('price_minor')->label(__('Price override (minor units)'))->integer()->minValue(1)->suffix(StoreService::currency())
                ->helperText(__('Leave empty to use the product price.')),
            TextInput::make('sort_order')->integer()->default(0),
            Toggle::make('is_active')->label(__('On sale'))->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('option_label')->label(__('Size or option'))->weight('semibold'),
                TextColumn::make('sku')->label(__('SKU'))->toggleable(),
                TextColumn::make('price')->label(__('Price'))->state(fn (ProductVariant $v) => Money::format($v->unitPrice($owner), $owner->currency)),
                TextColumn::make('stock')->label(__('Stock'))->weight('bold')
                    ->color(fn (int $state) => $state <= 0 ? 'danger' : ($state <= 3 ? 'warning' : 'success')),
                IconColumn::make('is_active')->boolean()->label(__('On sale')),
            ])
            ->headerActions([CreateAction::make()->label(__('Add size or option'))])
            ->recordActions([
                Action::make('restock')
                    ->label(__('Stock arrived'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->schema([
                        TextInput::make('quantity')->label(__('How many arrived'))->integer()->minValue(1)->required(),
                        TextInput::make('note')->label(__('Note (supplier, delivery note…)'))->maxLength(255),
                    ])
                    ->action(function (ProductVariant $record, array $data) {
                        self::attempt(fn () => app(InventoryService::class)->restock($record, auth()->user(), (int) $data['quantity'], $data['note'] ?? null));
                        Notification::make()->success()->title(__('Stock updated'))->send();
                    }),
                Action::make('count')
                    ->label(__('Count stock'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('gray')
                    ->modalDescription(__('Use after a stock count, or for damaged or lost items. The difference is recorded with your reason.'))
                    ->schema([
                        TextInput::make('counted')->label(__('Items actually on the shelf'))->integer()->minValue(0)->required(),
                        TextInput::make('reason')->label(__('Reason (audited)'))->required()->maxLength(255),
                    ])
                    ->action(function (ProductVariant $record, array $data) {
                        self::attempt(fn () => app(InventoryService::class)->setCount($record, auth()->user(), (int) $data['counted'], $data['reason']));
                        Notification::make()->success()->title(__('Stock updated'))->send();
                    }),
                // Consignment stock (Milestone 9): unsold seller items handed back to the seller.
                Action::make('returnToSeller')
                    ->label(__('Returned to seller'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn () => $owner->seller_id !== null)
                    ->modalDescription(__('Record unsold items you handed back to the seller. They leave the stock RIVALO holds.'))
                    ->schema([
                        TextInput::make('quantity')->label(__('How many were handed back'))->integer()->minValue(1)->required(),
                        TextInput::make('note')->label(__('Note (who collected them…)'))->maxLength(255),
                    ])
                    ->action(function (ProductVariant $record, array $data) {
                        self::attempt(fn () => app(InventoryService::class)->returnToSeller($record, auth()->user(), (int) $data['quantity'], $data['note'] ?? null));
                        Notification::make()->success()->title(__('Stock updated'))->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
