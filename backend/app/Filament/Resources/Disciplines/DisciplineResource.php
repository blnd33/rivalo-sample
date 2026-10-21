<?php

namespace App\Filament\Resources\Disciplines;

use App\Enums\AdminNavigationGroup;
use App\Enums\CompetitionMode;
use App\Enums\RegistrationType;
use App\Enums\RulesetStatus;
use App\Filament\Resources\Disciplines\Pages\CreateDiscipline;
use App\Filament\Resources\Disciplines\Pages\EditDiscipline;
use App\Filament\Resources\Disciplines\Pages\ListDisciplines;
use App\Models\Discipline;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class DisciplineResource extends Resource
{
    protected static ?string $model = Discipline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Catalog;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Discipline'))
                ->columns(2)
                ->schema([
                    Select::make('sport_id')->relationship('sport', 'name')->required()->native(false),
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('slug')->required()->alphaDash()->maxLength(80),
                    TextInput::make('sort_order')->integer()->default(0),
                    Textarea::make('description')->columnSpanFull()->rows(3),
                ]),
            Section::make(__('Entry format'))
                ->columns(3)
                ->schema([
                    CheckboxList::make('registration_types')
                        ->options(RegistrationType::options())
                        ->required()
                        ->columns(3)
                        ->columnSpanFull()
                        ->helperText(__('Pairs register as two-player teams so partners must accept an invitation first.')),
                    TextInput::make('team_size_min')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('team_size_max')->integer()->minValue(1)->required()->default(1)->gte('team_size_min'),
                    TextInput::make('substitutes_max')->integer()->minValue(0)->required()->default(0),
                ]),
            Section::make(__('E-sports details'))
                ->description(__('Required before activating an e-sports title. Do not guess versions: enter exactly what will be played.'))
                ->columns(3)
                ->collapsible()
                ->schema([
                    TextInput::make('game_title')->maxLength(120),
                    TextInput::make('game_version')->maxLength(60),
                    TextInput::make('platform')->maxLength(60)->placeholder(__('e.g. PC, PlayStation 5, cross-play')),
                    TextInput::make('region')->maxLength(60),
                    TextInput::make('match_format')->maxLength(120)->placeholder(__('e.g. Best of 3, MR12')),
                    Select::make('competition_mode')->options(CompetitionMode::options())->native(false),
                ]),
            Section::make(__('Ruleset and activation'))
                ->columns(2)
                ->schema([
                    Select::make('ruleset_status')->options(RulesetStatus::options())->required()->native(false)
                        ->helperText(__('Tournaments can only be published on disciplines with a confirmed ruleset.')),
                    Toggle::make('is_active')->label(__('Active (visible to players)')),
                    Textarea::make('ruleset_notes')->rows(3)->columnSpanFull()
                        ->helperText(__('Record open questions here (e.g. D1: exact American pool ruleset).')),
                    KeyValue::make('name_translations')->keyLabel(__('Locale'))->valueLabel(__('Name'))->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sport_id')
            ->columns([
                TextColumn::make('sport.name')->label(__('Sport'))->sortable(),
                TextColumn::make('name')->searchable()->weight('semibold'),
                TextColumn::make('registration_types')->badge()->label(__('Entry')),
                TextColumn::make('team_size_max')->label(__('Roster'))
                    ->formatStateUsing(fn ($state, Discipline $record) => $record->team_size_min === $record->team_size_max
                        ? (string) $record->team_size_max
                        : "{$record->team_size_min}–{$record->team_size_max}"),
                TextColumn::make('ruleset_status')->badge()
                    ->formatStateUsing(fn (RulesetStatus $state) => $state === RulesetStatus::Confirmed ? __('Confirmed') : __('Needs confirmation'))
                    ->color(fn (RulesetStatus $state) => $state === RulesetStatus::Confirmed ? 'success' : 'warning')
                    ->icon(fn (RulesetStatus $state) => $state === RulesetStatus::Confirmed ? Heroicon::CheckCircle : Heroicon::ExclamationTriangle),
                IconColumn::make('is_active')->boolean()->label(__('Active')),
            ])
            ->filters([
                SelectFilter::make('sport')->relationship('sport', 'name'),
                TernaryFilter::make('is_active')->label(__('Active')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Discipline');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Disciplines');
    }

    public static function getNavigationLabel(): string
    {
        return __('Disciplines & games');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDisciplines::route('/'),
            'create' => CreateDiscipline::route('/create'),
            'edit' => EditDiscipline::route('/{record}/edit'),
        ];
    }
}
