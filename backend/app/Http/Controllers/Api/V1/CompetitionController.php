<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DisputeStatus;
use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use App\Enums\SeasonStatus;
use App\Enums\StageStatus;
use App\Http\Controllers\Controller;
use App\Models\MatchResult;
use App\Models\PointsLedgerEntry;
use App\Models\PointsRuleSet;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentEntryMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\PointsService;
use App\Services\ResultService;
use App\Services\StandingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fixtures, results, disputes and leaderboards for the app. Only published fixtures and approved
 * results are public. A submitted (unreviewed) result is visible only to the two sides and staff.
 */
class CompetitionController extends Controller
{
    public function __construct(
        private readonly ResultService $results,
        private readonly PointsService $points,
        private readonly StandingsService $standings,
    ) {}

    public function fixtures(Request $request, Tournament $tournament): JsonResponse
    {
        abort_unless($tournament->status->isPublic(), 404);
        $user = $request->user();
        $myEntries = $this->myEntryIds($user, $tournament);

        $stages = $tournament->stages()
            ->whereIn('status', [StageStatus::Published->value, StageStatus::Completed->value])
            ->with(['groups', 'matches.participants.entry', 'matches.approvedResult', 'matches.group'])
            ->get();

        $set = $tournament->pointsRuleSet;

        return response()->json([
            'data' => [
                'stages' => $stages->map(fn (Stage $s) => $this->stage($s, $user, $myEntries))->values(),
                'placements_final' => (bool) $tournament->placements_finalized_at,
                'placements' => $tournament->placements_finalized_at
                    ? $tournament->entries()->whereNotNull('final_place')->orderBy('final_place')->get(['id', 'display_name', 'final_place'])
                        ->map(fn ($e) => ['place' => $e->final_place, 'entry_id' => $e->id, 'name' => $e->display_name, 'is_mine' => in_array($e->id, $myEntries, true)])
                    : [],
                'points' => [
                    'ranking_eligible' => (bool) $tournament->ranking_eligible,
                    // Only a published table is mentioned; drafts are staff-only.
                    'rule_set' => $set && $set->status->value !== 'draft' ? ['name' => $set->name, 'version' => $set->version] : null,
                ],
            ],
        ]);
    }

    public function match(Request $request, TournamentMatch $match): JsonResponse
    {
        $match->load(['participants.entry', 'approvedResult', 'stage', 'tournament', 'group']);
        abort_unless($match->tournament->status->isPublic() && $match->stage->isPublic(), 404);
        $user = $request->user();

        return response()->json(['data' => $this->matchJson($match, $user, $this->myEntryIds($user, $match->tournament), detailed: true)]);
    }

