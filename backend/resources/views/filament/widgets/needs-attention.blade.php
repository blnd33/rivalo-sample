@php
    $items = $this->items();
    $toneColor = ['danger' => '#FF626D', 'warning' => '#FFBF47', 'info' => '#4DA3FF'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Needs your attention') }}</x-slot>
        <x-slot name="description">{{ __('Only what you can act on. Counts come straight from the database.') }}</x-slot>

        @if (empty($items))
            <div style="display:flex;align-items:center;gap:.6rem;opacity:.8">
                <x-filament::icon icon="heroicon-o-check-circle" style="width:1.25rem;height:1.25rem;color:#35D07F" />
                <span>{{ __('Nothing needs your attention right now.') }}</span>
            </div>
        @else
            <ul style="display:grid;gap:.5rem;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));list-style:none;margin:0;padding:0">
                @foreach ($items as $item)
                    @php $color = $toneColor[$item['tone']] ?? '#C7FF3D'; @endphp
                    <li>
                        <{{ $item['url'] ? 'a' : 'div' }} @if ($item['url']) href="{{ $item['url'] }}" @endif
                            style="display:flex;gap:.85rem;align-items:center;padding:.75rem .9rem;border-radius:.85rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);text-decoration:none;color:inherit">
                            <span style="min-width:2.25rem;height:2.25rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;background:{{ $color }}22;color:{{ $color }}">{{ $item['count'] }}</span>
                            <span style="display:flex;flex-direction:column;min-width:0">
                                <span style="font-weight:600">{{ $item['label'] }}</span>
                                @if ($item['detail'])
                                    <span style="font-size:.8rem;opacity:.7;overflow:hidden;text-overflow:ellipsis">{{ $item['detail'] }}</span>
                                @endif
                            </span>
                        </{{ $item['url'] ? 'a' : 'div' }}>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
