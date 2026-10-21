<?php

namespace App\Filament\Resources\DeliveryZones;

use App\Enums\AdminNavigationGroup;
use App\Models\DeliveryZone;
use App\Services\StoreService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Delivery is offered only to these areas. With none active, the app offers pickup only. */
class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Store;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('City or area'))->required()->maxLength(120),
            TextInput::make('fee_minor')->label(__('Delivery fee (minor units)'))->integer()->minValue(0)->required()->suffix(StoreService::currency())
                ->helperText(__('Charged on top of the items and collected in cash on delivery. 0 = free delivery.')),
            TextInput::make('estimate')->label(__('Delivery time shown to customers'))->maxLength(80)->helperText(__('For example: 1–2 days')),
            Toggle::make('is_active')->label(__('Offered at checkout'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('City or area'))->weight('semibold')->description(fn (DeliveryZone $z) => $z->is_sample_data ? __('Sample data') : null),
            TextColumn::make('fee_minor')->label(__('Delivery fee'))->formatStateUsing(fn (DeliveryZone $z) => Money::format($z->fee_minor, StoreService::currency())),
            TextColumn::make('estimate')->label(__('Delivery time'))->placeholder('—'),
            IconColumn::make('is_active')->boolean()->label(__('Offered at checkout')),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Delivery area');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Delivery areas');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDeliveryZones::route('/')];
    }
}
