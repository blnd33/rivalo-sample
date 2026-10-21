<?php

namespace App\Filament\Resources\Achievements;

use App\Enums\AchievementCriteria;
use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Achievements\Pages\CreateAchievement;
use App\Filament\Resources\Achievements\Pages\EditAchievement;
use App\Filament\Resources\Achievements\Pages\ListAchievements;
use App\Models\Achievement;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Badges. Automatic badges are earned from approved results, final placements and check-ins only. */
class AchievementResource extends Resource
{
    protected static ?string $model = Achievement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Community;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public const ICONS = ['flag', 'qrcode', 'checkmark_seal', 'flame', 'repeat', 'rosette', 'trophy', 'star', 'bolt', 'heart'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make(__('Badge'))->columnSpan(1)->schema([
                TextInput::make('key')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true)->disabledOn('edit'),
                TextInput::make('name')->required()->maxLength(80),
                TextInput::make('name_translations.ar')->label(__('Name (Arabic)'))->maxLength(80),
                TextInput::make('name_translations.kmr')->label(__('Name (Kurdish)'))->maxLength(80),
                TextInput::make('description')->required()->maxLength(200),
                TextInput::make('description_translations.ar')->label(__('Description (Arabic)'))->maxLength(200),
                TextInput::make('description_translations.kmr')->label(__('Description (Kurdish)'))->maxLength(200),
            ]),
            Section::make(__('How it is earned'))->columnSpan(1)->schema([
                Select::make('criteria_type')->label(__('Earned for'))->options(AchievementCriteria::options())->required()->live()->native(false)
                    ->helperText(__('Counts only approved results, confirmed placements and venue check-ins. "Manual" badges are awarded by staff with a reason.')),
                TextInput::make('threshold')->integer()->minValue(1)->maxValue(10000)->required()->default(1)
                    ->visible(fn (Get $get) => $get('criteria_type') !== 'manual'),
                Select::make('icon')->options(array_combine(self::ICONS, self::ICONS))->required()->default('rosette')->native(false),
                TextInput::make('sort_order')->integer()->default(0),
                Select::make('status')->options(['draft' => __('Draft'), 'active' => __('Active'), 'retired' => __('Retired')])->required()->default('draft')->native(false)
                    ->helperText(__('Only active badges are awarded and shown in the app.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn (Achievement $r) => $r->description),
                TextColumn::make('criteria_type')->label(__('Earned for'))->formatStateUsing(fn (AchievementCriteria $state, Achievement $r) => $state->label().($state !== AchievementCriteria::Manual ? ' ≥ '.$r->threshold : '')),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => __(ucfirst($state)))
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success', 'draft' => 'warning', default => 'gray'
                    }),
                TextColumn::make('holders_count')->label(__('Holders'))->counts(['holders' => fn ($q) => $q->whereNull('revoked_at')]),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Badge');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Badges');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAchievements::route('/'),
            'create' => CreateAchievement::route('/create'),
            'edit' => EditAchievement::route('/{record}/edit'),
        ];
    }
}
