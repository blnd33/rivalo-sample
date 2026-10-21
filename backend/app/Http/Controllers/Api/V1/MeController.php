<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CaptainTransferStatus;
use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Http\Resources\TeamInvitationResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\TournamentEntryResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\CaptainTransfer;
use App\Models\Team;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * The signed-in user's own account. Every route here acts on $request->user() only.
 */
class MeController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function show(Request $request): MeResource
    {
        return MeResource::make($request->user());
    }

    public function update(Request $request): MeResource
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'locale' => ['sometimes', Rule::in(User::SUPPORTED_LOCALES)],
            // Private; used only for event operations. No SMS verification (no provider confirmed).
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{6,32}$/'],
        ]);

        $request->user()->update($data);

        return MeResource::make($request->user()->fresh());
    }

    public function uploadAvatar(Request $request): MeResource
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000'],
        ]);

        $this->accounts->updateAvatar($request->user(), $request->file('avatar'));

        return MeResource::make($request->user()->fresh());
    }

    public function deleteAvatar(Request $request): MeResource
    {
        $this->accounts->removeAvatar($request->user());

        return MeResource::make($request->user()->fresh());
    }

    public function updateDisciplines(Request $request): MeResource
    {
        $data = $request->validate([
            'discipline_ids' => ['present', 'array', 'max:30'],
            'discipline_ids.*' => ['integer', 'distinct'],
        ]);

        $this->accounts->syncDisciplines($request->user(), $data['discipline_ids']);

        return MeResource::make($request->user()->fresh());
    }

    public function updatePrivacy(Request $request): MeResource
    {
        $rules = [];
        foreach (array_keys(User::DEFAULT_PRIVACY) as $key) {
            $rules[$key] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);

        $user = $request->user();
        $user->update(['privacy' => array_merge($user->resolvedPrivacy(), $data)]);

        return MeResource::make($user->fresh());
    }

    public function updateNotificationPreferences(Request $request): MeResource
    {
        $rules = [];
        foreach (array_keys(User::DEFAULT_NOTIFICATION_PREFERENCES) as $key) {
            $rules[$key] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);

        $user = $request->user();
        $user->update(['notification_preferences' => array_merge($user->resolvedNotificationPreferences(), $data)]);

        return MeResource::make($user->fresh());
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $request->user();
        $this->accounts->changePassword($user, $data['current_password'], $data['password'], $user->currentAccessToken()?->id);

        return response()->json(['message' => 'Password updated. Other devices have been signed out.']);
    }

    /** Active sign-ins (one Sanctum token per device). */
    public function sessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken()?->id;

        return response()->json([
            'data' => $request->user()->tokens()->latest('last_used_at')->get()->map(fn ($t) => [
                'id' => $t->id,
                'device_name' => $t->name,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
                'is_current' => $t->id === $current,
            ]),
        ]);
    }

    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();
        abort_if($deleted === 0, 404);

        return response()->json(['message' => 'Device signed out.']);
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $deletion = $this->accounts->requestDeletion($request->user(), $data['password'], $data['reason'] ?? null);

        return response()->json([
            'message' => 'Account deletion scheduled. Sign in and cancel before the date shown to keep your account.',
            'scheduled_for' => $deletion->scheduled_for->toIso8601String(),
        ], 202);
    }

    public function cancelDeletion(Request $request): JsonResponse
    {
        $this->accounts->cancelDeletion($request->user());

        return response()->json(['message' => 'Account deletion cancelled.']);
    }

    public function teams(Request $request)
    {
        $teams = Team::query()
            ->whereHas('activeMemberships', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', 'active')
            ->with(['sport', 'captain', 'activeMemberships.user'])
            ->withCount('activeMemberships')
            ->orderBy('name')
            ->get();

        return TeamResource::collection($teams);
    }

    /** Pending team invitations and captain-transfer nominations addressed to me. */
    public function invitations(Request $request): JsonResponse
    {
        $user = $request->user();

        $invitations = $user->teamInvitations()
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->with(['team.sport', 'invitedBy'])
            ->latest()
            ->get();

        $transfers = CaptainTransfer::query()
            ->where('to_user_id', $user->id)
            ->where('status', CaptainTransferStatus::Pending->value)
            ->with(['team', 'fromUser'])
            ->get();

        return response()->json([
            'invitations' => TeamInvitationResource::collection($invitations),
            'captain_transfers' => $transfers->map(fn ($t) => [
                'id' => $t->id,
                'team' => ['slug' => $t->team->slug, 'name' => $t->team->name],
                'from' => UserSummaryResource::make($t->fromUser),
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** Entries where I am on the active roster or I registered the entry. */
    public function registrations(Request $request)
    {
        $user = $request->user();

        $entries = TournamentEntry::query()
            ->where(fn ($q) => $q->where('registered_by_id', $user->id)
                ->orWhereHas('activeMembers', fn ($m) => $m->where('user_id', $user->id)))
            ->with(['tournament.discipline', 'tournament.sport', 'team', 'activeMembers'])
            ->latest()
            ->limit(50)
            ->get();

        return TournamentEntryResource::collection($entries);
    }
}
