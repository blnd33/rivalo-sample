@php
    use App\Enums\ContributionStatus;
    use App\Enums\PrizeLabel;
    use App\Models\TournamentFinanceRule;

    $tournament = $this->tournament;
    $rules = $this->rules;
    $s = $this->summary;

    $labelColor = fn (PrizeLabel $l) => match ($l) {
        PrizeLabel::Guaranteed => 'success',
        PrizeLabel::Finalized => 'info',
        PrizeLabel::Estimated => 'warning',
    };
    $statusColor = fn (ContributionStatus $st) => match ($st) {
        ContributionStatus::Received => 'success',
        ContributionStatus::PartiallyReceived => 'info',
        ContributionStatus::Pledged => 'warning',
        ContributionStatus::Cancelled => 'gray',
    };
    $tile = 'padding:.85rem 1rem;border-radius:.75rem;border:1px solid rgba(255,255,255,.1)';
    $th = 'text-align:start;padding:.35rem .5rem;font-weight:600;opacity:.7';
    $td = 'padding:.45rem .5rem;border-top:1px solid rgba(255,255,255,.08)';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end">
            <div style="flex:1;min-width:260px">
                <label for="tournament" style="display:block;font-size:.875rem;font-weight:500;margin-bottom:.25rem">{{ __('Tournament') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="tournament" wire:model.live="tournamentId">
                        <option value="">{{ __('Choose a tournament') }}</option>
                        @foreach ($this->tournaments as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} · {{ $t->starts_at?->format('d M Y') }} · {{ $t->status->label() }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    @if ($tournament)
        {{-- Rules --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Finance rules') }}</x-slot>
            <x-slot name="description">{{ __('Shares of net entry revenue (entry fees received minus refunds).') }}</x-slot>

            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
                @if ($rules->isApproved())
                    <x-filament::badge color="success" icon="heroicon-o-shield-check">{{ __('Approved') }}</x-filament::badge>
                @else
                    <x-filament::badge color="warning" icon="heroicon-o-pencil-square">{{ __('Draft: projections only, no formula-based payouts') }}</x-filament::badge>
                @endif
                @if ($tournament->financial_rules_locked_at)
                    <x-filament::badge color="gray" icon="heroicon-o-lock-closed">{{ __('Locked since :date: changes need a reason', ['date' => $tournament->financial_rules_locked_at->format('d M Y')]) }}</x-filament::badge>
                @endif
            </div>

            <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                <div style="{{ $tile }}"><div style="font-size:.8rem;opacity:.7">{{ __('Prize pool share') }}</div><div style="font-size:1.4rem;font-weight:700">{{ TournamentFinanceRule::percent($rules->prize_pool_bp) }}</div></div>
                <div style="{{ $tile }}"><div style="font-size:.8rem;opacity:.7">{{ __('Organizer share') }}</div><div style="font-size:1.4rem;font-weight:700">{{ TournamentFinanceRule::percent($rules->organizer_share_bp) }}</div></div>
                <div style="{{ $tile }}"><div style="font-size:.8rem;opacity:.7">{{ __('Projected prize pool') }}</div><div style="font-size:1.4rem;font-weight:700">{{ $this->money($s['projection']['prize_pool']) }}</div><div style="font-size:.75rem;opacity:.6">{{ __('Includes prize-only sponsor cash received') }}</div></div>
                <div style="{{ $tile }}"><div style="font-size:.8rem;opacity:.7">{{ __('Projected organizer share') }}</div><div style="font-size:1.4rem;font-weight:700">{{ $this->money($s['projection']['organizer_share']) }}</div></div>
            </div>

            @if ($rules->isApproved())
                <p style="margin-top:.75rem;font-size:.85rem;opacity:.75">{{ __('Approved by :user on :date: :note', ['user' => $rules->approvedBy?->username ?? '—', 'date' => $rules->approved_at?->format('d M Y H:i'), 'note' => $rules->approval_note]) }}</p>
            @endif
            @if ($rules->notes)
                <p style="margin-top:.5rem;font-size:.85rem;opacity:.75">{{ $rules->notes }}</p>
            @endif

            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem">
                @foreach (['editRules', 'approveRules'] as $name)
                    @php($action = $this->getAction($name))
                    @if ($action?->isVisible()) {{ $action }} @endif
                @endforeach
            </div>
        </x-filament::section>

        {{-- Money overview --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Money overview') }}</x-slot>
            <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                @foreach ([
                    __('Cash on hand') => $s['cash_on_hand'],
                    __('Net entry revenue') => $s['net_entry'],
                    __('Refunds') => $s['refunds'],
                    __('Sponsor cash received') => $s['sponsor_cash'],
                    __('Expenses paid') => $s['expenses'],
                    __('Prizes paid') => $s['prizes_paid'],
                    __('Organizer paid') => $s['organizer_paid'],
                    __('Sponsor pledges not yet received') => $s['pledged_outstanding'],
                ] as $label => $value)
                    <div style="{{ $tile }}"><div style="font-size:.8rem;opacity:.7">{{ $label }}</div><div style="font-size:1.15rem;font-weight:700">{{ $this->money($value) }}</div></div>
                @endforeach
            </div>

            <h3 style="font-weight:600;margin:1.25rem 0 .5rem">{{ __('Funds') }}</h3>
            <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                @foreach (\App\Enums\LedgerFund::cases() as $fund)
                    @php($bal = $s['balances'][$fund->value] ?? 0)
                    <div style="{{ $tile }};{{ $bal < 0 ? 'border-color:rgba(255,98,109,.6)' : '' }}">
                        <div style="font-size:.8rem;opacity:.7">{{ $fund->label() }}</div>
                        <div style="font-size:1.15rem;font-weight:700;{{ $bal < 0 ? 'color:#FF626D' : '' }}">{{ $this->money($bal) }}</div>
                    </div>
                @endforeach
            </div>
            <p style="margin-top:.5rem;font-size:.8rem;opacity:.65">{{ __('Prize fund money can only pay prizes. Expense fund money can only pay expenses. Only unrestricted money can be set aside or pay the organizer.') }}</p>

            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem">
                @foreach (['recordExpense', 'allocate', 'organizerPayout', 'prizePayout', 'reverseEntry'] as $name)
                    @php($action = $this->getAction($name))
                    @if ($action?->isVisible()) {{ $action }} @endif
                @endforeach
            </div>
        </x-filament::section>

        {{-- Prizes --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Prizes') }}</x-slot>
            <x-slot name="description">{{ __('Estimated: may change. Finalized: confirmed amount. Guaranteed: backed by sponsor cash already received.') }}</x-slot>
            @if ($this->prizes->isEmpty())
                <p style="opacity:.7">{{ __('No prizes yet.') }}</p>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                        <thead><tr>
                            <th style="{{ $th }}">#</th><th style="{{ $th }}">{{ __('Prize') }}</th><th style="{{ $th }}">{{ __('Basis') }}</th>
                            <th style="{{ $th }}">{{ __('Amount') }}</th><th style="{{ $th }}">{{ __('Item') }}</th><th style="{{ $th }}">{{ __('Label') }}</th><th style="{{ $th }}">{{ __('Paid') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($this->prizes as $row)
                                @php($p = $row['prize'])
                                <tr>
                                    <td style="{{ $td }};font-weight:700">{{ $p->place }}</td>
                                    <td style="{{ $td }}">{{ $p->displayTitle() }}</td>
                                    <td style="{{ $td }}">{{ $p->basis->label() }}{{ $p->pool_share_bp ? ' · '.TournamentFinanceRule::percent($p->pool_share_bp) : '' }}</td>
                                    <td style="{{ $td }}">{{ $p->amount_minor === null && $p->pool_share_bp === null ? '—' : $this->money($row['amount']) }}</td>
                                    <td style="{{ $td }}">{{ $p->in_kind_description ?? '—' }}</td>
                                    <td style="{{ $td }}">
                                        <x-filament::badge :color="$labelColor($p->label)">{{ $p->label->label() }}</x-filament::badge>
                                        @if ($p->backingContribution)<div style="font-size:.75rem;opacity:.65">{{ __('Backed by :sponsor', ['sponsor' => $p->backingContribution->sponsor->name]) }}</div>@endif
                                    </td>
                                    <td style="{{ $td }}">{{ $this->money($row['paid']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem">
                @foreach (['addPrize', 'editPrize', 'deletePrize'] as $name)
                    @php($action = $this->getAction($name))
                    @if ($action?->isVisible()) {{ $action }} @endif
                @endforeach
            </div>
        </x-filament::section>

        {{-- Sponsors --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Sponsor contributions') }}</x-slot>
            @if ($this->contributions->isEmpty())
                <p style="opacity:.7">{{ __('No sponsor contributions yet.') }}</p>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                        <thead><tr>
                            <th style="{{ $th }}">{{ __('Sponsor') }}</th><th style="{{ $th }}">{{ __('Type') }}</th><th style="{{ $th }}">{{ __('How it may be used') }}</th>
                            <th style="{{ $th }}">{{ __('Committed') }}</th><th style="{{ $th }}">{{ __('Received') }}</th><th style="{{ $th }}">{{ __('Status') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($this->contributions as $c)
                                <tr style="{{ $c->status === ContributionStatus::Cancelled ? 'opacity:.55' : '' }}">
                                    <td style="{{ $td }};font-weight:600">{{ $c->sponsor->name }}@if ($c->is_sample_data || $c->sponsor->is_sample_data) <x-filament::badge color="warning" size="sm">{{ __('Sample data') }}</x-filament::badge>@endif</td>
                                    <td style="{{ $td }}">{{ $c->type->label() }}{{ $c->in_kind_description ? ': '.$c->in_kind_description : '' }}</td>
                                    <td style="{{ $td }}">{{ $c->restriction?->label() ?? '—' }}</td>
                                    <td style="{{ $td }}">{{ $c->isCash() ? $this->money($c->committed_minor) : '—' }}</td>
                                    <td style="{{ $td }}">{{ $c->isCash() ? $this->money($c->receivedMinor()) : '—' }}</td>
                                    <td style="{{ $td }}"><x-filament::badge :color="$statusColor($c->status)">{{ $c->status->label() }}</x-filament::badge>
                                        @if ($c->cancel_reason)<div style="font-size:.75rem;opacity:.65">{{ $c->cancel_reason }}</div>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem">
                @foreach (['addContribution', 'receiveContribution', 'cancelContribution'] as $name)
                    @php($action = $this->getAction($name))
                    @if ($action?->isVisible()) {{ $action }} @endif
                @endforeach
            </div>
        </x-filament::section>

        {{-- Ledger --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Money records') }}</x-slot>
            <x-slot name="description">{{ __('Append-only. Corrections appear as reversal entries; nothing is edited or deleted.') }}</x-slot>
            @if ($this->ledger->isEmpty())
                <p style="opacity:.7">{{ __('No money recorded yet.') }}</p>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                        <thead><tr>
                            <th style="{{ $th }}">{{ __('Reference') }}</th><th style="{{ $th }}">{{ __('Date') }}</th><th style="{{ $th }}">{{ __('Type') }}</th>
                            <th style="{{ $th }}">{{ __('Fund') }}</th><th style="{{ $th }}">{{ __('Amount') }}</th><th style="{{ $th }}">{{ __('Paid to / from') }}</th>
                            <th style="{{ $th }}">{{ __('Note') }}</th><th style="{{ $th }}">{{ __('Recorded by') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($this->ledger as $e)
                                <tr style="{{ $e->reversedBy ? 'opacity:.55;text-decoration:line-through' : '' }}">
                                    <td style="{{ $td }};font-family:ui-monospace,monospace">{{ $e->reference }}</td>
                                    <td style="{{ $td }}">{{ $e->recorded_at->timezone($tournament->timezone)->format('d M H:i') }}</td>
                                    <td style="{{ $td }}">{{ $e->type->label() }}{{ $e->prize ? ' · #'.$e->prize->place : '' }}</td>
                                    <td style="{{ $td }}">{{ $e->fund->label() }}</td>
                                    <td style="{{ $td }};font-weight:700;color:{{ $e->amount_minor >= 0 ? '#35D07F' : '#FF626D' }}">{{ $e->amount_minor >= 0 ? '+' : '' }}{{ $e->formattedAmount() }}</td>
                                    <td style="{{ $td }}">{{ $e->payee ?? '—' }}</td>
                                    <td style="{{ $td }}">{{ $e->note }}</td>
                                    <td style="{{ $td }}">{{ $e->recordedBy?->username ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
