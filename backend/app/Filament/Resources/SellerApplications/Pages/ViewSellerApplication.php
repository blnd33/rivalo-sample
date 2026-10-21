<?php

namespace App\Filament\Resources\SellerApplications\Pages;

use App\Enums\SellerApplicationStatus;
use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\SellerApplications\SellerApplicationResource;
use App\Filament\Resources\Sellers\SellerResource;
use App\Services\SellerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Approving creates the shop with the terms of the signed agreement. Nothing is pre-filled for
 * the commission: it must come from the agreement (decision D22).
 */
class ViewSellerApplication extends ViewRecord
{
    use HandlesDomainRules;

    protected static string $resource = SellerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        $pending = fn () => $this->record->status === SellerApplicationStatus::Pending && auth()->user()->can('sellers.manage');

        return [
            Action::make('approve')
                ->label(__('Approve seller'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible($pending)
                ->modalDescription(__('Approve only after the seller signed the RIVALO seller agreement. The person can then sign in to the Seller Hub with their RIVALO account.'))
                ->fillForm(fn () => ['name' => $this->record->business_name])
                ->schema([
                    TextInput::make('name')->label(__('Shop name shown in the app'))->required()->maxLength(120),
                    TextInput::make('commission_percent')->label(__('Commission (%)'))->numeric()->minValue(0)->maxValue(100)->step(0.01)->required()
                        ->helperText(__('As written in the signed agreement. Applies to orders placed from now on.')),
                    TextInput::make('agreement_reference')->label(__('Agreement reference'))->required()->maxLength(120)
                        ->helperText(__('For example the contract number or the date it was signed.')),
                    Textarea::make('verification_note')->label(__('What you checked'))->required()->minLength(10)->maxLength(500)
                        ->helperText(__('For example: ID card seen, business registration number, visited the shop. Do not upload or copy ID numbers here.')),
                    Checkbox::make('identity_checked')->label(__('I checked who this seller is and the agreement is signed.'))->accepted(),
                ])
                ->action(function (array $data) {
                    $seller = self::attempt(fn () => app(SellerService::class)->approve($this->record, auth()->user(), [
                        'name' => $data['name'],
                        'commission_bps' => (int) round(((float) $data['commission_percent']) * 100),
                        'agreement_reference' => $data['agreement_reference'],
                        'verification_note' => $data['verification_note'],
                        'identity_checked' => (bool) $data['identity_checked'],
                    ]));
                    Notification::make()->success()->title(__('Seller approved'))->body(__('The applicant was told in the app.'))->send();
                    $this->redirect(SellerResource::getUrl('view', ['record' => $seller]));
                }),
            Action::make('reject')
                ->label(__('Reject'))
                ->color('danger')
                ->icon(Heroicon::OutlinedXCircle)
                ->visible($pending)
                ->schema([
                    Textarea::make('reason')->label(__('Reason shown to the applicant'))->required()->maxLength(500),
                ])
                ->action(function (array $data) {
                    self::attempt(fn () => app(SellerService::class)->reject($this->record, auth()->user(), $data['reason']));
                    Notification::make()->success()->title(__('Application rejected'))->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
