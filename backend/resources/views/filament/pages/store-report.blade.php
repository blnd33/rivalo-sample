@php
    $th = 'text-align:start;padding:.35rem .5rem;font-weight:600;opacity:.7;white-space:nowrap';
    $td = 'padding:.45rem .5rem;border-top:1px solid rgba(255,255,255,.08);white-space:nowrap';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end;justify-content:space-between">
            <p style="font-size:.875rem;opacity:.75;max-width:44rem">{{ __('Orders placed in the period. "Received" is cash staff recorded at handover; "Refunded" is cash recorded as handed back. Sample orders are shown separately.') }}</p>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                <label style="font-size:.8rem">{{ __('From') }}
                    <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
                </label>
                <label style="font-size:.8rem">{{ __('Until') }}
                    <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="until" /></x-filament::input.wrapper>
                </label>
            </div>
        </div>
    </x-filament::section>

    @forelse ($this->totals as $t)
        <x-filament::section>
            <x-slot name="heading">{{ $t['sample'] ? __('Sample orders (not real) in :currency', ['currency' => $t['currency']]) : __('Totals in :currency', ['currency' => $t['currency']]) }}</x-slot>
            <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
                @foreach ([
                    [__('Orders'), number_format($t['orders'])],
                    [__('Completed'), number_format($t['completed'])],
                    [__('Cancelled'), number_format($t['cancelled'])],
                    [__('Still open (to collect)'), $this->money($t['open_value'], $t['currency'])],
                    [__('Cash received'), $this->money($t['received'], $t['currency'])],
                    [__('Refunded'), $this->money($t['refunded'], $t['currency'])],
                    [__('Net cash'), $this->money($t['net'], $t['currency'])],
                ] as [$label, $value])
                    <div style="padding:.75rem;border-radius:.75rem;background:rgba(255,255,255,.04)">
                        <div style="font-size:.75rem;opacity:.7">{{ $label }}</div>
                        <div style="font-size:1.1rem;font-weight:700">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @empty
        <x-filament::section><p style="opacity:.75">{{ __('No orders in this period.') }}</p></x-filament::section>
    @endforelse

    <x-filament::section>
        <x-slot name="heading">{{ __('Items sold') }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">{{ __('Product') }}</th><th style="{{ $th }}">{{ __('Size or option') }}</th><th style="{{ $th }}">{{ __('SKU') }}</th>
                    <th style="{{ $th }}">{{ __('Units') }}</th><th style="{{ $th }}">{{ __('Returned') }}</th><th style="{{ $th }}">{{ __('Value') }}</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->products as $p)
                        <tr>
                            <td style="{{ $td }}">{{ $p['product_name'] }}</td><td style="{{ $td }}">{{ $p['option_label'] }}</td><td style="{{ $td }}">{{ $p['sku'] }}</td>
                            <td style="{{ $td }}">{{ $p['units'] }}</td><td style="{{ $td }}">{{ $p['returned'] }}</td><td style="{{ $td }}">{{ $this->money((int) $p['value'], $p['currency']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="{{ $td }};opacity:.7">{{ __('Nothing sold in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Low stock (3 or fewer)') }}</x-slot>
        @if (empty($this->lowStock))
            <p style="opacity:.75">{{ __('Nothing is running low.') }}</p>
        @else
            <ul style="display:grid;gap:.35rem">
                @foreach ($this->lowStock as $v)
                    <li>{{ $v['name'] }} · {{ $v['option'] }} <span style="opacity:.6">({{ $v['sku'] }})</span> — <strong style="color:{{ $v['stock'] === 0 ? '#FF626D' : '#FFBF47' }}">{{ $v['stock'] === 0 ? __('Sold out') : __(':count left', ['count' => $v['stock']]) }}</strong></li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
