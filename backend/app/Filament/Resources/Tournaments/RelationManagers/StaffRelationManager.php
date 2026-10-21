<?php

namespace App\Filament\Resources\Tournaments\RelationManagers;

use App\Enums\Permission;
use App\Enums\StaffRole;
use App\Filament\Concerns\HandlesDomainRules;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Services\TournamentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Organizer and referee assignments. Only global tournament managers may change them. */
class StaffRelationManager extends RelationManager
{
    use HandlesDomainRules;

    protected static string $relationship = 'staff';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Organizers & referees');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Tournament $tournament */
        $tournament = $this->getOwnerRecord();
        $canManage = auth()->user()->can('manageStaff', $tournament);

        return $table
            ->columns([
                TextColumn::make('user.username')->label(__('Staff member')),
                TextColumn::make('role')->badge()->formatStateUsing(fn (StaffRole $state) => $state->label()),
                TextColumn::make('assignedBy.username')->label(__('Assigned by'))->placeholder(__('—')),
                TextColumn::make('created_at')->since()->label(__('Assigned')),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label(__('Assign staff'))
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible($canManage)
                    ->schema([
                        Select::make('role')->options(StaffRole::options())->required()->live()->native(false),
                        Select::make('user_id')
                            ->label(__('Staff member'))
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->permission([Permission::TournamentsManageAssigned->value, Permission::ResultsReviewAssigned->value])
                                ->where('username', 'like', "%{$search}%")
                                ->limit(20)
                                ->pluck('username', 'id')),
                    ])
                    ->action(function (array $data) use ($tournament) {
                        self::attempt(fn () => app(TournamentService::class)->assignStaff(
                            $tournament, User::findOrFail($data['user_id']), auth()->user(), StaffRole::from($data['role'])));
                        Notification::make()->success()->title(__('Staff assigned'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label(__('Remove'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible($canManage)
                    ->requiresConfirmation()
                    ->action(function (TournamentStaff $record) use ($tournament) {
                        app(TournamentService::class)->removeStaff($tournament, $record->user, auth()->user(), $record->role);
                        Notification::make()->success()->title(__('Assignment removed'))->send();
                    }),
            ]);
    }
}
