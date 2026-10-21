<?php

namespace App\Filament\Resources\Achievements\Pages;

use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\Achievements\AchievementResource;
use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAchievement extends EditRecord
{
    use HandlesDomainRules;

    protected static string $resource = AchievementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('award')
                ->label(__('Award to a player'))
                ->icon(Heroicon::OutlinedGift)
                ->visible(fn (Achievement $record) => $record->isActive())
                ->schema([
                    Select::make('user_id')->label(__('Player'))->searchable()
                        ->getSearchResultsUsing(fn (string $search) => User::where('username', 'like', mb_strtolower($search).'%')->limit(20)->pluck('username', 'id'))
                        ->required(),
                    Textarea::make('reason')->label(__('Reason (audited)'))->required(),
                ])
                ->action(function (Achievement $record, array $data) {
                    self::attempt(fn () => app(AchievementService::class)->awardManually($record, User::findOrFail($data['user_id']), auth()->user(), $data['reason']));
                    Notification::make()->success()->title(__('Badge awarded'))->send();
                }),
            Action::make('revoke')
                ->label(__('Remove from a player'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (Achievement $record) => $record->holders()->whereNull('revoked_at')->exists())
                ->schema([
                    Select::make('holder_id')->label(__('Holder'))
                        ->options(fn (Achievement $record) => $record->holders()->whereNull('revoked_at')->with('user')->limit(200)->get()->mapWithKeys(fn ($h) => [$h->id => $h->user->username]))
                        ->searchable()->required(),
                    Textarea::make('reason')->label(__('Reason (audited)'))->required(),
                ])
                ->action(function (array $data) {
                    self::attempt(fn () => app(AchievementService::class)->revoke(UserAchievement::findOrFail($data['holder_id']), auth()->user(), $data['reason']));
                    Notification::make()->success()->title(__('Badge removed'))->send();
                }),
            DeleteAction::make(),
        ];
    }
}
