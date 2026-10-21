<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Enums\DisputeStatus;
use App\Enums\MatchOutcome;
use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use App\Enums\StageType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Concerns\HandlesDomainRules;
use App\Models\MatchResult;
use App\Models\PointsRuleSet;
use App\Models\ResultDispute;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\FixtureService;
use App\Services\PointsService;
use App\Services\ResultService;
use App\Services\StandingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Fixtures and results desk: draw, publish, schedule, enter and review results, corrections,
 * disputes, knockout from groups, and final placements. Every change goes through the services,
 * which re-check assignment (organizer for fixtures, referee for approvals).
 */
class TournamentFixtures extends Page
{
    use HandlesDomainRules;

    protected string $view = 'filament.pages.tournament-fixtures';

    protected static ?string $slug = 'fixtures';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Competition;

    protected static ?int $navigationSort = 3;

    #[Url(as: 'tournament')]
    public ?int $tournamentId = null;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) $u && ($u->can('tournaments.manage_all') || $u->can('tournaments.manage_assigned') || $u->can('results.review_assigned'));
    }

    public static function getNavigationLabel(): string
    {
        return __('Fixtures and results');
    }

    public function getTitle(): string
    {
        return __('Fixtures and results');
    }

    public function updatedTournamentId(): void
    {
        $this->reloadData();
    }

    // ---------------------------------------------------------------- View data

    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()->manageableBy(auth()->user())
            ->whereIn('status', [TournamentStatus::RegistrationOpen->value, TournamentStatus::RegistrationClosed->value, TournamentStatus::InProgress->value, TournamentStatus::Completed->value])
            ->orderByDesc('starts_at')->limit(200)->get(['id', 'name', 'starts_at', 'status']);
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        if (! $this->tournamentId) {
            return null;
        }
        $t = Tournament::with('pointsRuleSet')->find($this->tournamentId);
        $u = auth()->user();

        return $t && ($u->can('manageFixtures', $t) || $u->can('reviewResults', $t)) ? $t : null;
    }

    /** @return Collection<int, Stage> */
    #[Computed]
    public function stages(): Collection
    {
        return $this->tournament
            ? $this->tournament->stages()->with(['groups', 'matches.participants.entry', 'matches.approvedResult', 'matches.group'])->get()
            : collect();
    }

    /** @return Collection<int, MatchResult> */
    #[Computed]
    public function pendingResults(): Collection
    {
        return $this->tournament
            ? MatchResult::with(['match.participants.entry', 'submittedBy'])->where('status', ResultStatus::Submitted->value)
                ->whereHas('match', fn ($q) => $q->where('tournament_id', $this->tournament->id))->get()
            : collect();
    }

    /** @return Collection<int, ResultDispute> */
    #[Computed]
    public function openDisputes(): Collection
    {
        return $this->tournament
            ? ResultDispute::with(['match.participants.entry', 'result', 'raisedBy', 'entry'])->where('status', DisputeStatus::Open->value)
                ->whereHas('match', fn ($q) => $q->where('tournament_id', $this->tournament->id))->get()
            : collect();
    }

    public function standings(Stage $stage, $group = null): array
    {
        return app(StandingsService::class)->table($stage, $group);
    }

    public function matchName(TournamentMatch $m): string
    {
        $a = $m->participants->firstWhere('slot', 1)?->entry?->display_name ?? __('TBD');
        $b = $m->participants->firstWhere('slot', 2)?->entry?->display_name ?? ($m->status === MatchStatus::Bye ? __('Bye') : __('TBD'));
        $where = $m->group ? __('Group :name', ['name' => $m->group->name]).' · ' : '';

        return '#'.$m->id.' · '.$where.$m->displayLabel().' · '.$a.' v '.$b;
    }

    private function reloadData(): void
    {
        unset($this->tournament, $this->stages, $this->pendingResults, $this->openDisputes);
    }

    private function done(string $title): void
    {
        $this->reloadData();
        Notification::make()->success()->title($title)->send();
    }

    private function can(string $ability): bool
    {
        return $this->tournament && auth()->user()->can($ability, $this->tournament);
    }

    private function matches(callable $filter): array
    {
        return $this->stages->flatMap->matches->filter($filter)->mapWithKeys(fn ($m) => [$m->id => $this->matchName($m)])->all();
    }

    private function findMatch(int|string $id): TournamentMatch
    {
        return $this->stages->flatMap->matches->firstWhere('id', (int) $id) ?? throw new \RuntimeException('Unknown match');
    }

    /** Score fields shared by "enter", "correct" and "uphold dispute". */
    private function resultFields(): array
    {
        return [
            Select::make('outcome')->label(__('How the match ended'))->options(MatchOutcome::options())->default('normal')->required()->live()->native(false),
            TextInput::make('slot1')->label(__('Score: first side'))->integer()->minValue(0)->maxValue(999),
            TextInput::make('slot2')->label(__('Score: second side'))->integer()->minValue(0)->maxValue(999),
            TextInput::make('sets')->label(__('Sets (optional)'))->placeholder('6-4, 3-6, 10-8')
                ->helperText(__('For set-based sports. Sets decide the winner; leave the scores empty.')),
            Radio::make('winner_slot')->label(__('Winner'))->options(['1' => __('First side'), '2' => __('Second side')])
                ->helperText(__('Needed for walkovers, forfeits, no-shows, disqualifications and knockout tiebreaks.')),
        ];
    }

    /** "6-4, 3-6" → [[6,4],[3,6]]. */
    private static function resultData(array $data): array
    {
        if (filled($data['sets'] ?? null)) {
            $sets = [];
            foreach (preg_split('/[,;]\s*/', trim($data['sets'])) as $set) {
                if (! preg_match('/^\s*(\d{1,3})\s*[-:–]\s*(\d{1,3})\s*$/u', $set, $m)) {
                    throw DomainRuleException::make('invalid_score', 'Write sets like 6-4, 3-6, 10-8.', 422);
                }
                $sets[] = [(int) $m[1], (int) $m[2]];
            }
            $data['sets'] = $sets;
        } else {
            unset($data['sets']);
        }

        return $data;
    }

    // ---------------------------------------------------------------- Points rules

    public function assignPointsAction(): Action
    {
        return Action::make('assignPoints')
            ->label(__('Choose points table'))
            ->icon(Heroicon::OutlinedListBullet)
            ->color('gray')
            ->visible(fn () => $this->tournament && ! $this->tournament->points_rules_locked_at
                && (auth()->user()->can('points.manage') || auth()->user()->can('tournaments.manage_all')))
            ->schema([
                Select::make('set_id')->label(__('Published points table'))
                    ->options(fn () => PointsRuleSet::where('discipline_id', $this->tournament->discipline_id)->where('status', 'published')
                        ->where(fn ($q) => $q->whereNull('season_id')->orWhere('season_id', $this->tournament->season_id))
                        ->get()->mapWithKeys(fn ($s) => [$s->id => $s->displayName()])->all())
                    ->placeholder(__('None: no ranking points'))
                    ->helperText(__('Only published tables appear. Points are locked after the first award.')),
            ])
            ->action(function (array $data) {
                $set = filled($data['set_id'] ?? null) ? PointsRuleSet::find($data['set_id']) : null;
                self::attempt(fn () => app(PointsService::class)->assignToTournament($this->tournament, $set, auth()->user()));
                $this->done(__('Points table saved'));
            });
    }

    // ---------------------------------------------------------------- Draw

    public function generateAction(): Action
    {
        return Action::make('generate')
            ->label(fn () => $this->stages->isEmpty() ? __('Make the draw') : __('Redo the draw'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->visible(fn () => $this->can('manageFixtures') && $this->tournament->status !== TournamentStatus::Completed)
            ->modalDescription(__('Uses checked-in, eligible entrants. Seeded entrants are placed first; the rest are drawn at random. The draw number lets anyone reproduce the draw. A draw can be redone until the first result is entered.'))
            ->schema(fn () => array_filter([
                TextInput::make('draw_seed')->label(__('Draw number (optional)'))->integer()->minValue(1)
                    ->helperText(__('Leave empty for a new random draw.')),
                $this->tournament?->format === TournamentFormat::GroupsKnockout
                    ? TextInput::make('group_size')->label(__('Entrants per group'))->integer()->minValue(2)->maxValue(16)->default(4)->required() : null,
                $this->tournament?->format === TournamentFormat::GroupsKnockout
                    ? TextInput::make('advance_per_group')->label(__('Advance from each group'))->integer()->minValue(1)->maxValue(8)->default(2)->required() : null,
            ]))
            ->action(function (array $data) {
                $options = array_filter($data, fn ($v) => filled($v));
                self::attempt(fn () => app(FixtureService::class)->generate($this->tournament, auth()->user(), $options));
                $this->done(__('Draw made. Check it, then publish the fixtures.'));
            });
    }

    public function publishFixturesAction(): Action
    {
        return Action::make('publishFixtures')
            ->label(__('Publish fixtures'))
            ->icon(Heroicon::OutlinedEye)
            ->color('success')
            ->visible(fn () => $this->can('manageFixtures') && $this->stages->contains(fn ($s) => ! $s->isPublic()))
            ->requiresConfirmation()
            ->modalDescription(__('Players will see the fixtures in the app.'))
            ->action(function () {
                self::attempt(fn () => app(FixtureService::class)->publish($this->tournament, auth()->user()));
                $this->done(__('Fixtures published'));
            });
    }

    public function generateKnockoutAction(): Action
    {
        return Action::make('generateKnockout')
            ->label(__('Create knockout from groups'))
            ->icon(Heroicon::OutlinedTrophy)
            ->visible(fn () => $this->can('manageFixtures')
                && $this->stages->contains(fn ($s) => $s->type === StageType::Groups)
                && ! $this->stages->contains(fn ($s) => $s->type === StageType::Knockout))
            ->requiresConfirmation()
            ->modalDescription(__('Group winners are seeded first, so teams from the same group are kept apart in the first knockout round.'))
            ->action(function () {
                self::attempt(fn () => app(FixtureService::class)->generateKnockout($this->tournament, auth()->user()));
                $this->done(__('Knockout created'));
            });
    }

    public function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label(__('Schedule a match'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->visible(fn () => $this->can('manageFixtures') && $this->stages->isNotEmpty())
            ->schema([
                Select::make('match_id')->label(__('Match'))->options(fn () => $this->matches(fn ($m) => ! $m->status->isFinished()))->searchable()->required(),
                DateTimePicker::make('scheduled_at')->label(__('Time'))->seconds(false)->timezone(fn () => $this->tournament->timezone),
                TextInput::make('location')->label(__('Court / table / lobby'))->maxLength(120),
            ])
            ->action(function (array $data) {
                $at = filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at']) : null;
                self::attempt(fn () => app(FixtureService::class)->schedule($this->findMatch($data['match_id']), auth()->user(), $at, $data['location'] ?? null));
                $this->done(__('Match scheduled'));
            });
    }

    // ---------------------------------------------------------------- Results

    public function enterResultAction(): Action
    {
        return Action::make('enterResult')
            ->label(__('Enter a result'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->visible(fn () => ($this->can('manageFixtures') || $this->can('reviewResults')) && $this->stages->flatMap->matches->contains(fn ($m) => $m->status === MatchStatus::Ready))
            ->schema(fn () => [
                Select::make('match_id')->label(__('Match'))->options(fn () => $this->matches(fn ($m) => $m->status === MatchStatus::Ready))->searchable()->required(),
                ...$this->resultFields(),
                Toggle::make('approve')->label(__('Approve now (referee)'))->default(true)->visible(fn () => $this->can('reviewResults')),
                Textarea::make('note')->label(__('Note (optional)'))->rows(2),
            ])
            ->action(function (array $data) {
                $result = self::attempt(fn () => app(ResultService::class)->submit($this->findMatch($data['match_id']), auth()->user(), self::resultData($data)));
                $this->done($result->status === ResultStatus::Approved ? __('Result approved') : __('Result submitted for review'));
            });
    }

    public function reviewResultAction(): Action
    {
        return Action::make('reviewResult')
            ->label(__('Review submitted results'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->badge(fn () => $this->pendingResults->count() ?: null)
            ->visible(fn () => $this->can('reviewResults') && $this->pendingResults->isNotEmpty())
            ->schema([
                Select::make('result_id')->label(__('Submitted result'))
                    ->options(fn () => $this->pendingResults->mapWithKeys(fn (MatchResult $r) => [
                        $r->id => $this->matchName($r->match).' · '.$r->scoreLine().' · '.__('by :user', ['user' => $r->submittedBy?->username ?? '—']),
                    ])->all())
                    ->required(),
                Radio::make('decision')->label(__('Decision'))->options(['approve' => __('Approve'), 'reject' => __('Reject')])->default('approve')->required()->live(),
                Textarea::make('note')->label(__('Note'))->rows(2)->required(fn (Get $get) => $get('decision') === 'reject'),
            ])
            ->action(function (array $data) {
                $result = $this->pendingResults->firstWhere('id', (int) $data['result_id']);
                $service = app(ResultService::class);
                if ($data['decision'] === 'approve') {
                    self::attempt(fn () => $service->approve($result, auth()->user(), $data['note'] ?? null));
                    $this->done(__('Result approved'));
                } else {
                    self::attempt(fn () => $service->reject($result, auth()->user(), $data['note'] ?? ''));
                    $this->done(__('Result rejected'));
                }
            });
    }

    public function correctResultAction(): Action
    {
        return Action::make('correctResult')
            ->label(__('Correct a result'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn () => $this->can('reviewResults') && ! $this->tournament->placements_finalized_at
                && $this->stages->flatMap->matches->contains(fn ($m) => $m->approved_result_id))
            ->modalDescription(__('The old result is kept as superseded, its points are reversed, and the bracket is updated. Not possible once a later match that depends on it has been played.'))
            ->schema(fn () => [
                Select::make('match_id')->label(__('Match'))->options(fn () => $this->matches(fn ($m) => $m->approved_result_id))->searchable()->required(),
                ...$this->resultFields(),
                Textarea::make('reason')->label(__('Reason (audited)'))->required()->rows(2),
            ])
            ->action(function (array $data) {
                self::attempt(fn () => app(ResultService::class)->correct($this->findMatch($data['match_id']), auth()->user(), self::resultData($data), $data['reason']));
                $this->done(__('Result corrected'));
            });
    }

    public function resolveDisputeAction(): Action
    {
        return Action::make('resolveDispute')
            ->label(__('Resolve a dispute'))
            ->icon(Heroicon::OutlinedScale)
            ->color('danger')
            ->badge(fn () => $this->openDisputes->count() ?: null)
            ->visible(fn () => $this->can('reviewResults') && $this->openDisputes->isNotEmpty())
            ->schema(fn () => [
                Select::make('dispute_id')->label(__('Dispute'))
                    ->options(fn () => $this->openDisputes->mapWithKeys(fn (ResultDispute $d) => [
                        $d->id => $this->matchName($d->match).' · '.$d->entry?->display_name.': '.str($d->reason)->limit(60),
                    ])->all())
                    ->required(),
                Radio::make('decision')->label(__('Decision'))->options(['rejected' => __('Reject: the result stands'), 'upheld' => __('Uphold: correct the result')])->required()->live(),
                Textarea::make('note')->label(__('Explanation (shared with the players)'))->required()->rows(2),
                ...collect($this->resultFields())->map(fn ($f) => $f->visible(fn (Get $get) => $get('decision') === 'upheld'))->all(),
            ])
            ->action(function (array $data) {
                $dispute = $this->openDisputes->firstWhere('id', (int) $data['dispute_id']);
                $upheld = $data['decision'] === 'upheld';
                self::attempt(fn () => app(ResultService::class)->resolveDispute($dispute, auth()->user(), $upheld, $data['note'], $upheld ? self::resultData($data) : null));
                $this->done($upheld ? __('Dispute upheld and result corrected') : __('Dispute rejected'));
            });
    }

    public function finalizeAction(): Action
    {
        return Action::make('finalize')
            ->label(__('Confirm final placements'))
            ->icon(Heroicon::OutlinedFlag)
            ->color('success')
            ->visible(fn () => $this->can('manageFixtures') && $this->tournament->placements_finalized_at === null && $this->stages->isNotEmpty())
            ->requiresConfirmation()
            ->modalDescription(__('Every match must have an approved result. Placements and placement/participation points are recorded, and the tournament is marked completed.'))
            ->action(function () {
                self::attempt(fn () => app(PointsService::class)->finalize($this->tournament, auth()->user()));
                $this->done(__('Placements confirmed. Tournament completed.'));
            });
    }
}
