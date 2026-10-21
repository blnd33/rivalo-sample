@php
    use App\Enums\CheckInStatus;
    use App\Enums\EligibilityStatus;
    use App\Enums\PaymentStatus;
    use App\Enums\RegistrationStatus;

    $tournament = $this->tournament;
    $entry = $this->entry;

    $tone = fn ($status) => match ($status) {
        RegistrationStatus::Reserved, PaymentStatus::Paid, PaymentStatus::NotRequired, CheckInStatus::CheckedIn, EligibilityStatus::Eligible => 'success',
        RegistrationStatus::Waitlisted, PaymentStatus::Unpaid, EligibilityStatus::Pending => 'warning',
        EligibilityStatus::Ineligible, RegistrationStatus::NoShow, RegistrationStatus::Cancelled => 'danger',
        default => 'gray',
    };
@endphp

<x-filament-panels::page>
    {{-- 1. Find the entrant: scan (phone camera opens this page), type the code, or pick a tournament --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('Find an entrant') }}</x-slot>
        <x-slot name="description">{{ __('Scan the player\'s QR code with your phone camera, type the code, or choose a tournament and search.') }}</x-slot>

        <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
            <form wire:submit="lookupManual" style="display:flex;gap:.5rem;align-items:flex-end">
                <div style="flex:1">
                    <label for="manual-code" class="fi-fo-field-label" style="display:block;font-size:.875rem;font-weight:500;margin-bottom:.25rem">{{ __('Check-in code') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="manual-code" type="text" wire:model="manualCode" autocomplete="off" placeholder="1234-AbCd..." />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">{{ __('Find') }}</x-filament::button>
            </form>

            <div>
                <label for="tournament" class="fi-fo-field-label" style="display:block;font-size:.875rem;font-weight:500;margin-bottom:.25rem">{{ __('Tournament') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="tournament" wire:model.live="tournamentId">
                        <option value="">{{ __('Choose a tournament') }}</option>
                        @foreach ($this->tournaments as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} · {{ $t->starts_at?->format('d M') }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        @if ($lookupError)
            <div role="alert" style="margin-top:1rem;padding:.75rem 1rem;border-radius:.75rem;border:1px solid rgba(255,98,109,.45);background:rgba(255,98,109,.1);color:#FF626D;font-weight:600">
                {{ $lookupError }}
            </div>
        @endif
    </x-filament::section>

    @if ($tournament)
        {{-- 2. Selected entrant --}}
        @if ($entry)
            <x-filament::section>
                <x-slot name="heading">{{ $entry->display_name }}</x-slot>
                <x-slot name="description">{{ $tournament->name }}{{ $entry->team ? ' · '.__('Team') : '' }}</x-slot>

                <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
                    <x-filament::badge :color="$tone($entry->registration_status)">{{ __('Registration') }}: {{ $entry->registration_status->label() }}</x-filament::badge>
                    <x-filament::badge :color="$tone($entry->payment_status)">{{ __('Payment') }}: {{ $entry->payment_status->label() }}</x-filament::badge>
                    <x-filament::badge :color="$tone($entry->check_in_status)">{{ __('Check-in') }}: {{ $entry->check_in_status->label() }}</x-filament::badge>
                    <x-filament::badge :color="$tone($entry->eligibility_status)">{{ __('Eligibility') }}: {{ $entry->eligibility_status->label() }}</x-filament::badge>
                </div>

                @if ($entry->eligibility_reason)
                    <p style="margin-bottom:1rem;color:#FF626D">{{ $entry->eligibility_reason }}</p>
                @endif

                {{-- Identity check: compare these photos and names with the person in front of you --}}
                <h3 style="font-weight:600;margin-bottom:.5rem">{{ __('Roster') }}</h3>
                <ul style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));margin-bottom:1rem">
                    @foreach ($entry->activeMembers as $member)
                        @php($scanned = $member->id === $scannedMemberId)
                        <li style="display:flex;gap:.75rem;align-items:center;padding:.75rem;border-radius:.75rem;border:1px solid {{ $scanned ? '#C7FF3D' : 'rgba(255,255,255,.1)' }}">
                            @if ($member->user?->avatarUrl())
                                <img src="{{ $member->user->avatarUrl() }}" alt="{{ __('Photo of :name', ['name' => $member->username_snapshot]) }}" style="width:56px;height:56px;border-radius:999px;object-fit:cover">
                            @else
                                <div aria-hidden="true" style="width:56px;height:56px;border-radius:999px;display:grid;place-items:center;background:#1F2B45;font-weight:700">{{ mb_strtoupper(mb_substr($member->username_snapshot, 0, 2)) }}</div>
                            @endif
                            <div>
                                <div style="font-weight:600">{{ $member->display_name_snapshot ?? $member->username_snapshot }}</div>
                                <div style="font-size:.875rem;opacity:.7">{{ '@'.$member->username_snapshot }} · {{ $member->role->label() }}</div>
                                @if ($scanned)
                                    <div style="font-size:.75rem;color:#C7FF3D;font-weight:700">{{ __('Scanned code') }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($entry->checked_in_at)
                    <p style="margin-bottom:1rem;opacity:.8">{{ __('Checked in at :time by :staff', ['time' => $entry->checked_in_at->timezone($tournament->timezone)->format('d M H:i'), 'staff' => $entry->checkedInBy?->username ?? '—']) }}</p>
                @endif

                {{-- Only the actions that apply to this entrant right now (rendering does not check visibility). --}}
                <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                    @foreach (['recordPayment', 'checkIn', 'undoCheckIn', 'refund', 'voidPayment', 'markIneligible', 'clearIneligible'] as $actionName)
                        @php($action = $this->getAction($actionName))
                        @if ($action?->isVisible())
                            {{ $action }}
                        @endif
                    @endforeach
                </div>

                @if ($entry->payment_status === PaymentStatus::Unpaid && $tournament->isPaidEntry())
                    <p style="margin-top:.75rem;font-size:.875rem;color:#FFBF47">{{ __('Record the entry fee before checking in.') }}</p>
                @endif

                @if ($entry->payments->isNotEmpty())
                    <h3 style="font-weight:600;margin:1.25rem 0 .5rem">{{ __('Receipts') }}</h3>
                    <div style="overflow-x:auto">
                        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                            <thead>
                                <tr style="text-align:start;opacity:.7">
                                    <th style="text-align:start;padding:.25rem .5rem">{{ __('Receipt') }}</th>
                                    <th style="text-align:start;padding:.25rem .5rem">{{ __('Type') }}</th>
                                    <th style="text-align:start;padding:.25rem .5rem">{{ __('Amount') }}</th>
                                    <th style="text-align:start;padding:.25rem .5rem">{{ __('Recorded by') }}</th>
                                    <th style="text-align:start;padding:.25rem .5rem">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entry->payments->sortBy('recorded_at') as $p)
                                    <tr style="border-top:1px solid rgba(255,255,255,.08);{{ $p->isVoided() ? 'opacity:.55;text-decoration:line-through' : '' }}">
                                        <td style="padding:.4rem .5rem;font-family:ui-monospace,monospace">{{ $p->reference }}</td>
                                        <td style="padding:.4rem .5rem">{{ $p->isRefund() ? __('Refund') : __('Payment') }}</td>
                                        <td style="padding:.4rem .5rem">{{ $p->formattedAmount() }}</td>
                                        <td style="padding:.4rem .5rem">{{ $p->recordedBy?->username }} · {{ $p->recorded_at->timezone($tournament->timezone)->format('d M H:i') }}</td>
                                        <td style="padding:.4rem .5rem">{{ $p->isVoided() ? __('Void').': '.$p->void_reason : __('Valid') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- 3. Entrant list for this tournament --}}
        <x-filament::section>
            <x-slot name="heading">{{ $tournament->name }}</x-slot>
            <x-slot name="description">
                {{ __(':checked of :reserved checked in · :paid paid', ['checked' => $this->counts['checked_in'], 'reserved' => $this->counts['reserved'], 'paid' => $this->counts['paid']]) }}
            </x-slot>

            <div style="margin-bottom:1rem;max-width:24rem">
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Search name or username')" :aria-label="__('Search name or username')" />
                </x-filament::input.wrapper>
            </div>

            <ul style="display:grid;gap:.5rem">
                @forelse ($this->entries as $row)
                    <li>
                        <button type="button" wire:click="openEntry({{ $row->id }})"
                                style="width:100%;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-radius:.75rem;border:1px solid {{ $row->id === $entryId ? '#C7FF3D' : 'rgba(255,255,255,.1)' }};text-align:start;min-height:44px">
                            <span>
                                <span style="font-weight:600">{{ $row->display_name }}</span>
                                <span style="font-size:.8rem;opacity:.7"> · {{ $row->activeMembers->pluck('username_snapshot')->implode(', ') }}</span>
                            </span>
                            <span style="display:flex;gap:.35rem;flex-wrap:wrap">
                                @if ($row->registration_status === RegistrationStatus::Waitlisted)
                                    <x-filament::badge color="warning">{{ __('Waitlist #:position', ['position' => $row->waitlist_position]) }}</x-filament::badge>
                                @endif
                                <x-filament::badge :color="$tone($row->payment_status)">{{ $row->payment_status->label() }}</x-filament::badge>
                                <x-filament::badge :color="$tone($row->check_in_status)">{{ $row->check_in_status->label() }}</x-filament::badge>
                            </span>
                        </button>
                    </li>
                @empty
                    <li style="opacity:.7">{{ __('No entrants match.') }}</li>
                @endforelse
            </ul>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
