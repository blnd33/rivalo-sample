<?php

namespace App\Filament\Resources\Sellers\Pages;

use App\Enums\PayoutMethod;
use App\Enums\SellerStatus;
use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\Sellers\SellerResource;
use App\Services\SellerLedgerService;
use App\Services\SellerService;
use App\Services\StoreService;
use App\Support\SellerStatementCsv;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Seller page. Payouts and adjustments need finance staff and are recorded after the money
 * actually moved; RIVALO never pays anyone automatically.
 */
class ViewSeller extends ViewRecord
{
    use HandlesDomainRules;

    protected static string $resource = SellerResource::class;

    private function done(string $title): void
    {
        Notification::make()->success()->title($title)->send();
        $this->redirect(SellerResource::getUrl('view', ['record' => $this->record]));
    }

    protected function getHeaderActions(): array
    {
        $currency = StoreService::currency();
        $canManage = fn () => auth()->user()->can('sellers.manage') && $this->record->user_id !== auth()->id();
        $canFinance = fn () => auth()->user()->can('finance.manage') && $this->record->user_id !== auth()->id();

        return [
            Action::make('payout')
                ->label(__('Record payout'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->visible($canFinance)
                ->modalDescription(__('Record money you already paid to the seller. Nothing is transferred by RIVALO.'))
                ->schema([
                    TextInput::make('amount_minor')->label(__('Amount paid (minor units)'))->integer()->minValue(1)->required()->suffix($currency),
                    Select::make('method')->label(__('Paid by'))->options(PayoutMethod::options())->required()->native(false)->live(),
                    TextInput::make('reference')->label(__('Reference (bank transfer or receipt)'))->maxLength(120)
                        ->required(fn ($get) => $get('method') === PayoutMethod::BankTransfer->value),
                    DatePicker::make('paid_at')->label(__('Paid on'))->required()->default(now())->maxDate(now()),
                    TextInput::make('note')->label(__('Note'))->maxLength(500),
                ])
                ->action(function (array $data) {
                    self::attempt(fn () => app(SellerLedgerService::class)->recordPayout(
                        $this->record, auth()->user(), (int) $data['amount_minor'], PayoutMethod::from($data['method']),
                        $data['reference'] ?? null, Carbon::parse($data['paid_at'])->endOfDay()->min(now()), $data['note'] ?? null,
                    ));
                    $this->done(__('Payout recorded'));
                }),
            ActionGroup::make([
                Action::make('adjustment')
                    ->label(__('Record adjustment'))
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible($canFinance)
                    ->modalDescription(__('Only for corrections both sides agreed, for example a damaged item the seller accepted to bear. Use a minus sign when the seller owes RIVALO.'))
                    ->schema([
                        TextInput::make('amount_minor')->label(__('Amount (minor units)'))->integer()->required()->suffix($currency)->notIn([0]),
                        Textarea::make('reason')->label(__('Reason (shown to the seller)'))->required()->maxLength(500),
                    ])
                    ->action(function (array $data) {
                        self::attempt(fn () => app(SellerLedgerService::class)->recordAdjustment($this->record, auth()->user(), (int) $data['amount_minor'], $data['reason']));
                        $this->done(__('Adjustment recorded'));
                    }),
                Action::make('commission')
                    ->label(__('Change commission'))
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->visible($canManage)
                    ->modalDescription(__('Only after a new agreement is signed. Orders already placed keep the old rate.'))
                    ->fillForm(fn () => ['commission_percent' => $this->record->commission_bps / 100])
                    ->schema([
                        TextInput::make('commission_percent')->label(__('Commission (%)'))->numeric()->minValue(0)->maxValue(100)->step(0.01)->required(),
                        TextInput::make('agreement_reference')->label(__('New agreement reference'))->required()->maxLength(120),
                    ])
                    ->action(function (array $data) {
                        self::attempt(fn () => app(SellerService::class)->changeCommission($this->record, auth()->user(), (int) round(((float) $data['commission_percent']) * 100), $data['agreement_reference']));
                        $this->done(__('Commission changed'));
                    }),
                Action::make('suspend')
                    ->label(__('Pause shop'))
                    ->icon(Heroicon::OutlinedPauseCircle)
                    ->color('warning')
                    ->visible(fn () => $canManage() && $this->record->status === SellerStatus::Active)
                    ->modalDescription(__('The shop\'s products leave the store at once. Orders already placed continue. The seller can still read their statement.'))
                    ->schema([Textarea::make('reason')->label(__('Reason (shown to the seller)'))->required()->maxLength(500)])
                    ->action(function (array $data) {
                        self::attempt(fn () => app(SellerService::class)->suspend($this->record, auth()->user(), $data['reason']));
                        $this->done(__('Shop paused'));
                    }),
                Action::make('reinstate')
                    ->label(__('Reopen shop'))
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->visible(fn () => $canManage() && $this->record->status === SellerStatus::Suspended)
                    ->requiresConfirmation()
                    ->action(function () {
                        self::attempt(fn () => app(SellerService::class)->reinstate($this->record, auth()->user()));
                        $this->done(__('Shop reopened'));
                    }),
                Action::make('close')
                    ->label(__('Close shop'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('danger')
                    ->visible(fn () => $canManage() && $this->record->status !== SellerStatus::Closed)
                    ->modalDescription(__('All the shop\'s products are archived. Hand unsold stock back with "Returned to seller" and settle the balance.'))
                    ->schema([Textarea::make('reason')->label(__('Reason (shown to the seller)'))->required()->maxLength(500)])
                    ->action(function (array $data) {
                        self::attempt(fn () => app(SellerService::class)->close($this->record, auth()->user(), $data['reason']));
                        $this->done(__('Shop closed'));
                    }),
                Action::make('csv')
                    ->label(__('Download statement (CSV)'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn () => SellerStatementCsv::download($this->record)),
            ])->label(__('More'))->button()->color('gray'),
            EditAction::make()->visible($canManage),
        ];
    }
}
