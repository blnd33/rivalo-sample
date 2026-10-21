<?php

namespace App\Http\Resources;

use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TeamInvitation */
class TeamInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'role' => $this->role->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'team' => [
                'slug' => $this->team->slug,
                'name' => $this->team->name,
                'logo_url' => $this->team->logoUrl(),
                'sport' => $this->team->sport->localizedName(),
            ],
            'invited_by' => UserSummaryResource::make($this->invitedBy),
        ];
    }
}
