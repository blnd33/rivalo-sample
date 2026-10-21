<x-filament-panels::page>
    <form wire:submit="save" style="display:grid;gap:1.5rem">
        {{ $this->form }}

        @if (\App\Support\SellerContext::canWrite())
            <div>
                <x-filament::button type="submit">{{ __('Save') }}</x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
