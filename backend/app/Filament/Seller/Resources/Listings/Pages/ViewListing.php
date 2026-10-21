<?php

namespace App\Filament\Seller\Resources\Listings\Pages;

use App\Enums\ProductStatus;
use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Seller\Resources\Listings\ListingResource;
use App\Services\ListingService;
use App\Support\SellerContext;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewListing extends ViewRecord
{
    use HandlesDomainRules;

    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('withdraw')
                ->label(fn () => $this->record->status === ProductStatus::Active ? __('Take off sale to edit') : __('Stop the review to edit'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn () => SellerContext::canWrite() && in_array($this->record->status, [ProductStatus::Active, ProductStatus::PendingReview], true))
                ->requiresConfirmation()
                ->modalDescription(__('The product leaves the store until RIVALO approves it again. Orders already placed are not affected.'))
                ->action(function () {
                    self::attempt(fn () => app(ListingService::class)->withdraw($this->record, auth()->user()));
                    Notification::make()->success()->title(__('Back to draft'))->send();
                    $this->redirect(ListingResource::getUrl('edit', ['record' => $this->record]));
                }),
            EditAction::make(),
        ];
    }
}
