<?php

namespace App\Filament\Resources\Sports;

use App\Enums\AdminNavigationGroup;
use App\Enums\SportKind;
use App\Filament\Resources\Sports\Pages\CreateSport;
use App\Filament\Resources\Sports\Pages\EditSport;
use App\Filament\Resources\Sports\Pages\ListSports;
use App\Models\Sport;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SportResource extends Resource
{
    protected static ?string $model = Sport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Catalog;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Sport'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(80),
                    TextInput::make('slug')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true)
                        ->helperText(__('Stable identifier used by the app. Avoid changing after launch.')),
                    Select::make('kind')->options(SportKind::options())->required()->native(false),
                    TextInput::make('icon')->maxLength(40)->helperText(__('Icon key used by the app (e.g. football, padel, cue, controller).')),
                    TextInput::make('team_member_limit')->integer()->minValue(1)->maxValue(100)
                        ->helperText(__('Maximum active members per team. Leave empty for individual-only sports.')),
                    TextInput::make('sort_order')->integer()->default(0)->required(),
                    Toggle::make('is_active')->label(__('Active'))->helperText(__('Inactive sports are hidden from players.')),
                ]),
            Section::make(__('Translations'))
                ->description(__('Localized names shown in the app. Use locale codes: ar (Arabic), kmr (Kurdish, Kurmanji).'))
                ->collapsed()
                ->schema([
                    KeyValue::make('name_translations')->keyLabel(__('Locale (ar or kmr)'))->valueLabel(__('Name')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold'),
                TextColumn::make('kind')->badge()->formatStateUsing(fn (SportKind $state) => $state->label()),
                TextColumn::make('disciplines_count')->counts('disciplines')->label(__('Disciplines')),
                TextColumn::make('team_member_limit')->label(__('Team limit'))->placeholder(__('Individual only')),
                IconColumn::make('is_active')->boolean()->label(__('Active')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Sport');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sports');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSports::route('/'),
            'create' => CreateSport::route('/create'),
            'edit' => EditSport::route('/{record}/edit'),
        ];
    }
}
