<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamInvitationResource;
use App\Http\Resources\TeamResource;
use App\Models\CaptainTransfer;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamService;
use App\Support\ImageSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Team::class);

        $data = $request->validate([
            'sport_id' => ['required', 'integer', Rule::exists('sports', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        $team = $this->teams->create($request->user(), Sport::findOrFail($data['sport_id']), $data['name']);

        return TeamResource::make($this->loadTeam($team))->response()->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        return TeamResource::make($this->loadTeam($team));
    }

    public function update(Request $request, Team $team): TeamResource
    {
        $this->authorize('manage', $team);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:60']]);

        $this->teams->rename($team, $request->user(), $data['name']);

        return TeamResource::make($this->loadTeam($team->fresh()));
    }

    public function uploadLogo(Request $request, Team $team): TeamResource
    {
        $this->authorize('manage', $team);
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000'],
        ]);

        // Re-encoded like avatars: strips photo metadata such as GPS location before it goes public.
        $file = $request->file('logo');
        $clean = ImageSanitizer::reencode($file);
        $path = 'team-logos/'.$team->id.'/'.Str::uuid().'.'.($clean === null ? $file->extension() : 'jpg');
        Storage::disk('public')->put($path, $clean ?? $file->get());
        if ($team->logo_path) {
            Storage::disk('public')->delete($team->logo_path);
        }
        $team->forceFill(['logo_path' => $path])->save();

        return TeamResource::make($this->loadTeam($team));
    }

    public function invite(Request $request, Team $team): JsonResponse
    {
        $this->authorize('manage', $team);
        $data = $request->validate([
            'username' => ['required', 'string', 'max:30'],
            'role' => ['sometimes', Rule::in([TeamRole::Member->value, TeamRole::Substitute->value])],
        ]);

        $invitee = User::where('username', mb_strtolower($data['username']))->firstOrFail();
        $invitation = $this->teams->invite($team, $request->user(), $invitee, TeamRole::from($data['role'] ?? 'member'));

        return TeamInvitationResource::make($invitation->load(['team.sport', 'invitedBy']))->response()->setStatusCode(201);
    }

    public function revokeInvitation(Request $request, Team $team, TeamInvitation $invitation): JsonResponse
    {
        $this->authorize('manage', $team);
        abort_unless($invitation->team_id === $team->id, 404);

        $this->teams->revokeInvitation($invitation, $request->user());

        return response()->json(['message' => 'Invitation revoked.']);
    }

    public function acceptInvitation(Request $request, TeamInvitation $invitation): TeamResource
    {
        $this->teams->acceptInvitation($invitation, $request->user());

        return TeamResource::make($this->loadTeam($invitation->team->fresh()));
    }

    public function declineInvitation(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $this->teams->declineInvitation($invitation, $request->user());

        return response()->json(['message' => 'Invitation declined.']);
    }

    public function leave(Request $request, Team $team): JsonResponse
    {
        $this->teams->leave($team, $request->user());

        return response()->json(['message' => 'You left the team.']);
    }

    public function removeMember(Request $request, Team $team, User $user): JsonResponse
    {
        $this->authorize('manage', $team);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:120']]);

        $this->teams->removeMember($team, $request->user(), $user, $data['reason'] ?? null);

        return response()->json(['message' => 'Member removed.']);
    }

    public function changeMemberRole(Request $request, Team $team, User $user): TeamResource
    {
        $this->authorize('manage', $team);
        $data = $request->validate(['role' => ['required', Rule::in([TeamRole::Member->value, TeamRole::Substitute->value])]]);

        $this->teams->changeMemberRole($team, $request->user(), $user, TeamRole::from($data['role']));

        return TeamResource::make($this->loadTeam($team));
    }

    public function requestCaptainTransfer(Request $request, Team $team): JsonResponse
    {
        $this->authorize('manage', $team);
        $data = $request->validate(['username' => ['required', 'string', 'max:30']]);

        $nominee = User::where('username', mb_strtolower($data['username']))->firstOrFail();
        $transfer = $this->teams->requestCaptainTransfer($team, $request->user(), $nominee);

        return response()->json(['id' => $transfer->id, 'status' => $transfer->status->value], 201);
    }

    public function respondCaptainTransfer(Request $request, CaptainTransfer $transfer): JsonResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        $transfer = $this->teams->respondToCaptainTransfer($transfer, $request->user(), (bool) $data['accept']);

        return response()->json(['id' => $transfer->id, 'status' => $transfer->status->value]);
    }

    public function cancelCaptainTransfer(Request $request, CaptainTransfer $transfer): JsonResponse
    {
        abort_unless($transfer->from_user_id === $request->user()->id, 403);
        $this->teams->cancelCaptainTransfer($transfer, $request->user());

        return response()->json(['message' => 'Transfer cancelled.']);
    }

    public function disband(Request $request, Team $team): JsonResponse
    {
        $this->authorize('manage', $team);
        $this->teams->disband($team, $request->user());

        return response()->json(['message' => 'Team disbanded.']);
    }

    private function loadTeam(Team $team): Team
    {
        return $team->load(['sport', 'captain', 'activeMemberships.user', 'invitations.invitedUser', 'captainTransfers.toUser'])
            ->loadCount('activeMemberships');
    }
}
