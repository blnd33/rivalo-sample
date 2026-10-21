@php
    use App\Enums\MatchStatus;
    use App\Enums\StageType;

    $tournament = $this->tournament;
    $statusColor = fn (MatchStatus $s) => match ($s) {
        MatchStatus::Completed => 'success',
        MatchStatus::Ready => 'info',
        MatchStatus::Pending => 'gray',
        MatchStatus::Bye => 'gray',
        MatchStatus::Cancelled => 'danger',
    };
    $th = 'text-align:start;padding:.35rem .5rem;font-weight:600;opacity:.7;white-space:nowrap';
    $td = 'padding:.4rem .5rem;border-top:1px solid rgba(255,255,255,.08)';
    $actions = fn (array $names) => collect($names)->map(fn ($n) => $this->getAction($n))->filter(fn ($a) => $a?->isVisible());
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <label for="tournament" style="display:block;font-size:.875rem;font-weight:500;margin-bottom:.25rem">{{ __('Tournament') }}</label>
        <x-filament::input.wrapper>
            <x-filament::input.select id="tournament" wire:model.live="tournamentId">
                <option value="">{{ __('Choose a tournament') }}</option>
                @foreach ($this->tournaments as $t)
                    <option value="{{ $t->id }}">{{ $t->name }} · {{ $t->starts_at?->format('d M Y') }} · {{ $t->status->label() }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </x-filament::section>

    @if ($tournament)
        <x-filament::section>
            <x-slot name="heading">{{ $tournament->name }}</x-slot>
            <x-slot name="description">{{ $tournament->format->label() }} · {{ $tournament->status->label() }}</x-slot>

            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
                @if (! $tournament->ranking_eligible)
                    <x-filament::badge color="gray">{{ __('Does not count toward rankings') }}</x-filament::badge>
                @elseif ($tournament->pointsRuleSet && $tournament->pointsRuleSet->status->value !== 'draft')
                    <x-filament::badge color="success" icon="heroicon-o-list-bullet">{{ __('Points: :table', ['table' => $tournament->pointsRuleSet->displayName()]) }}</x-filament::badge>
                @else
                    <x-filament::badge color="warning">{{ __('No published points table: results award no ranking points') }}</x-filament::badge>
                @endif
                @if ($tournament->points_rules_locked_at)
                    <x-filament::badge color="gray" icon="heroicon-o-lock-closed">{{ __('Points locked') }}</x-filament::badge>
                @endif
                @if ($tournament->placements_finalized_at)
                    <x-filament::badge color="success" icon="heroicon-o-flag">{{ __('Placements final') }}</x-filament::badge>
                @endif
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                @foreach ($actions(['generate', 'publishFixtures', 'generateKnockout', 'enterResult', 'reviewResult', 'resolveDispute', 'correctResult', 'schedule', 'assignPoints', 'finalize']) as $action)
                    {{ $action }}
                @endforeach
            </div>

            @if ($this->stages->isEmpty())
                <p style="margin-top:1rem;opacity:.7">{{ __('No draw yet. Close registration and check entrants in, then make the draw.') }}</p>
            @endif
        </x-filament::section>

        @if ($this->openDisputes->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">{{ __('Open disputes') }}</x-slot>
                <ul style="display:grid;gap:.5rem">
                    @foreach ($this->openDisputes as $d)
                        <li style="padding:.6rem .8rem;border-radius:.6rem;border:1px solid rgba(255,98,109,.4)">
                            <div style="font-weight:600">{{ $this->matchName($d->match) }}</div>
                            <div style="font-size:.85rem;opacity:.8">{{ $d->entry?->display_name }} ({{ '@'.$d->raisedBy?->username }}): {{ $d->reason }}</div>
                            <div style="font-size:.8rem;opacity:.6">{{ __('Approved result: :score', ['score' => $d->result->scoreLine()]) }} · {{ $d->created_at->diffForHumans() }}</div>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @foreach ($this->stages as $stage)
            <x-filament::section>
                <x-slot name="heading">{{ $stage->displayName() }}</x-slot>
                <x-slot name="description">
                    {{ $stage->type->label() }} · {{ $stage->status->label() }}
                    @if ($stage->setting('draw_seed')) · {{ __('Draw number :seed', ['seed' => $stage->setting('draw_seed')]) }} @endif
                </x-slot>

                @php($tables = $stage->type === StageType::RoundRobin ? [[null, __('Table')]] : ($stage->type === StageType::Groups ? $stage->groups->map(fn ($g) => [$g, __('Group :name', ['name' => $g->name])])->all() : []))
                @if ($tables)
                    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-bottom:1rem">
                        @foreach ($tables as [$group, $title])
                            <div style="overflow-x:auto">
                                <h3 style="font-weight:600;margin-bottom:.25rem">{{ $title }}</h3>
                                <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                                    <thead><tr>
                                        <th style="{{ $th }}">#</th><th style="{{ $th }}">{{ __('Entrant') }}</th>
                                        <th style="{{ $th }}">{{ __('P') }}</th><th style="{{ $th }}">{{ __('W') }}</th><th style="{{ $th }}">{{ __('D') }}</th><th style="{{ $th }}">{{ __('L') }}</th>
                                        <th style="{{ $th }}">+/-</th><th style="{{ $th }}">{{ __('Pts') }}</th>
                                    </tr></thead>
                                    <tbody>
                                        @foreach ($this->standings($stage, $group) as $row)
                                            <tr @if ($stage->type === StageType::Groups && $row['rank'] <= $stage->setting('advance_per_group', 2)) style="background:rgba(199,255,61,.06)" @endif>
                                                <td style="{{ $td }}">{{ $row['rank'] }}</td>
                                                <td style="{{ $td }};font-weight:600">{{ $row['name'] }}</td>
                                                <td style="{{ $td }}">{{ $row['played'] }}</td><td style="{{ $td }}">{{ $row['won'] }}</td>
                                                <td style="{{ $td }}">{{ $row['drawn'] }}</td><td style="{{ $td }}">{{ $row['lost'] }}</td>
                                                <td style="{{ $td }}">{{ $row['diff'] > 0 ? '+' : '' }}{{ $row['diff'] }}</td>
                                                <td style="{{ $td }};font-weight:700">{{ $row['points'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                        <thead><tr>
                            <th style="{{ $th }}">#</th><th style="{{ $th }}">{{ __('Round') }}</th><th style="{{ $th }}">{{ __('First side') }}</th>
                            <th style="{{ $th }}">{{ __('Score') }}</th><th style="{{ $th }}">{{ __('Second side') }}</th>
                            <th style="{{ $th }}">{{ __('Status') }}</th><th style="{{ $th }}">{{ __('Time') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($stage->matches->sortBy(fn ($m) => [$m->stage_group_id, $m->round, $m->position]) as $m)
                                @php($a = $m->participants->firstWhere('slot', 1))
                                @php($b = $m->participants->firstWhere('slot', 2))
                                <tr>
                                    <td style="{{ $td }};font-family:ui-monospace,monospace;opacity:.6">{{ $m->id }}</td>
                                    <td style="{{ $td }};white-space:nowrap">{{ $m->group ? __('Group :name', ['name' => $m->group->name]).' · ' : '' }}{{ $m->displayLabel() }}</td>
                                    <td style="{{ $td }};{{ $a && $a->tournament_entry_id && $a->tournament_entry_id === $m->winner_entry_id ? 'font-weight:700;color:#C7FF3D' : '' }}">{{ $a?->entry?->display_name ?? __('TBD') }}</td>
                                    <td style="{{ $td }};font-weight:700;white-space:nowrap">{{ $m->approvedResult?->scoreLine() ?? '—' }}</td>
                                    <td style="{{ $td }};{{ $b && $b->tournament_entry_id && $b->tournament_entry_id === $m->winner_entry_id ? 'font-weight:700;color:#C7FF3D' : '' }}">{{ $b?->entry?->display_name ?? ($m->status === MatchStatus::Bye ? __('Bye') : __('TBD')) }}</td>
                                    <td style="{{ $td }}"><x-filament::badge :color="$statusColor($m->status)">{{ $m->status->label() }}</x-filament::badge></td>
                                    <td style="{{ $td }};white-space:nowrap">{{ $m->scheduled_at?->timezone($tournament->timezone)->format('d M H:i') ?? '—' }}{{ $m->location ? ' · '.$m->location : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