    public function submitResult(Request $request, TournamentMatch $match): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['normal', 'walkover', 'forfeit', 'no_show'])],
            'slot1' => ['nullable', 'integer', 'min:0', 'max:999'],
            'slot2' => ['nullable', 'integer', 'min:0', 'max:999'],
            'sets' => ['nullable', 'array', 'max:9'],
            'sets.*' => ['array', 'size:2'],
            'sets.*.*' => ['integer', 'min:0', 'max:999'],
            'winner_slot' => ['nullable', Rule::in([1, 2])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->results->submit($match, $request->user(), $data);

        return $this->match($request, $match->fresh());
    }

    public function dispute(Request $request, MatchResult $result): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $dispute = $this->results->dispute($result, $request->user(), $data['reason']);

        return response()->json(['data' => ['id' => $dispute->id, 'status' => $dispute->status->value]], 201);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'discipline_id' => ['required', 'integer', 'exists:disciplines,id'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
        ]);
        $user = $request->user();
        $rows = $this->points->leaderboard((int) $data['discipline_id'], isset($data['season_id']) ? (int) $data['season_id'] : null);

        return response()->json([
            'data' => array_map(fn ($r) => [
                'rank' => $r['rank'],
                'user' => ['username' => $r['username'], 'name' => $r['name'], 'avatar_url' => $r['avatar_url']],
                'points' => $r['points'],
                'wins' => $r['wins'],
                'tournaments' => $r['tournaments'],
                'is_me' => $user?->id === $r['user_id'],
            ], $rows),
            'meta' => [
                'has_published_rules' => PointsRuleSet::where('discipline_id', $data['discipline_id'])->where('status', '!=', 'draft')->exists(),
            ],
        ]);
    }

    public function seasons(): JsonResponse
    {
        return response()->json([
            'data' => Season::whereIn('status', [SeasonStatus::Active->value, SeasonStatus::Closed->value])
                ->orderByDesc('starts_on')
                ->get()
                ->map(fn (Season $s) => ['id' => $s->id, 'name' => $s->name, 'status' => $s->status->value, 'starts_on' => $s->starts_on?->toDateString(), 'ends_on' => $s->ends_on?->toDateString()]),
        ]);
    }

    /** My points: total and every award with its reason. */
    public function myPoints(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = PointsLedgerEntry::with('tournament:id,slug,name')
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => [
                'total' => $this->points->totalFor($user),
                'history' => $rows->map(fn (PointsLedgerEntry $r) => [
                    'id' => $r->id,
                    'points' => $r->points_delta,
                    'award_type' => $r->award_type,
                    'reason' => $r->reason,
                    'tournament' => $r->tournament ? ['slug' => $r->tournament->slug, 'name' => $r->tournament->name] : null,
                    'created_at' => $r->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    // ---------------------------------------------------------------- Shaping

    private function stage(Stage $stage, ?User $user, array $myEntries): array
    {
        $rounds = $stage->matches->whereNull('stage_group_id')->groupBy('round')->map(fn ($ms, $round) => [
            'round' => (int) $round,
            'label' => TournamentMatch::translateLabel($ms->first()->label),
            'matches' => $ms->sortBy('position')->map(fn ($m) => $this->matchJson($m, $user, $myEntries))->values(),
        ])->values();

        return [
            'id' => $stage->id,
            'type' => $stage->type->value,
            'name' => $stage->displayName(),
            'status' => $stage->status->value,
            'standings' => $stage->type->value === 'round_robin' ? $this->standingsJson($stage, null, $myEntries) : null,
            'groups' => $stage->groups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'standings' => $this->standingsJson($stage, $g, $myEntries),
                'matches' => $stage->matches->where('stage_group_id', $g->id)->map(fn ($m) => $this->matchJson($m, $user, $myEntries))->values(),
            ])->values(),
            'rounds' => $rounds,
            'advance_per_group' => $stage->setting('advance_per_group'),
        ];
    }

    private function standingsJson(Stage $stage, $group, array $myEntries): array
    {
        return array_map(fn ($r) => $r + ['is_mine' => in_array($r['entry_id'], $myEntries, true)], $this->standings->table($stage, $group));
    }

    private function matchJson(TournamentMatch $m, ?User $user, array $myEntries, bool $detailed = false): array
    {
        $result = $m->approvedResult;
        $mySlot = null;
        foreach ($m->participants as $p) {
            if ($p->tournament_entry_id && in_array($p->tournament_entry_id, $myEntries, true)) {
                $mySlot = $p->slot;
            }
        }
        $isStaff = $user && ($user->can('manageFixtures', $m->tournament ?? $m->tournament()->first()) || $user->can('reviewResults', $m->tournament));
        $pending = ($mySlot || $isStaff) ? $m->results()->where('status', ResultStatus::Submitted->value)->first() : null;
        $window = (int) config('rivalo.competition.dispute_window_hours', 48);

        $json = [
            'id' => $m->id,
            'round' => $m->round,
            'position' => $m->position,
            'label' => TournamentMatch::translateLabel($m->label),
            'group' => $m->group?->name,
            'status' => $m->status->value,
            'scheduled_at' => $m->scheduled_at?->toIso8601String(),
            'location' => $m->location,
            'next_match_id' => $m->next_match_id,
            'slots' => collect([1, 2])->map(function ($slot) use ($m) {
                $p = $m->participants->firstWhere('slot', $slot);

                return [
                    'slot' => $slot,
                    'entry_id' => $p?->tournament_entry_id,
                    'name' => $p?->entry?->display_name,
                    'is_winner' => $p && $p->tournament_entry_id && $p->tournament_entry_id === $m->winner_entry_id,
                ];
            })->values(),
            'my_slot' => $mySlot,
            'result' => $result ? [
                'id' => $result->id,
                'outcome' => $result->outcome->value,
                'score_line' => $result->scoreLine(),
                'scores' => $result->scores,
                'winner_slot' => $result->winner_slot,
                'is_draw' => $m->is_draw,
                'approved_at' => $result->reviewed_at?->toIso8601String(),
            ] : null,
            'pending_submission' => $pending ? ['outcome' => $pending->outcome->value, 'score_line' => $pending->scoreLine(), 'by_my_side' => $pending->submitted_by_id === $user?->id] : null,
            'can_submit' => $mySlot !== null && $m->status === MatchStatus::Ready,
            'can_dispute' => $mySlot !== null && $result && $result->reviewed_at
                && $result->reviewed_at->copy()->addHours($window)->isFuture()
                && ! $result->disputes()->where('status', DisputeStatus::Open->value)->exists(),
        ];
        if ($detailed) {
            $json['dispute_window_hours'] = $window;
            $json['tournament'] = ['slug' => $m->tournament->slug, 'name' => $m->tournament->name];
            $json['open_dispute'] = $result && $mySlot ? $result->disputes()->where('status', DisputeStatus::Open->value)->exists() : false;
        }

        return $json;
    }

    /** @return list<int> */
    private function myEntryIds(?User $user, Tournament $tournament): array
    {
        if (! $user) {
            return [];
        }

        return TournamentEntryMember::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('tournament_entry_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
