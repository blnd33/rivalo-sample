<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\Products\ProductResource;
use App\Services\ListingService;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    use HandlesDomainRules;

    protected static string $resource = ProductResource::class;

    private const AUDITED = ['slug', 'status', 'price_minor', 'category', 'is_featured', 'max_per_order'];

    protected function getHeaderActions(): array
    {
        $canReview = fn () => $this->record->seller_id !== null && auth()->user()->can('sellers.manage');

        return [
            // Seller listings (Milestone 9): staff check product, photos and price before it goes on sale.
            Action::make('approveListing')
                ->label(__('Approve listing'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible(fn () => $canReview() && $this->record->status === ProductStatus::PendingReview)
                ->requiresConfirmation()
                ->modalDescription(__('The product goes on sale once stock is recorded. Check the name, photos, description and price first.'))
                ->action(function () {
                    self::attempt(fn () => app(ListingService::class)->approve($this->record, auth()->user()));
                    Notification::make()->success()->title(__('Listing approved'))->body(__('The seller was told in the app.'))->send();
                    $this->redirect(ProductResource::getUrl('edit', ['record' => $this->record]));
                }),
            Action::make('requestChanges')
                ->label(fn () => $this->record->status === ProductStatus::Active ? __('Take off sale') : __('Request changes'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning')
                ->visible(fn () => $canReview() && in_array($this->record->status, [ProductStatus::PendingReview, ProductStatus::Active], true))
                ->schema([Textarea::make('note')->label(__('What the seller should change'))->required()->maxLength(500)])
                ->action(function (array $data) {
                    self::attempt(fn () => app(ListingService::class)->requestChanges($this->record, auth()->user(), $data['note']));
                    Notification::make()->success()->title(__('Sent back to the seller'))->send();
                    $this->redirect(ProductResource::getUrl('edit', ['record' => $this->record]));
                }),
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $old = $record->only(self::AUDITED);
        $record->update($data);
        $changed = array_intersect_key($record->getChanges(), array_flip(self::AUDITED));
        if ($changed !== []) {
            Audit::record('product.updated', $record, array_intersect_key($old, $changed), $changed);
        }

        return $record;
    }
}
