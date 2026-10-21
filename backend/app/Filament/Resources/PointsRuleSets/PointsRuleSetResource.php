<?php

namespace App\Filament\Resources\PointsRuleSets;

use App\Enums\AdminNavigationGroup;
use App\Enums\MatchOutcome;
use App\Enums\PointsRuleSetStatus;
use App\Enums\PointsTrigger;
use App\Filament\Resources\PointsRuleSets\Pages\CreatePointsRuleSet;
use App\Filament\Resources\PointsRuleSets\Pages\EditPointsRuleSet;
use App\Filament\Resources\PointsRuleSets\Pages\ListPointsRuleSets;
use App\Models\Discipline;
use App\Models\PointsRuleSet;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Points tables (decision D3). Every table starts as a draft and awards nothing. Publishing needs
 * a note recording the owner's decision; published tables are frozen (make a new version instead).
 */
class PointsRuleSetResource extends Resource
{
    protected static ?string $model = PointsRuleSet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Competition;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $locked = fn (?PointsRuleSet $record) => $record && ! $record->isDraft();

        return $schema->columns(1)->components([
            Callout::make(__('Published tables cannot be edited'))
                ->description(__('Use "New version" to change a published table. Tournaments keep the table they were assigned.'))
                ->warning()
                ->visible($locked),
            Section::make(__('Points table'))->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->disabled($locked),
                Select::make('discipline_id')->label(__('Discipline'))
                    ->options(fn () => Discipline::with('sport')->get()->mapWithKeys(fn ($d) => [$d->id => $d->sport->name.' · '.$d->name]))
                    ->required()->searchable()->native(false)->disabled($locked),
                Select::make('season_id')->label(__('Season'))->relationship('season', 'name')
                    ->placeholder(__('All seasons'))->native(false)->disabled($locked),
                TextInput::make('version')->disabled()->dehydrated(false)->visible(fn (?PointsRuleSet $record) => (bool) $record),
                Textarea::make('notes')->rows(2)->columnSpanFull()->disabled($locked)
                    ->helperText(__('Where the owner\'s decision is documented (decision D3).')),
            ]),
            Section::make(__('Rules'))->schema([
                Repeater::make('rules')
                    ->relationship()
                    ->disabled($locked)
                    ->columns(3)
                    ->defaultItems(0)
                    ->addActionLabel(__('Add rule'))
                    ->schema([
                        Select::make('trigger')->label(__('Earned for'))->options(PointsTrigger::options())->required()->live()->distinct()->native(false),
                        TextInput::make('points')->label(__('Points'))->integer()->minValue(0)->maxValue(10000)->required(),
                        CheckboxList::make('eligible_outcomes')->label(__('Counts when the match ended by'))
                            ->options(collect(MatchOutcome::cases())->reject(fn ($o) => $o === MatchOutcome::Cancelled)->mapWithKeys(fn ($o) => [$o->value => $o->label()])->all())
                            ->default(['normal'])
                            ->visible(fn (Get $get) => in_array($get('trigger'), ['match_win', 'match_draw', 'match_loss'], true)),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold'),
                TextColumn::make('discipline.name')->label(__('Discipline')),
                TextColumn::make('season.name')->label(__('Season'))->placeholder(__('All seasons')),
                TextColumn::make('version')->prefix('v'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PointsRuleSetStatus $state) => $state->label())
                    ->color(fn (PointsRuleSetStatus $state) => match ($state) {
                        PointsRuleSetStatus::Published => 'success',
                        PointsRuleSetStatus::Draft => 'warning',
                        PointsRuleSetStatus::Retired => 'gray',
                    }),
                TextColumn::make('rules_count')->counts('rules')->label(__('Rules')),
                TextColumn::make('published_at')->dateTime('d M Y')->label(__('Published'))->placeholder('—'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Points table');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Points tables');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPointsRuleSets::route('/'),
            'create' => CreatePointsRuleSet::route('/create'),
            'edit' => EditPointsRuleSet::route('/{record}/edit'),
        ];
    }
}
