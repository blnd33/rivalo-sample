@php
    $d = $this->data();
    $card = 'padding:.85rem;border-radius:.85rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);text-decoration:none;color:inherit;display:block';
@endphp

<x-filament-widgets::widget>
    @if (! $d['seller'])
        <x-filament::section><p>{{ __('No shop is linked to this account.') }}</p></x-filament::section>
    @else
        @php($seller = $d['seller'])
        @if (! $seller->isActive())
            <x-filament::section>
                <div style="color:#FFBF47;font-weight:600">{{ __('Your shop is :status. Your products are not shown in the store.', ['status' => $seller->status->label()]) }}</div>
                @if ($seller->status_reason)
                    <p style="opacity:.8;margin-top:.35rem">{{ $seller->status_reason }}</p>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">{{ $seller->name }}</x-slot>
            <x-slot name="description">{{ __('RIVALO commission: :rate of the item price, as in your agreement.', ['rate' => $d['commission']]) }}</x-slot>
            <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                <a href="{{ $d['statementUrl'] }}" style="{{ $card }}">
                    <div style="font-size:.75rem;opacity:.7">{{ $d['owedNegative'] ? __('You owe RIVALO') : __('RIVALO owes you') }}</div>
                    <div style="font-size:1.35rem;font-weight:800;color:{{ $d['owedNegative'] ? '#FF626D' : '#C7FF3D' }}">{{ $d['owed'] }}</div>
                    <div style="font-size:.75rem;opacity:.7">{{ __('Paid to you so far: :amount', ['amount' => $d['paid']]) }}</div>
                </a>
                <a href="{{ $d['statementUrl'] }}" style="{{ $card }}">
                    <div style="font-size:.75rem;opacity:.7">{{ __('Earned this month') }}</div>
                    <div style="font-size:1.35rem;font-weight:800">{{ $d['month'] }}</div>
                    <div style="font-size:.75rem;opacity:.7">{{ __('After commission, from completed orders') }}</div>
                </a>
                <a href="{{ $d['salesUrl'] }}" style="{{ $card }}">
                    <div style="font-size:.75rem;opacity:.7">{{ __('Items in orders in progress') }}</div>
                    <div style="font-size:1.35rem;font-weight:800">{{ number_format($d['inOpenOrders']) }}</div>
                    <div style="font-size:.75rem;opacity:.7">{{ __('Counted when the customer has them and has paid') }}</div>
                </a>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('Your products') }}</x-slot>
            <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                @foreach ([
                    [__('On sale'), $d['onSale'], '#35D07F'],
                    [__('Waiting for review'), $d['waiting'], '#4DA3FF'],
                    [__('Changes requested'), $d['changes'], '#FFBF47'],
                    [__('Sizes low or sold out'), $d['low'], '#FF626D'],
                ] as [$label, $count, $color])
                    <a href="{{ $d['listingsUrl'] }}" style="{{ $card }}">
                        <div style="font-size:.75rem;opacity:.7">{{ $label }}</div>
                        <div style="font-size:1.35rem;font-weight:800;color:{{ $count > 0 ? $color : 'inherit' }}">{{ $count }}</div>
                    </a>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">{{ __('How selling on RIVALO works') }}</x-slot>
            <ol style="display:grid;gap:.4rem;padding-inline-start:1.2rem;list-style:decimal;opacity:.85">
                <li>{{ __('Add a product with photos and sizes, then submit it. RIVALO staff review every product before it goes on sale.') }}</li>
                <li>{{ __('Bring the stock to RIVALO. Staff count it in, and only items RIVALO holds can be bought.') }}</li>
                <li>{{ __('Customers order in the RIVALO app and pay in cash at pickup or delivery. You never need their contact details.') }}</li>
                <li>{{ __('When an order is completed, the item price minus the commission is added to your statement. Returns are taken off again.') }}</li>
                <li>{{ __('RIVALO pays you in cash or by bank transfer and records each payout on your statement. Nothing is paid automatically.') }}</li>
            </ol>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
