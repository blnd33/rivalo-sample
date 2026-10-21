<?php

namespace App\Filament\Resources\Tournaments;

use App\Enums\AdminNavigationGroup;
use App\Enums\FundingModel;
use App\Enums\RegistrationType;
use App\Enums\ThirdPlaceMethod;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Filament\Resources\Tournaments\Pages\CreateTournament;
use App\Filament\Resources\Tournaments\Pages\EditTournament;
use App\Filament\Resources\Tournaments\Pages\ListTournaments;
use App\Filament\Resources\Tournaments\RelationManagers\EntriesRelationManager;
use App\Filament\Resources\Tournaments\RelationManagers\StaffRelationManager;
use App\Models\Discipline;
use App\Models\Tournament;
use App\Support\FeatureFlags;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class TournamentResource extends Resource
{
    protected static ?string $model = Tournament::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Competition;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /** Organizers see only tournaments they are assigned to. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->manageableBy(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        $locked = fn (?Tournament $record) => $record?->areMaterialRulesLocked() ?? false;

        return $schema->components([
            Section::make(__('Rules are locked'))
                ->description(__('Registration has opened, so fees, prize rules, entry format, roster size and ranking eligibility are locked. A correction needs a reason and is recorded in the audit log.'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->visible($locked)
                ->schema([
                    Textarea::make('change_reason')
                        ->label(__('Reason for correcting locked rules'))
                        ->helperText(__('Required only if you change a locked field.'))
                        ->rows(2),
                ]),
            Tabs::make('Tournament')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make(__('Basics'))->icon(Heroicon::OutlinedInformationCircle)->columns(2)->schema([
                        TextInput::make('name')->required()->maxLength(120)->columnSpanFull(),
                        Select::make('discipline_id')
                            ->label(__('Discipline'))
                            ->options(fn () => Discipline::with('sport')->orderBy('sport_id')->orderBy('sort_order')->get()
                                ->groupBy(fn ($d) => $d->sport->name)
                                ->map(fn ($group) => $group->mapWithKeys(fn ($d) => [$d->id => $d->name.($d->isUsableForTournaments() ? '' : ' ('.__('not ready').')')])))
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(__('Disciplines marked "not ready" can be drafted but not published.')),
                        Select::make('season_id')->relationship('season', 'name')->native(false)->placeholder(__('No season')),
                        Select::make('registration_type')
                            ->options(function (Get $get) {
                                $discipline = Discipline::find($get('discipline_id'));
                                $allowed = $discipline?->registration_types ?? RegistrationType::values();

                                return collect(RegistrationType::options())->only($allowed)->all();
                            })
                            ->required()
                            ->native(false)
                            ->disabled($locked)
                            ->helperText(__('Pairs register as two-player teams.')),
                        Select::make('format')
                            ->options(TournamentFormat::options())
                            ->required()
                            ->native(false)
                            ->disabled($locked)
                            ->helperText(__('Fixture generation for each format arrives in Milestone 5.')),
                        Textarea::make('description')->rows(4)->columnSpanFull(),
                        FileUpload::make('cover_path')
                            ->label(__('Cover image'))
                            ->image()
                            ->disk('public')
                            ->directory('tournament-covers')
                            ->maxSize(4096)
                            ->imageAspectRatio('16:9')
                            ->columnSpanFull(),
                    ]),
                    Tab::make(__('Entrants & roster'))->icon(Heroicon::OutlinedUsers)->columns(3)->schema([
                        TextInput::make('min_entrants')->integer()->minValue(2)->required()->default(2),
                        TextInput::make('max_entrants')->integer()->minValue(2)->required()->gte('min_entrants')
                            ->helperText(__('Registrations beyond this go to the waitlist.')),
                        Textarea::make('eligibility_notes')->rows(2)->columnSpanFull()
                            ->helperText(__('Published eligibility. Do not add age rules until decision D6 is resolved.')),
                        TextInput::make('roster_min')->integer()->minValue(1)->required()->default(1)->disabled($locked),
                        TextInput::make('roster_max')->integer()->minValue(1)->required()->default(1)->gte('roster_min')->disabled($locked),
                        TextInput::make('substitutes_max')->integer()->minValue(0)->required()->default(0)->disabled($locked),
                    ]),
                    Tab::make(__('Schedule & venue'))->icon(Heroicon::OutlinedCalendar)->columns(2)->schema([
                        TextInput::make('timezone')->required()->default('Asia/Baghdad')
                            ->rule('timezone')->helperText(__('IANA timezone. Times are stored in UTC and shown in this zone.')),
                        DateTimePicker::make('registration_opens_at')->seconds(false)->timezone(fn (Get $get) => $get('timezone') ?: 'UTC'),
                        DateTimePicker::make('registration_closes_at')->seconds(false)->after('registration_opens_at')->timezone(fn (Get $get) => $get('timezone') ?: 'UTC'),
                        DateTimePicker::make('check_in_closes_at')->seconds(false)->timezone(fn (Get $get) => $get('timezone') ?: 'UTC'),
                        DateTimePicker::make('starts_at')->seconds(false)->timezone(fn (Get $get) => $get('timezone') ?: 'UTC'),
                        DateTimePicker::make('ends_at')->seconds(false)->afterOrEqual('starts_at')->timezone(fn (Get $get) => $get('timezone') ?: 'UTC'),
                        TextInput::make('venue_name')->maxLength(120),
                        TextInput::make('city')->maxLength(80),
                        TextInput::make('venue_address')->maxLength(255)->columnSpanFull(),
                        TextInput::make('online_region')->maxLength(60)->helperText(__('For online e-sports events.')),
                        Textarea::make('lobby_details')->rows(2)
                            ->helperText(__('Private: shown only to staff and checked-in entrants.')),
                    ]),
                    Tab::make(__('Rules'))->icon(Heroicon::OutlinedBookOpen)->columns(2)->schema([
                        MarkdownEditor::make('rules')->columnSpanFull()->required()
                            ->helperText(__('Published rules, including scoring and tie-breakers for this sport.')),
                        Select::make('third_place_method')->options(ThirdPlaceMethod::options())->required()->native(false)->disabled($locked),
                        Textarea::make('third_place_notes')->rows(2)->helperText(__('Explain the method if "Published other" is chosen.')),
                    ]),
                    Tab::make(__('Fees & prizes'))->icon(Heroicon::OutlinedBanknotes)->columns(3)->schema([
                        Callout::make(__('Paid-entry compliance'))
                            ->description(fn () => self::paidEntryNotice())
                            ->status(fn () => FeatureFlags::enabled(FeatureFlags::PAID_ENTRY) ? 'warning' : 'info')
                            ->columnSpanFull(),
                        Select::make('funding_model')->options(FundingModel::options())->required()->default('free')->native(false)->disabled($locked),
                        TextInput::make('entry_fee_minor')
                            ->label(__('Entry fee (minor units)'))
                            ->integer()->minValue(0)->default(0)->required()
                            ->disabled($locked)
                            ->helperText(__('IQD has no minor unit, so 25000 means 25,000 IQD. Paid in person at the venue only.')),
                        Select::make('currency')->options(array_combine(Money::supportedCurrencies(), Money::supportedCurrencies()))
                            ->default('IQD')->required()->native(false)->disabled($locked),
                        Textarea::make('prize_rules')->rows(5)->columnSpanFull()->disabled($locked)
                            ->helperText(__('Publish prize amounts or formulas, deductions and refund conditions before registration opens. Label estimates as "Estimated".')),
                        Toggle::make('ranking_eligible')->disabled($locked)
                            ->helperText(__('Points rules and awards arrive in Milestone 5. No points are awarded yet.')),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold')->wrap()
                    ->description(fn (Tournament $record) => $record->is_sample_data ? new HtmlString('<span class="rivalo-sample-badge">'.e(__('Sample data')).'</span>') : null),
                TextColumn::make('discipline.name')->label(__('Discipline')),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (TournamentStatus $state) => $state->label())
                    ->color(fn (TournamentStatus $state) => self::statusColor($state)),
                TextColumn::make('starts_at')->dateTime('d M Y, H:i')->sortable()->placeholder(__('Not scheduled')),
                TextColumn::make('reserved_entries_count')->counts('reservedEntries')->label(__('Entrants'))
                    ->formatStateUsing(fn ($state, Tournament $record) => "{$state} / {$record->max_entrants}"),
                TextColumn::make('entry_fee_minor')->label(__('Entry fee'))
                    ->formatStateUsing(fn (Tournament $record) => $record->formattedEntryFee()),
            ])
            ->filters([
                SelectFilter::make('status')->options(TournamentStatus::options()),
                SelectFilter::make('discipline')->relationship('discipline', 'name'),
                TernaryFilter::make('is_sample_data')->label(__('Sample data')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function statusColor(TournamentStatus $state): string
    {
        return match ($state) {
            TournamentStatus::Draft => 'gray',
            TournamentStatus::Published => 'info',
            TournamentStatus::RegistrationOpen => 'success',
            TournamentStatus::RegistrationClosed, TournamentStatus::Postponed => 'warning',
            TournamentStatus::InProgress => 'primary',
            TournamentStatus::Completed => 'gray',
            TournamentStatus::Cancelled => 'danger',
        };
    }

    public static function getRelations(): array
    {
        return [
            EntriesRelationManager::class,
            StaffRelationManager::class,
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Tournament');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tournaments');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTournaments::route('/'),
            'create' => CreateTournament::route('/create'),
            'edit' => EditTournament::route('/{record}/edit'),
        ];
    }

    public static function paidEntryNotice(): string
    {
        return FeatureFlags::enabled(FeatureFlags::PAID_ENTRY)
            ? __('Paid-entry participation is ENABLED. Fees are collected in person by staff only; there is no online checkout.')
            : __('Paid-entry participation is DISABLED (compliance flag, decision D7). Tournaments with an entry fee above 0 can be drafted but not published. Free tournaments work normally.');
    }
}
