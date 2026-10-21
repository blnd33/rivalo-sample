<?php

namespace App\Filament\Resources\FeatureFlags;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\FeatureFlags\Pages\EditFeatureFlag;
use App\Filament\Resources\FeatureFlags\Pages\ListFeatureFlags;
use App\Models\FeatureFlag;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
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

/**
 * Compliance and rollout switches. Every change requires a reason and is audited.
 */
class FeatureFlagResource extends Resource
{
    protected static ?string $model = FeatureFlag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextInput::make('key')->disabled(),
                    Textarea::make('description')->disabled()->rows(4),
                    Toggle::make('enabled')
                        ->helperText(__('Do not enable compliance-gated features until the required approvals are documented in docs/05-DECISIONS.md.')),
                    Textarea::make('last_change_reason')
                        ->label(__('Reason for this change'))
                        ->required()
                        ->helperText(__('Recorded in the audit log with your name.'))
                        ->formatStateUsing(fn () => null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->weight('semibold')->description(fn (FeatureFlag $r) => $r->description)->wrap(),
                IconColumn::make('enabled')->boolean(),
                IconColumn::make('is_public')->boolean()->label(__('Readable by app')),
                TextColumn::make('updatedBy.username')->label(__('Changed by'))->placeholder(__('Seeded')),
                TextColumn::make('updated_at')->since()->label(__('Changed')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Feature flag');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Feature flags');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureFlags::route('/'),
            'edit' => EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
