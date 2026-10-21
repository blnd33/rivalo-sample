<?php

namespace App\Filament\Resources\Teams;

use App\Enums\AdminNavigationGroup;
use App\Enums\TeamStatus;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Filament\Resources\Teams\Pages\ViewTeam;
use App\Filament\Resources\Teams\RelationManagers\MembershipsRelationManager;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Teams are owned by their captains. Staff can view, force a captain transfer (with reason) and
 * disband for moderation. Teams are never deleted, to preserve history.
 */
class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Community;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    ImageEntry::make('logo_path')->disk('public')->label(__('Logo'))->circular()->placeholder(__('No logo')),
                    TextEntry::make('name'),
                    TextEntry::make('sport.name')->label(__('Sport')),
                    TextEntry::make('captain.username')->label(__('Captain')),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (TeamStatus $state) => $state->label()),
                    TextEntry::make('created_at')->dateTime(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold')
                    ->description(fn (Team $record) => $record->is_sample_data ? new HtmlString('<span class="rivalo-sample-badge">'.e(__('Sample data')).'</span>') : null),
                TextColumn::make('sport.name')->label(__('Sport')),
                TextColumn::make('captain.username')->label(__('Captain'))->searchable(),
                TextColumn::make('active_memberships_count')->counts('activeMemberships')->label(__('Members')),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (TeamStatus $state) => $state->label())
                    ->color(fn (TeamStatus $state) => $state === TeamStatus::Active ? 'success' : 'gray'),
                TextColumn::make('created_at')->since()->label(__('Created')),
            ])
            ->filters([
                SelectFilter::make('sport')->relationship('sport', 'name'),
                SelectFilter::make('status')->options(TeamStatus::options()),
                TernaryFilter::make('is_sample_data')->label(__('Sample data')),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [MembershipsRelationManager::class];
    }

    public static function getModelLabel(): string
    {
        return __('Team');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Teams');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'view' => ViewTeam::route('/{record}'),
        ];
    }
}
