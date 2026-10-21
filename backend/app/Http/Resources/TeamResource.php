<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isCaptain = $viewer && $this->captain_id === $viewer->id;
        $isMember = $viewer && $this->relationLoaded('activeMemberships')
            ? $this->activeMemberships->contains('user_id', $viewer->id)
            : false;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'logo_url' => $this->logoUrl(),
            'status' => $this->status->value,
            'sport' => [
                'id' => $this->sport_id,
                'name' => $this->sport?->localizedName(),
                'team_member_limit' => $this->sport?->team_member_limit,
            ],
            'captain' => $this->whenLoaded('captain', fn () => UserSummaryResource::make($this->captain)),
            'members' => $this->whenLoaded('activeMemberships', fn () => $this->activeMemberships->map(fn ($m) => [
                'user' => UserSummaryResource::make($m->user),
                'role' => $m->role->value,
                'joined_at' => $m->joined_at?->toIso8601String(),
            ])->values()),
            'member_count' => $this->whenCounted('activeMemberships'),
            'viewer' => [
                'is_captain' => $isCaptain,
                'is_member' => $isMember,
            ],
            // Pending invitations and transfers are visible to the captain only.
            'pending_invitations' => $this->when($isCaptain && $this->relationLoaded('invitations'), fn () => $this->invitations
                ->where('status.value', 'pending')
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'user' => UserSummaryResource::make($i->invitedUser),
                    'role' => $i->role->value,
                    'expires_at' => $i->expires_at->toIso8601String(),
                ])->values()),
            'pending_captain_transfer' => $this->when($isCaptain && $this->relationLoaded('captainTransfers'), function () {
                $t = $this->captainTransfers->firstWhere('status.value', 'pending');

                return $t ? ['id' => $t->id, 'to' => UserSummaryResource::make($t->toUser)] : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
