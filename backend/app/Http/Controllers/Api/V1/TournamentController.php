<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RegistrationStatus;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentEntryResource;
use App\Http\Resources\TournamentResource;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Services\RegistrationService;
use App\Support\CheckInCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'sport_id' => ['nullable', 'integer'],
            'discipline_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_diff(TournamentStatus::values(), ['draft']))],
            'scope' => ['nullable', Rule::in(['upcoming', 'past', 'mine', 'for_me'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();

        // Guests can browse; personal lists need an account.
        if (! $user && in_array($data['scope'] ?? null, ['mine', 'for_me'], true)) {
            abort(401, __('Sign in to see tournaments for you.'));
        }

        $query = Tournament::query()
            ->visibleToPublic()
            ->with(['sport', 'discipline'])
            ->withCount(['reservedEntries', 'waitlistedEntries'])
            ->when($data['sport_id'] ?? null, fn ($q, $v) => $q->where('sport_id', $v))
            ->when($data['discipline_id'] ?? null, fn ($q, $v) => $q->where('discipline_id', $v))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        match ($data['scope'] ?? 'upcoming') {
            'upcoming' => $query->whereNotIn('status', [TournamentStatus::Completed->value, TournamentStatus::Cancelled->value])
                ->orderByRaw('starts_at IS NULL')->orderBy('starts_at'),
            'past' => $query->whereIn('status', [TournamentStatus::Completed->value, TournamentStatus::Cancelled->value])
                ->orderByDesc('starts_at'),
            'mine' => $query->whereHas('entries', fn ($q) => $q
                ->whereIn('registration_status', RegistrationStatus::activeValues())
                ->whereHas('activeMembers', fn ($m) => $m->where('user_id', $user->id)))
                ->orderBy('starts_at'),
            // Upcoming tournaments in the disciplines the user selected.
            'for_me' => $query->whereIn('discipline_id', $user->disciplines()->pluck('disciplines.id'))
                ->whereNotIn('status', [TournamentStatus::Completed->value, TournamentStatus::Cancelled->value])
                ->orderBy('starts_at'),
        };

        return TournamentResource::collection($query->paginate($data['per_page'] ?? 20));
    }

    public function show(Tournament $tournament): TournamentResource
    {
        abort_unless($tournament->status->isPublic(), 404);

        return TournamentResource::make($tournament->load(['sport', 'discipline', 'season']))->detailed();
    }

    /** Public entrant list: names only. */
    public function entries(Tournament $tournament): JsonResponse
    {
        abort_unless($tournament->status->isPublic(), 404);

        $entries = $tournament->entries()
            ->whereIn('registration_status', RegistrationStatus::activeValues())
            ->orderByRaw("CASE WHEN registration_status = 'reserved' THEN 0 ELSE 1 END")
            ->orderBy('reserved_at')
            ->orderBy('waitlist_position')
            ->get(['id', 'display_name', 'registration_status', 'waitlist_position', 'team_id']);

        return response()->json([
            'data' => $entries->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->display_name,
                'status' => $e->registration_status->value,
                'waitlist_position' => $e->waitlist_position,
            ]),
        ]);
    }

    public function myEntry(Request $request, Tournament $tournament): JsonResponse
    {
        $user = $request->user();
        $entry = $tournament->entries()
            ->where(fn ($q) => $q->where('registered_by_id', $user->id)
                ->orWhereHas('activeMembers', fn ($m) => $m->where('user_id', $user->id)))
            ->whereIn('registration_status', RegistrationStatus::activeValues())
            ->with(['team', 'activeMembers'])
            ->latest()
            ->first();

        return response()->json(['data' => $entry ? TournamentEntryResource::make($entry) : null]);
    }

    public function register(Request $request, Tournament $tournament): JsonResponse
    {
        abort_unless($tournament->status->isPublic(), 404);

        $data = $request->validate([
            'team_id' => ['nullable', 'integer'],
            'player_ids' => ['nullable', 'array', 'max:30'],
            'player_ids.*' => ['integer', 'distinct'],
            'substitute_ids' => ['nullable', 'array', 'max:15'],
            'substitute_ids.*' => ['integer', 'distinct'],
        ]);

        $team = isset($data['team_id']) ? Team::findOrFail($data['team_id']) : null;

        $entry = $this->registrations->register(
            $tournament,
            $request->user(),
            $team,
            $data['player_ids'] ?? [],
            $data['substitute_ids'] ?? [],
        );

        return TournamentEntryResource::make($entry->load(['tournament.discipline', 'team']))
            ->response()->setStatusCode(201);
    }

    public function updateRoster(Request $request, TournamentEntry $entry): TournamentEntryResource
    {
        $this->authorize('updateRoster', $entry);
        $data = $request->validate([
            'player_ids' => ['required', 'array', 'max:30'],
            'player_ids.*' => ['integer', 'distinct'],
            'substitute_ids' => ['nullable', 'array', 'max:15'],
            'substitute_ids.*' => ['integer', 'distinct'],
        ]);

        $entry = $this->registrations->updateRoster($entry, $request->user(), $data['player_ids'], $data['substitute_ids'] ?? []);

        return TournamentEntryResource::make($entry->load(['tournament.discipline', 'team']));
    }

    /**
     * The signed-in player's personal check-in code for an entry they are on. Each roster member
     * has their own code. The app shows it as a QR code (works offline once loaded).
     */
    public function checkInCode(Request $request, TournamentEntry $entry): JsonResponse
    {
        $member = $entry->activeMembers()->where('user_id', $request->user()->id)->first();
        abort_unless($member !== null, 404);

        if ($entry->registration_status !== RegistrationStatus::Reserved) {
            throw DomainRuleException::make('entry_not_reserved', 'Only entries with a reserved place can check in.', 409);
        }

        $tournament = $entry->tournament;
        if (in_array($tournament->status, [TournamentStatus::Completed, TournamentStatus::Cancelled], true)) {
            throw DomainRuleException::make('tournament_not_operational', 'Check-in and payments are only possible while the tournament is open for registration, closed for registration, or in progress.', 409);
        }

        $code = CheckInCode::for($member);

        return response()->json([
            'data' => [
                'code' => $code,
                'qr_payload' => CheckInCode::url($code),
                'member' => ['username' => $member->username_snapshot, 'display_name' => $member->display_name_snapshot],
                'entry' => ['id' => $entry->id, 'display_name' => $entry->display_name],
                'tournament' => [
                    'slug' => $tournament->slug,
                    'name' => $tournament->name,
                    'starts_at' => $tournament->starts_at?->toIso8601String(),
                    'check_in_closes_at' => $tournament->check_in_closes_at?->toIso8601String(),
                    'venue_name' => $tournament->venue_name,
                ],
                'check_in_status' => $entry->check_in_status->value,
                'payment_status' => $entry->payment_status->value,
            ],
        ]);
    }

    public function cancel(Request $request, TournamentEntry $entry): TournamentEntryResource
    {
        $this->authorize('cancel', $entry);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $entry = $this->registrations->cancel($entry, $request->user(), $data['reason'] ?? null);

        return TournamentEntryResource::make($entry->load(['tournament.discipline', 'team', 'activeMembers']));
    }
}
