<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class EditAnnouncement extends EditRecord
{
    use HandlesDomainRules;

    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        $service = fn () => app(AnnouncementService::class);

        return [
            Action::make('sendNow')
                ->label(__('Send now'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->visible(fn (Announcement $record) => $record->isEditable())
                ->requiresConfirmation()
                ->modalDescription(fn (Announcement $record) => __('This sends the saved version to :count people. It cannot be unsent.', ['count' => $service()->recipients($record)->count()]))
                ->action(function (Announcement $record) use ($service) {
                    self::attempt(fn () => $service()->sendNow($record, auth()->user()));
                    Notification::make()->success()->title(__('Announcement is being sent'))->send();
                    $this->redirect(AnnouncementResource::getUrl('index'));
                }),
            Action::make('schedule')
                ->label(__('Schedule'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->visible(fn (Announcement $record) => $record->isEditable())
                ->schema([DateTimePicker::make('send_at')->label(__('Send at'))->seconds(false)->required()->minDate(now())])
                ->action(function (Announcement $record, array $data) use ($service) {
                    self::attempt(fn () => $service()->schedule($record, auth()->user(), Carbon::parse($data['send_at'])));
                    Notification::make()->success()->title(__('Announcement scheduled'))->send();
                    $this->redirect(AnnouncementResource::getUrl('index'));
                }),
            Action::make('unschedule')
                ->label(__('Cancel schedule'))
                ->color('danger')
                ->visible(fn (Announcement $record) => $record->status->value === 'scheduled')
                ->requiresConfirmation()
                ->action(function (Announcement $record) use ($service) {
                    self::attempt(fn () => $service()->cancel($record, auth()->user()));
                    $this->redirect(AnnouncementResource::getUrl('edit', ['record' => $record]));
                }),
            DeleteAction::make()->visible(fn (Announcement $record) => $record->status->value === 'draft'),
        ];
    }
}
