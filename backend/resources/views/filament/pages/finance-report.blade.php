@php
    use App\Filament\Pages\TournamentFinance;
    $th = 'text-align:start;padding:.35rem .5rem;font-weight:600;opacity:.7;white-space:nowrap';
    $td = 'padding:.45rem .5rem;border-top:1px solid rgba(255,255,255,.08);white-space:nowrap';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
            <p style="font-size:.875rem;opacity:.75;max-width:48rem">{{ __('Figures come straight from each tournament\'s money records. Projections follow the finance rules; drafts are not approved and do not allow formula-based payouts.') }}</p>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="scope" :aria-label="__('Show')">
                    <option value="with_money">{{ __('Tournaments with money activity') }}</option>
                    <option value="all">{{ __('All published tournaments') }}</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    @foreach ($this->totals as $currency => $t)
        <x-filament::section>
            <x-slot name="heading">{{ __('Totals in :currency', ['currency' => $currency]) }}</x-slot>
            <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                @foreach ([
                    __('Net entry revenue') => $t['net_entry'],
                    __('Sponsor cash received') => $t['sponsor_cash'],
                    __('Expenses paid') => $t['expenses'],
                    __('Prizes paid') => $t['prizes_paid'],
                    __('Organizer paid') => $t['organizer_paid'],
                    __('Cash on hand') => $t['cash_on_hand'],
                    __('Sponsor pledges not yet received') => $t['pledged_outstanding'],
                ] as $label => $value)
                    <div style="padding:.85rem 1rem;border-radius:.75rem;border:1px solid rgba(255,255,255,.1)">
                        <div style="font-size:.8rem;opacity:.7">{{ $label }}</div>
                        <div style="font-size:1.1rem;font-weight:700">{{ $this->money($value, $currency) }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    <x-filament::section>
        <x-slot name="heading">{{ __('By tournament') }}</x-slot>
        @if ($this->rows->isEmpty())
            <p style="opacity:.7">{{ __('No tournaments with money activity yet.') }}</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                    <thead><tr>
                        <th style="{{ $th }}">{{ __('Tournament') }}</th><th style="{{ $th }}">{{ __('Rules') }}</th>
                        <th style="{{ $th }}">{{ __('Net entry revenue') }}</th><th style="{{ $th }}">{{ __('Sponsor cash') }}</th>
                        <th style="{{ $th }}">{{ __('Expenses') }}</th><th style="{{ $th }}">{{ __('Prizes paid') }}</th>
                        <th style="{{ $th }}">{{ __('Organizer paid') }}</th><th style="{{ $th }}">{{ __('Cash on hand') }}</th>
                        <th style="{{ $th }}">{{ __('Pledged, not received') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($this->rows as $row)
                            @php($t = $row['tournament'])
                            @php($s = $row['summary'])
                            <tr>
                                <td style="{{ $td }}">
                                    <a href="{{ TournamentFinance::getUrl(['tournament' => $t->id]) }}" style="font-weight:600;text-decoration:underline">{{ $t->name }}</a>
                                    @if ($t->is_sample_data) <x-filament::badge color="warning" size="sm">{{ __('Sample data') }}</x-filament::badge> @endif
                                </td>
                                <td style="{{ $td }}"><x-filament::badge :color="$s['projection']['approved'] ? 'success' : 'warning'">{{ $s['projection']['approved'] ? __('Approved') : __('Draft') }}</x-filament::badge></td>
                                <td style="{{ $td }}">{{ $this->money($s['net_entry'], $s['currency']) }}</td>
                                <td style="{{ $td }}">{{ $this->money($s['sponsor_cash'], $s['currency']) }}</td>
                                <td style="{{ $td }}">{{ $this->money($s['expenses'], $s['currency']) }}</td>
                                <td style="{{ $td }}">{{ $this->money($s['prizes_paid'], $s['currency']) }}</td>
                                <td style="{{ $td }}">{{ $this->money($s['organizer_paid'], $s['currency']) }}</td>
                                <td style="{{ $td }};font-weight:700">{{ $this->money($s['cash_on_hand'], $s['currency']) }}</td>
                                <td style="{{ $td }}">{{ $this->money($s['pledged_outstanding'], $s['currency']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
