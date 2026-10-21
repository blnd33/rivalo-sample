<?php

namespace App\Filament\Seller\Resources\Listings\RelationManagers;

use App\Models\ProductVariant;
use App\Services\StoreService;
use App\Support\Money;
use App\Support\SellerContext;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Sizes and options of the seller's own listing. Stock is shown but never edited here:
 * RIVALO staff count stock in when the seller brings it.
 */
class SizesRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Sizes and stock');
    }

    /** Read-only unless the listing is a draft or was sent back for changes, in an active shop. */
    public function isReadOnly(): bool
    {
        return parent::isReadOnly() || ! SellerContext::canWrite() || ! $this->getOwnerRecord()->status->sellerEditable();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('option_label')->label(__('Size or option'))->required()->maxLength(40)->helperText(__('For example S, M, XL or One size.')),
            TextInput::make('sku')->label(__('SKU'))->required()->maxLength(40)->unique(ignoreRecord: true)
                ->helperText(__('Your own code for this size. It is printed on RIVALO\'s stock records.')),
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
            ->description(__('Stock is what RIVALO holds for you. Staff count it in when you bring it.'))
            ->columns([
                TextColumn::make('option_label')->label(__('Size or option'))->weight('semibold'),
                TextColumn::make('sku')->label(__('SKU')),
                TextColumn::make('price')->label(__('Price'))->state(fn (ProductVariant $v) => Money::format($v->unitPrice($owner), $owner->currency)),
                TextColumn::make('stock')->label(__('Stock at RIVALO'))->weight('bold')
                    ->color(fn (int $state) => $state <= 0 ? 'danger' : ($state <= 3 ? 'warning' : 'success')),
                IconColumn::make('is_active')->boolean()->label(__('On sale')),
            ])
            ->headerActions([CreateAction::make()->label(__('Add size or option'))])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
