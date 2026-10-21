<?php

namespace App\Filament\Resources\Teams\Pages;

use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewTeam extends ViewRecord
{
    use HandlesDomainRules;

    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('forceCaptain')
                ->label(__('Force captain transfer'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('warning')
                ->visible(fn (Team $record) => $record->isActive() && auth()->user()->can('moderate', $record))
                ->modalDescription(__('Use only when the captain is unreachable or has left. The change and reason are audited.'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('New captain'))
                        ->options(fn (Team $record) => $record->activeMemberships()->with('user')->get()
                            ->reject(fn ($m) => $m->user_id === $record->captain_id)
                            ->mapWithKeys(fn ($m) => [$m->user_id => $m->user->username]))
                        ->required(),
                    Textarea::make('reason')->required()->label(__('Reason (audited)')),
                ])
                ->action(function (Team $record, array $data) {
                    self::attempt(fn () => app(TeamService::class)->forceCaptainTransfer($record, auth()->user(), User::findOrFail($data['user_id']), $data['reason']));
                    Notification::make()->success()->title(__('Captain changed'))->send();
                    $this->refreshFormData([]);
                }),
            Action::make('disband')
                ->label(__('Disband team'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (Team $record) => $record->isActive() && auth()->user()->can('moderate', $record))
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->required()->label(__('Reason (audited)'))])
                ->action(function (Team $record, array $data) {
                    self::attempt(fn () => app(TeamService::class)->disband($record, auth()->user(), $data['reason']));
                    Notification::make()->success()->title(__('Team disbanded'))->send();
                }),
        ];
    }
}
