<?php

namespace App\Filament\Resources\PickupLocations;

use App\Enums\AdminNavigationGroup;
use App\Models\PickupLocation;
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

class PickupLocationResource extends Resource
{
    protected static ?string $model = PickupLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Store;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('address')->required()->maxLength(255),
            TextInput::make('opening_hours')->label(__('Opening hours'))->maxLength(255)->helperText(__('For example: Sat–Thu 16:00–22:00')),
            Toggle::make('is_active')->label(__('Offered at checkout'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->weight('semibold')->description(fn (PickupLocation $p) => $p->is_sample_data ? __('Sample data') : null),
            TextColumn::make('address'),
            TextColumn::make('opening_hours')->label(__('Opening hours'))->placeholder('—'),
            IconColumn::make('is_active')->boolean()->label(__('Offered at checkout')),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Pickup point');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Pickup points');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePickupLocations::route('/')];
    }
}
