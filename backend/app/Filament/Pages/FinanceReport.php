<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Services\FinanceService;
use App\Support\Audit;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Finance report across tournaments: what came in, what went out, and what is left, straight
 * from the ledger. Projections are labelled with the rules' status (draft/approved).
 */
class FinanceReport extends Page
{
    protected string $view = 'filament.pages.finance-report';

    protected static ?string $slug = 'finance-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Finance;

    protected static ?int $navigationSort = 3;

    #[Url]
    public string $scope = 'with_money';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('finance.manage');
    }

    public static function getNavigationLabel(): string
    {
        return __('Finance report');
    }

    public function getTitle(): string
    {
        return __('Finance report');
    }

    /** @return Collection<int, array{tournament: Tournament, summary: array}> */
    #[Computed]
    public function rows(): Collection
    {
        $finance = app(FinanceService::class);

        return Tournament::query()
            ->where('status', '!=', TournamentStatus::Draft->value)
            ->when($this->scope === 'with_money', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('ledgerEntries')->orWhereHas('sponsorContributions')->orWhereHas('prizes')))
            ->with('financeRule')
            ->orderByDesc('starts_at')
            ->limit(200)
            ->get()
            ->map(fn (Tournament $t) => ['tournament' => $t, 'summary' => $finance->summary($t)]);
    }

    /** Totals per currency (amounts in different currencies are never added together). */
    #[Computed]
    public function totals(): array
    {
        $totals = [];
        foreach ($this->rows as $row) {
            $s = $row['summary'];
            $c = $s['currency'];
            foreach (['net_entry', 'sponsor_cash', 'expenses', 'prizes_paid', 'organizer_paid', 'cash_on_hand', 'pledged_outstanding'] as $k) {
                $totals[$c][$k] = ($totals[$c][$k] ?? 0) + $s[$k];
            }
        }

        return $totals;
    }

    public function updatedScope(): void
    {
        unset($this->rows, $this->totals);
    }

    public function money(int $minor, string $currency): string
    {
        return Money::format($minor, $currency);
    }

    public function downloadCsvAction(): Action
    {
        return Action::make('downloadCsv')
            ->label(__('Download CSV'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function (): StreamedResponse {
                Audit::record('finance.report_exported', null, new: ['rows' => $this->rows->count()]);
                $rows = $this->rows;

                return response()->streamDownload(function () use ($rows) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['tournament_id', 'tournament', 'status', 'currency', 'rules_status', 'gross_entry_minor', 'refunds_minor',
                        'net_entry_minor', 'sponsor_cash_minor', 'expenses_minor', 'prizes_paid_minor', 'organizer_paid_minor',
                        'balance_unrestricted_minor', 'balance_prize_minor', 'balance_expense_minor', 'balance_organizer_minor',
                        'cash_on_hand_minor', 'pledged_outstanding_minor', 'is_sample_data']);
                    foreach ($rows as $row) {
                        $t = $row['tournament'];
                        $s = $row['summary'];
                        fputcsv($out, [
                            $t->id, $t->name, $t->status->value, $s['currency'], $s['projection']['status'],
                            $s['gross_entry'], $s['refunds'], $s['net_entry'], $s['sponsor_cash'], $s['expenses'],
                            $s['prizes_paid'], $s['organizer_paid'],
                            $s['balances']['unrestricted'], $s['balances']['prize'], $s['balances']['expense'], $s['balances']['organizer'],
                            $s['cash_on_hand'], $s['pledged_outstanding'], $t->is_sample_data ? 'yes' : 'no',
                        ]);
                    }
                    fclose($out);
                }, 'rivalo-finance-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    protected function getHeaderActions(): array
    {
        return [$this->downloadCsvAction()];
    }
}
