<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Enums\CheckInStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Concerns\HandlesDomainRules;
use App\Models\EntryPayment;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Services\VenueService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Venue check-in desk for organizers.
 *
 * Players show a QR code in the app. The QR holds a link to this page (?code=...), so staff can
 * scan it with any phone camera. Staff can also type the code or search the entrant list.
 * Identity: staff compare the roster photos and names before confirming.
 */
class VenueCheckIn extends Page
{
    use HandlesDomainRules;

    protected string $view = 'filament.pages.venue-check-in';

    protected static ?string $slug = 'check-in';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Competition;

    protected static ?int $navigationSort = 2;

    #[Url]
    public ?string $code = null;

    #[Url(as: 'tournament')]
    public ?int $tournamentId = null;

    public string $manualCode = '';

    public string $search = '';

    public ?int $entryId = null;

    public ?int $scannedMemberId = null;

    public ?string $lookupError = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->can('checkin.record') || $user->can('tournaments.manage_all'));
    }

    public static function getNavigationLabel(): string
    {
        return __('Venue check-in');
    }

    public function getTitle(): string
    {
        return __('Venue check-in');
    }

    public function mount(): void
    {
        if (filled($this->code)) {
            $this->lookup($this->code);
        }
    }

    // ---------------------------------------------------------------- Lookup

    public function lookupManual(): void
    {
        $this->lookup($this->manualCode);
        $this->manualCode = '';
    }

    private function lookup(string $input): void
    {
        $this->lookupError = null;
        $this->entryId = null;
        $this->scannedMemberId = null;

        try {
            $member = app(VenueService::class)->resolveCode($input, auth()->user());
        } catch (DomainRuleException $e) {
            $this->lookupError = $e->getMessage();

            return;
        }

        $this->reloadData();
        $this->tournamentId = $member->tournament_id;
        $this->entryId = $member->tournament_entry_id;
        $this->scannedMemberId = $member->id;
        $this->code = null;
    }

    public function openEntry(int $entryId): void
    {
        $entry = TournamentEntry::find($entryId);
        if ($entry && $entry->tournament_id === $this->tournamentId && auth()->user()->can('recordCheckIn', $entry->tournament)) {
            $this->entryId = $entry->id;
            $this->scannedMemberId = null;
            $this->lookupError = null;
            $this->reloadData();
        }
    }

    public function updatedTournamentId(): void
    {
        $this->entryId = null;
        $this->scannedMemberId = null;
        $this->search = '';
        $this->reloadData();
    }

    // ---------------------------------------------------------------- View data

    /** @return Collection<int, Tournament> */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()
            ->manageableBy(auth()->user())
            ->whereIn('status', [TournamentStatus::RegistrationOpen->value, TournamentStatus::RegistrationClosed->value, TournamentStatus::InProgress->value])
            ->orderBy('starts_at')
            ->get(['id', 'name', 'starts_at', 'status']);
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        if (! $this->tournamentId) {
            return null;
        }
        $tournament = Tournament::find($this->tournamentId);

        return $tournament && auth()->user()->can('recordCheckIn', $tournament) ? $tournament : null;
    }

    #[Computed]
    public function entry(): ?TournamentEntry
    {
        if (! $this->entryId || ! $this->tournament) {
            return null;
        }

        return TournamentEntry::with(['activeMembers.user', 'payments.recordedBy', 'checkedInBy', 'team'])
            ->where('tournament_id', $this->tournament->id)
            ->find($this->entryId);
    }

    /** @return Collection<int, TournamentEntry> */
    #[Computed]
    public function entries(): Collection
    {
        if (! $this->tournament) {
            return collect();
        }

        $term = mb_strtolower(trim($this->search));

        return TournamentEntry::query()
            ->with('activeMembers')
            ->where('tournament_id', $this->tournament->id)
            ->whereIn('registration_status', RegistrationStatus::activeValues())
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(display_name) LIKE ?', ['%'.$term.'%'])
                ->orWhereHas('activeMembers', fn ($m) => $m->whereRaw('LOWER(username_snapshot) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('LOWER(display_name_snapshot) LIKE ?', ['%'.$term.'%']))))
            ->orderByRaw("CASE WHEN check_in_status = 'checked_in' THEN 1 ELSE 0 END")
            ->orderBy('display_name')
            ->limit(60)
            ->get();
    }

    /** @return array{checked_in: int, reserved: int, paid: int} */
    #[Computed]
    public function counts(): array
    {
        if (! $this->tournament) {
            return ['checked_in' => 0, 'reserved' => 0, 'paid' => 0];
        }
        $reserved = TournamentEntry::where('tournament_id', $this->tournament->id)->where('registration_status', RegistrationStatus::Reserved->value);

        return [
            'reserved' => (clone $reserved)->count(),
            'checked_in' => (clone $reserved)->where('check_in_status', CheckInStatus::CheckedIn->value)->count(),
            'paid' => (clone $reserved)->where('payment_status', PaymentStatus::Paid->value)->count(),
        ];
    }

    /** Drop cached view data after a change so the page shows the new statuses. */
    private function reloadData(): void
    {
        unset($this->tournament, $this->entry, $this->entries, $this->counts);
    }

    // ---------------------------------------------------------------- Actions

    public function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label(fn () => __('Record cash payment').($this->tournament ? ' · '.$this->tournament->formattedEntryFee() : ''))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->visible(fn () => $this->entry
                && $this->tournament?->isPaidEntry()
                && $this->entry->registration_status === RegistrationStatus::Reserved
                && $this->entry->payment_status === PaymentStatus::Unpaid
                && auth()->user()->can('recordPayments', $this->tournament))
            ->modalHeading(__('Record cash payment'))
            ->modalDescription(fn () => __('Confirm you received :fee in cash. A receipt number is created for the player.', ['fee' => $this->tournament?->formattedEntryFee()]))
            ->schema([
                Textarea::make('note')->label(__('Note (optional)'))->rows(2)->maxLength(500),
            ])
            ->action(function (array $data) {
                $payment = self::attempt(fn () => app(VenueService::class)->recordPayment($this->entry, auth()->user(), null, $data['note'] ?? null));
                $this->reloadData();
                Notification::make()->success()->title(__('Payment recorded'))->body(__('Receipt :reference', ['reference' => $payment->reference]))->persistent()->send();
            });
    }

    public function checkInAction(): Action
    {
        return Action::make('checkIn')
            ->label(__('Confirm check-in'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->size('lg')
            ->visible(fn () => $this->entry
                && $this->entry->registration_status === RegistrationStatus::Reserved
                && $this->entry->check_in_status !== CheckInStatus::CheckedIn)
            ->disabled(fn () => ! in_array($this->entry?->payment_status, [PaymentStatus::Paid, PaymentStatus::NotRequired], true))
            ->requiresConfirmation()
            ->modalHeading(__('Confirm check-in'))
            ->modalDescription(__('Confirm the player\'s identity against the roster photos and names first.'))
            ->schema(fn () => $this->tournament?->check_in_closes_at?->isPast()
                ? [Textarea::make('late_reason')->label(__('Reason for late check-in (audited)'))->required()]
                : [])
            ->action(function (array $data) {
                $scanned = $this->scannedMemberId ? $this->entry->activeMembers->firstWhere('id', $this->scannedMemberId) : null;
                self::attempt(fn () => app(VenueService::class)->checkIn($this->entry, auth()->user(), $scanned, $data['late_reason'] ?? null));
                $this->reloadData();
                Notification::make()->success()->title(__('Checked in'))->send();
            });
    }

    public function undoCheckInAction(): Action
    {
        return Action::make('undoCheckIn')
            ->label(__('Undo check-in'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn () => $this->entry?->check_in_status === CheckInStatus::CheckedIn)
            ->schema([Textarea::make('reason')->label(__('Reason (audited)'))->required()])
            ->action(function (array $data) {
                self::attempt(fn () => app(VenueService::class)->undoCheckIn($this->entry, auth()->user(), $data['reason']));
                $this->reloadData();
                Notification::make()->success()->title(__('Check-in undone'))->send();
            });
    }

    public function voidPaymentAction(): Action
    {
        return Action::make('voidPayment')
            ->label(__('Void a payment'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn () => $this->entry
                && $this->entry->payments->whereNull('voided_at')->isNotEmpty()
                && auth()->user()->can('recordPayments', $this->tournament))
            ->modalDescription(__('Use this to correct a mistake. The record stays in history, marked void.'))
            ->schema([
                Select::make('payment_id')
                    ->label(__('Record'))
                    ->options(fn () => $this->entry?->payments->whereNull('voided_at')->mapWithKeys(fn (EntryPayment $p) => [
                        $p->id => $p->reference.' · '.($p->isRefund() ? __('Refund') : __('Payment')).' · '.$p->formattedAmount(),
                    ])->all() ?? [])
                    ->required(),
                Textarea::make('reason')->label(__('Reason (audited)'))->required(),
            ])
            ->action(function (array $data) {
                $payment = $this->entry->payments->firstWhere('id', (int) $data['payment_id']);
                self::attempt(fn () => app(VenueService::class)->voidPayment($payment, auth()->user(), $data['reason']));
                $this->reloadData();
                Notification::make()->success()->title(__('Record voided'))->send();
            });
    }

    public function refundAction(): Action
    {
        return Action::make('refund')
            ->label(__('Record refund'))
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('warning')
            ->visible(fn () => $this->entry && $this->entry->netPaidMinor() > 0 && auth()->user()->can('recordPayments', $this->tournament))
            ->modalDescription(fn () => __('Record cash handed back to the player. Received so far: :paid.', [
                'paid' => Money::format($this->entry?->netPaidMinor() ?? 0, $this->tournament?->currency ?? 'IQD'),
            ]))
            ->schema([
                TextInput::make('amount_minor')
                    ->label(__('Amount (minor units)'))
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->default(fn () => $this->entry?->netPaidMinor()),
                Textarea::make('reason')->label(__('Reason (audited)'))->required(),
            ])
            ->action(function (array $data) {
                $refund = self::attempt(fn () => app(VenueService::class)->recordRefund($this->entry, auth()->user(), (int) $data['amount_minor'], $data['reason']));
                $this->reloadData();
                Notification::make()->success()->title(__('Refund recorded'))->body(__('Receipt :reference', ['reference' => $refund->reference]))->persistent()->send();
            });
    }

    public function markIneligibleAction(): Action
    {
        return Action::make('markIneligible')
            ->label(__('Mark not eligible'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn () => $this->entry && $this->entry->eligibility_status !== EligibilityStatus::Ineligible)
            ->schema([Textarea::make('reason')->label(__('Reason (shown to the entrant)'))->required()])
            ->action(function (array $data) {
                self::attempt(fn () => app(VenueService::class)->markIneligible($this->entry, auth()->user(), $data['reason']));
                $this->reloadData();
                Notification::make()->success()->title(__('Marked not eligible'))->send();
            });
    }

    public function clearIneligibleAction(): Action
    {
        return Action::make('clearIneligible')
            ->label(__('Remove not-eligible decision'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn () => $this->entry?->eligibility_status === EligibilityStatus::Ineligible)
            ->schema([Textarea::make('reason')->label(__('Reason (audited)'))->required()])
            ->action(function (array $data) {
                self::attempt(fn () => app(VenueService::class)->clearIneligible($this->entry, auth()->user(), $data['reason']));
                $this->reloadData();
                Notification::make()->success()->title(__('Decision removed'))->send();
            });
    }
}
