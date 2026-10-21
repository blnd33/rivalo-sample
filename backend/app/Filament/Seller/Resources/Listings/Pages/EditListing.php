<?php

namespace App\Filament\Seller\Resources\Listings\Pages;

use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Seller\Resources\Listings\ListingResource;
use App\Services\ListingService;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** Editing is only possible while the listing is a draft or was sent back for changes (ProductPolicy). */
class EditListing extends EditRecord
{
    use HandlesDomainRules;

    protected static string $resource = ListingResource::class;

    private const AUDITED = ['price_minor', 'category', 'max_per_order', 'name_translations'];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label(__('Submit for review'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription(__('RIVALO staff check the product, photos and price. You get a message when it is on sale or if changes are needed.'))
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    self::attempt(fn () => app(ListingService::class)->submit($this->record, auth()->user()));
                    Notification::make()->success()->title(__('Submitted for review'))->send();
                    $this->redirect(ListingResource::getUrl('view', ['record' => $this->record]));
                }),
            Action::make('archive')
                ->label(__('Archive'))
                ->color('gray')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->requiresConfirmation()
                ->modalDescription(__('The product is no longer offered. Its sales and statement lines stay.'))
                ->action(function () {
                    self::attempt(fn () => app(ListingService::class)->archive($this->record, auth()->user()));
                    $this->redirect(ListingResource::getUrl());
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $old = $record->only(self::AUDITED);
        $record->update($data);
        $changed = array_intersect_key($record->getChanges(), array_flip(self::AUDITED));
        if ($changed !== []) {
            Audit::record('listing.updated', $record, array_intersect_key($old, $changed), $changed);
        }

        return $record;
    }
}
