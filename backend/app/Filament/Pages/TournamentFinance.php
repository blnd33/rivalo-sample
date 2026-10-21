<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Enums\ContributionRestriction;
use App\Enums\ContributionStatus;
use App\Enums\ContributionType;
use App\Enums\LedgerFund;
use App\Enums\LedgerType;
use App\Enums\PrizeBasis;
use App\Enums\PrizeLabel;
use App\Filament\Concerns\HandlesDomainRules;
use App\Models\Sponsor;
use App\Models\SponsorContribution;
use App\Models\Tournament;
use App\Models\TournamentFinanceRule;
use App\Models\TournamentLedgerEntry;
use App\Models\TournamentPrize;
use App\Services\FinanceService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Tournament money: draft/approved finance rules, sponsor contributions, prize tiers, fund
 * balances and the append-only ledger. Every change goes through FinanceService, which
 * re-checks permissions and restrictions. Finance managers only.
 */
class TournamentFinance extends Page
{
    use HandlesDomainRules;

    protected string $view = 'filament.pages.tournament-finance';

    protected static ?string $slug = 'finance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Finance;

    protected static ?int $navigationSort = 1;

    #[Url(as: 'tournament')]
    public ?int $tournamentId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('finance.manage');
    }

    public static function getNavigationLabel(): string
    {
        return __('Tournament finance');
    }

    public function getTitle(): string
    {
        return __('Tournament finance');
    }

    public function updatedTournamentId(): void
    {
        $this->reloadData();
    }

    // ---------------------------------------------------------------- View data

    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()->orderByDesc('starts_at')->limit(300)->get(['id', 'name', 'starts_at', 'status']);
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return $this->tournamentId ? Tournament::find($this->tournamentId) : null;
    }

    #[Computed]
    public function rules(): ?TournamentFinanceRule
    {
        return $this->tournament ? $this->finance()->rules($this->tournament)->loadMissing('approvedBy') : null;
    }

    #[Computed]
    public function summary(): array
    {
        return $this->tournament ? $this->finance()->summary($this->tournament) : [];
    }

    /** @return Collection<int, array{prize: TournamentPrize, amount: ?int, paid: int}> */
    #[Computed]
    public function prizes(): Collection
    {
        if (! $this->tournament) {
            return collect();
        }
        $projection = $this->summary['projection'];

        return $this->tournament->prizes()->with('backingContribution.sponsor')->get()->map(fn (TournamentPrize $p) => [
            'prize' => $p,
            'amount' => $this->finance()->prizeAmount($p->setRelation('tournament', $this->tournament), $projection),
            'paid' => $this->finance()->prizePaid($p),
        ]);
    }

    /** @return Collection<int, SponsorContribution> */
    #[Computed]
    public function contributions(): Collection
    {
        return $this->tournament
            ? $this->tournament->sponsorContributions()->with('sponsor')->latest('id')->get()
            : collect();
    }

    /** @return Collection<int, TournamentLedgerEntry> */
    #[Computed]
    public function ledger(): Collection
    {
        return $this->tournament
            ? $this->tournament->ledgerEntries()->with(['recordedBy', 'reversedBy', 'prize'])->orderByDesc('id')->limit(300)->get()
            : collect();
    }

    public function money(?int $minor): string
    {
        return $minor === null ? __('To be announced') : Money::format($minor, $this->tournament?->currency ?? 'IQD');
    }

    private function finance(): FinanceService
    {
        return app(FinanceService::class);
    }

    private function reloadData(): void
    {
        unset($this->tournament, $this->rules, $this->summary, $this->prizes, $this->contributions, $this->ledger);
    }

    private function done(string $title, ?string $body = null): void
    {
        $this->reloadData();
        Notification::make()->success()->title($title)->body($body)->send();
    }

    private function locked(): bool
    {
        return (bool) $this->tournament?->financial_rules_locked_at;
    }

    private function amountField(string $name = 'amount_minor', ?string $label = null): TextInput
    {
        return TextInput::make($name)
            ->label($label ?? __('Amount'))
            ->integer()
            ->minValue(1)
            ->required()
            ->suffix(fn () => $this->tournament?->currency)
            ->helperText(fn () => Money::exponent($this->tournament?->currency ?? 'IQD') > 0 ? __('In minor units (cents).') : null);
    }

    private function reasonField(bool $required = true, ?string $label = null): Textarea
    {
        return Textarea::make('reason')->label($label ?? __('Reason (audited)'))->rows(2)->required($required);
    }

    /** "12.5" (percent) → 1250 basis points. Percentages are not money, but are stored as integers too. */
    private static function toBp(mixed $percent): ?int
    {
        if ($percent === null || $percent === '') {
            return null;
        }

        return (int) round(((float) $percent) * 100);
    }

    private static function fundOptions(array $funds): array
    {
        return collect($funds)->mapWithKeys(fn (LedgerFund $f) => [$f->value => $f->label()])->all();
    }

    // ---------------------------------------------------------------- Rules

    public function editRulesAction(): Action
    {
        return Action::make('editRules')
            ->label(__('Edit finance rules'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->visible(fn () => (bool) $this->tournament)
            ->modalDescription(__('Shares of net entry revenue (entry fees received minus refunds). Saving always returns the rules to Draft until they are approved again.'))
            ->fillForm(fn () => [
                'prize_pool_percent' => $this->rules?->prize_pool_bp === null ? null : $this->rules->prize_pool_bp / 100,
                'organizer_share_percent' => $this->rules?->organizer_share_bp === null ? null : $this->rules->organizer_share_bp / 100,
                'notes' => $this->rules?->notes,
            ])
            ->schema([
                TextInput::make('prize_pool_percent')->label(__('Prize pool share (%)'))->numeric()->minValue(0)->maxValue(100)->step(0.01)
                    ->helperText(__('Leave empty while undecided (decision D4).')),
                TextInput::make('organizer_share_percent')->label(__('Organizer share (%)'))->numeric()->minValue(0)->maxValue(100)->step(0.01),
                Textarea::make('notes')->label(__('Notes'))->rows(2),
                $this->reasonField(required: false, label: __('Reason for correction (required after registration opens)'))
                    ->visible(fn () => $this->locked())
                    ->required(fn () => $this->locked()),
            ])
            ->action(function (array $data) {
                self::attempt(fn () => $this->finance()->saveRules(
                    $this->tournament,
                    auth()->user(),
                    self::toBp($data['prize_pool_percent'] ?? null),
                    self::toBp($data['organizer_share_percent'] ?? null),
                    $data['notes'] ?? null,
                    $data['reason'] ?? null,
                ));
                $this->done(__('Finance rules saved as draft'));
            });
    }

    public function approveRulesAction(): Action
    {
        return Action::make('approveRules')
            ->label(__('Approve rules'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('success')
            ->visible(fn () => $this->rules && $this->rules->exists && $this->rules->isComplete() && ! $this->rules->isApproved())
            ->modalDescription(__('Approving allows organizer-share payouts and prize amounts based on these shares. Approve only rules the owner has actually decided.'))
            ->schema([Textarea::make('note')->label(__('Approval note (who decided, where it is documented)'))->required()->rows(2)])
            ->action(function (array $data) {
                self::attempt(fn () => $this->finance()->approveRules($this->tournament, auth()->user(), $data['note']));
                $this->done(__('Finance rules approved'));
            });
    }

    // ---------------------------------------------------------------- Sponsors

    public function addContributionAction(): Action
    {
        return Action::make('addContribution')
            ->label(__('Add sponsor contribution'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->visible(fn () => (bool) $this->tournament)
            ->schema([
                Select::make('sponsor_id')->label(__('Sponsor'))
                    ->options(fn () => Sponsor::where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                    ->searchable()->required()
                    ->helperText(__('Create the sponsor first under Finance → Sponsors.')),
                Select::make('type')->label(__('Type'))->options(ContributionType::options())->required()->live()->default('cash'),
                Select::make('restriction')->label(__('How the cash may be used'))
                    ->options(ContributionRestriction::options())
                    ->visible(fn (Get $get) => $get('type') === 'cash')
                    ->required(fn (Get $get) => $get('type') === 'cash')
                    ->helperText(__('Prizes only: can never pay the organizer or expenses.')),
                $this->amountField('committed_minor', __('Committed amount'))
                    ->visible(fn (Get $get) => $get('type') === 'cash')
                    ->required(fn (Get $get) => $get('type') === 'cash'),
                TextInput::make('in_kind_description')->label(__('What they provide'))
                    ->visible(fn (Get $get) => $get('type') !== 'cash')
                    ->required(fn (Get $get) => $get('type') !== 'cash')
                    ->maxLength(255),
                Textarea::make('note')->label(__('Note (optional)'))->rows(2),
            ])
            ->action(function (array $data) {
                $sponsor = Sponsor::findOrFail($data['sponsor_id']);
                self::attempt(fn () => $this->finance()->addContribution($this->tournament, $sponsor, auth()->user(), $data));
                $this->done(__('Contribution added'));
            });
    }

    public function receiveContributionAction(): Action
    {
        return Action::make('receiveContribution')
            ->label(__('Record sponsor cash received'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->visible(fn () => $this->contributions->contains(fn ($c) => $c->isCash() && in_array($c->status, [ContributionStatus::Pledged, ContributionStatus::PartiallyReceived], true)))
            ->schema([
                Select::make('contribution_id')->label(__('Contribution'))
                    ->options(fn () => $this->contributions
                        ->filter(fn ($c) => $c->isCash() && in_array($c->status, [ContributionStatus::Pledged, ContributionStatus::PartiallyReceived], true))
                        ->mapWithKeys(fn ($c) => [$c->id => $c->sponsor->name.' · '.$c->summary().' · '.__('received').' '.Money::format($c->receivedMinor(), $c->currency)])
                        ->all())
                    ->required(),
                $this->amountField(),
                Textarea::make('note')->label(__('Note (optional)'))->rows(2),
            ])
            ->action(function (array $data) {
                $c = $this->contributions->firstWhere('id', (int) $data['contribution_id']);
                $entry = self::attempt(fn () => $this->finance()->receiveContribution($c, auth()->user(), (int) $data['amount_minor'], $data['note'] ?? null));
                $this->done(__('Sponsor cash recorded'), $entry->reference);
            });
    }

    public function cancelContributionAction(): Action
    {
        return Action::make('cancelContribution')
            ->label(__('Cancel a contribution'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn () => $this->contributions->contains(fn ($c) => $c->status === ContributionStatus::Pledged))
            ->schema([
                Select::make('contribution_id')->label(__('Contribution'))
                    ->options(fn () => $this->contributions->where('status', ContributionStatus::Pledged)->mapWithKeys(fn ($c) => [$c->id => $c->sponsor->name.' · '.$c->summary()])->all())
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data) {
                $c = $this->contributions->firstWhere('id', (int) $data['contribution_id']);
                self::attempt(fn () => $this->finance()->cancelContribution($c, auth()->user(), $data['reason']));
                $this->done(__('Contribution cancelled'));
            });
    }

    // ---------------------------------------------------------------- Prizes

    private function prizeSchema(): array
    {
        return [
            TextInput::make('place')->label(__('Place'))->integer()->minValue(1)->maxValue(64)->required(),
            TextInput::make('title')->label(__('Title (optional)'))->maxLength(80)->placeholder(__('Champion')),
            Select::make('basis')->label(__('Basis'))->options(PrizeBasis::options())->required()->live()->default('fixed'),
            $this->amountField('amount_minor', __('Cash amount'))
                ->required(false)
                ->visible(fn (Get $get) => $get('basis') === 'fixed'),
            TextInput::make('pool_share_percent')->label(__('Share of prize pool (%)'))->numeric()->minValue(0.01)->maxValue(100)->step(0.01)
                ->visible(fn (Get $get) => $get('basis') === 'pool_share')
                ->required(fn (Get $get) => $get('basis') === 'pool_share'),
            TextInput::make('in_kind_description')->label(__('Item (trophy, medals…)'))->maxLength(255),
            Select::make('label')->label(__('Label'))->options(PrizeLabel::options())->required()->live()->default('estimated')
                ->helperText(__('Guaranteed needs sponsor cash already received. Finalized pool shares need approved rules and closed registration.')),
            Select::make('backing_contribution_id')->label(__('Backed by'))
                ->options(fn () => $this->contributions
                    ->filter(fn ($c) => $c->isCash() && $c->status !== ContributionStatus::Cancelled && $c->restriction !== ContributionRestriction::ExpensesOnly)
                    ->mapWithKeys(fn ($c) => [$c->id => $c->sponsor->name.' · '.__('received').' '.Money::format($c->receivedMinor(), $c->currency)])
                    ->all())
                ->visible(fn (Get $get) => $get('label') === 'guaranteed')
                ->required(fn (Get $get) => $get('label') === 'guaranteed'),
        ];
    }

    private static function prizeData(array $data): array
    {
        $data['pool_share_bp'] = self::toBp($data['pool_share_percent'] ?? null);

        return $data;
    }

    public function addPrizeAction(): Action
    {
        return Action::make('addPrize')
            ->label(__('Add prize'))
            ->icon(Heroicon::OutlinedTrophy)
            ->visible(fn () => (bool) $this->tournament)
            ->schema(fn () => [
                ...$this->prizeSchema(),
                $this->reasonField(required: false, label: __('Reason (required after registration opens)'))->visible(fn () => $this->locked()),
            ])
            ->action(function (array $data) {
                self::attempt(fn () => $this->finance()->savePrize($this->tournament, auth()->user(), self::prizeData($data), null, $data['reason'] ?? null));
                $this->done(__('Prize saved'));
            });
    }

    public function editPrizeAction(): Action
    {
        return Action::make('editPrize')
            ->label(__('Edit a prize'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn () => $this->prizes->isNotEmpty())
            ->schema(fn () => [
                Select::make('prize_id')->label(__('Prize'))
                    ->options(fn () => $this->prizes->mapWithKeys(fn ($row) => [$row['prize']->id => '#'.$row['prize']->place.' · '.$row['prize']->displayTitle()])->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $p = $this->prizes->firstWhere(fn ($row) => $row['prize']->id === (int) $state)['prize'] ?? null;
                        if ($p) {
                            foreach (['place', 'title', 'amount_minor', 'in_kind_description', 'backing_contribution_id'] as $f) {
                                $set($f, $p->{$f});
                            }
                            $set('basis', $p->basis->value);
                            $set('label', $p->label->value);
                            $set('pool_share_percent', $p->pool_share_bp === null ? null : $p->pool_share_bp / 100);
                        }
                    }),
                ...$this->prizeSchema(),
                $this->reasonField(required: false, label: __('Reason (required after registration opens, except to make a prize more certain)'))->visible(fn () => $this->locked()),
            ])
            ->action(function (array $data) {
                $prize = $this->prizes->firstWhere(fn ($row) => $row['prize']->id === (int) $data['prize_id'])['prize'];
                self::attempt(fn () => $this->finance()->savePrize($this->tournament, auth()->user(), self::prizeData($data), $prize, $data['reason'] ?? null));
                $this->done(__('Prize saved'));
            });
    }

    public function deletePrizeAction(): Action
    {
        return Action::make('deletePrize')
            ->label(__('Remove a prize'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn () => $this->prizes->isNotEmpty())
            ->schema(fn () => [
                Select::make('prize_id')->label(__('Prize'))
                    ->options(fn () => $this->prizes->mapWithKeys(fn ($row) => [$row['prize']->id => '#'.$row['prize']->place.' · '.$row['prize']->displayTitle()])->all())
                    ->required(),
                $this->reasonField(required: false)->required(fn () => $this->locked()),
            ])
            ->action(function (array $data) {
                $prize = $this->prizes->firstWhere(fn ($row) => $row['prize']->id === (int) $data['prize_id'])['prize'];
                self::attempt(fn () => $this->finance()->deletePrize($prize, auth()->user(), $data['reason'] ?? null));
                $this->done(__('Prize removed'));
            });
    }

    // ---------------------------------------------------------------- Ledger

    public function recordExpenseAction(): Action
    {
        return Action::make('recordExpense')
            ->label(__('Record expense'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('warning')
            ->visible(fn () => (bool) $this->tournament)
            ->modalDescription(__('Record cash actually paid out. It must come from the expense fund or unrestricted money.'))
            ->schema([
                Select::make('type')->label(__('Expense type'))->options(collect(LedgerType::expenses())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all())->required(),
                Select::make('fund')->label(__('Paid from'))->options(self::fundOptions([LedgerFund::Expense, LedgerFund::Unrestricted]))->required()->default('expense'),
                $this->amountField(),
                TextInput::make('payee')->label(__('Paid to'))->required()->maxLength(160),
                Textarea::make('note')->label(__('What it was for'))->required()->rows(2),
            ])
            ->action(function (array $data) {
                $entry = self::attempt(fn () => $this->finance()->recordExpense(
                    $this->tournament, auth()->user(), LedgerType::from($data['type']), (int) $data['amount_minor'],
                    LedgerFund::from($data['fund']), $data['payee'], $data['note'],
                ));
                $this->done(__('Expense recorded'), $entry->reference);
            });
    }

    public function allocateAction(): Action
    {
        return Action::make('allocate')
            ->label(__('Set aside unrestricted money'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn () => ($this->summary['balances']['unrestricted'] ?? 0) > 0)
            ->modalDescription(__('Moves unrestricted money into a purpose fund. Restricted sponsor money can never be moved.'))
            ->schema([
                Select::make('to')->label(__('Into'))->options(self::fundOptions([LedgerFund::Prize, LedgerFund::Expense, LedgerFund::Organizer]))->required(),
                $this->amountField(),
                $this->reasonField(),
            ])
            ->action(function (array $data) {
                self::attempt(fn () => $this->finance()->allocate($this->tournament, auth()->user(), LedgerFund::from($data['to']), (int) $data['amount_minor'], $data['reason']));
                $this->done(__('Money set aside'));
            });
    }

    public function organizerPayoutAction(): Action
    {
        return Action::make('organizerPayout')
            ->label(__('Record organizer payout'))
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('gray')
            ->visible(fn () => (bool) $this->rules?->isApproved())
            ->modalDescription(fn () => __('Organizer share under the approved rules: :share. Record only cash actually handed over.', [
                'share' => $this->money($this->summary['projection']['organizer_share'] ?? null),
            ]))
            ->schema([
                Select::make('fund')->label(__('Paid from'))->options(self::fundOptions([LedgerFund::Organizer, LedgerFund::Unrestricted]))->required()->default('organizer'),
                $this->amountField(),
                TextInput::make('payee')->label(__('Paid to'))->required()->maxLength(160),
                Textarea::make('note')->label(__('Note (optional)'))->rows(2),
            ])
            ->action(function (array $data) {
                $entry = self::attempt(fn () => $this->finance()->recordOrganizerPayout(
                    $this->tournament, auth()->user(), (int) $data['amount_minor'], LedgerFund::from($data['fund']), $data['payee'], $data['note'] ?? null,
                ));
                $this->done(__('Organizer payout recorded'), $entry->reference);
            });
    }

    public function prizePayoutAction(): Action
    {
        return Action::make('prizePayout')
            ->label(__('Record prize payout'))
            ->icon(Heroicon::OutlinedTrophy)
            ->color('success')
            ->visible(fn () => $this->tournament?->status->value === 'completed' && $this->prizes->contains(fn ($row) => $row['prize']->label !== PrizeLabel::Estimated && $row['amount'] !== null))
            ->modalDescription(__('Record cash actually handed to the winner. Payments are not made by the app.'))
            ->schema([
                Select::make('prize_id')->label(__('Prize'))
                    ->options(fn () => $this->prizes
                        ->filter(fn ($row) => $row['prize']->label !== PrizeLabel::Estimated && $row['amount'] !== null)
                        ->mapWithKeys(fn ($row) => [$row['prize']->id => '#'.$row['prize']->place.' · '.$this->money($row['amount']).' · '.__('paid').' '.$this->money($row['paid'])])
                        ->all())
                    ->required(),
                Select::make('fund')->label(__('Paid from'))->options(self::fundOptions([LedgerFund::Prize, LedgerFund::Unrestricted]))->required()->default('prize'),
                $this->amountField(),
                TextInput::make('payee')->label(__('Winner (who received it)'))->required()->maxLength(160),
                Textarea::make('note')->label(__('Note (optional)'))->rows(2),
            ])
            ->action(function (array $data) {
                $prize = $this->prizes->firstWhere(fn ($row) => $row['prize']->id === (int) $data['prize_id'])['prize'];
                $entry = self::attempt(fn () => $this->finance()->recordPrizePayout(
                    $prize, auth()->user(), (int) $data['amount_minor'], LedgerFund::from($data['fund']), $data['payee'], $data['note'] ?? null,
                ));
                $this->done(__('Prize payout recorded'), $entry->reference);
            });
    }

    public function reverseEntryAction(): Action
    {
        return Action::make('reverseEntry')
            ->label(__('Reverse an entry'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn () => $this->reversible()->isNotEmpty())
            ->modalDescription(__('Corrections never edit or delete records: an opposite entry is added. Entry fees and refunds are corrected at the check-in desk.'))
            ->schema([
                Select::make('entry_id')->label(__('Entry'))
                    ->options(fn () => $this->reversible()->mapWithKeys(fn (TournamentLedgerEntry $e) => [
                        $e->id => $e->reference.' · '.$e->type->label().' · '.$e->formattedAmount(),
                    ])->all())
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data) {
                $entry = $this->ledger->firstWhere('id', (int) $data['entry_id']);
                self::attempt(fn () => $this->finance()->reverse($entry, auth()->user(), $data['reason']));
                $this->done(__('Entry reversed'));
            });
    }

    /** @return Collection<int, TournamentLedgerEntry> */
    private function reversible(): Collection
    {
        $seenGroups = [];

        return $this->ledger->filter(function (TournamentLedgerEntry $e) use (&$seenGroups) {
            if ($e->type->isAutomatic() || $e->type === LedgerType::Reversal || $e->reversedBy) {
                return false;
            }
            // Show each allocation pair once (the incoming leg).
            if ($e->transfer_group) {
                if (isset($seenGroups[$e->transfer_group]) || $e->amount_minor < 0) {
                    return false;
                }
                $seenGroups[$e->transfer_group] = true;
            }

            return true;
        });
    }
}
