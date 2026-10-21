<?php

namespace App\Http\Resources;

use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sport */
class SportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localizedName(),
            'kind' => $this->kind->value,
            'icon' => $this->icon,
            'supports_teams' => $this->supportsTeams(),
            'team_member_limit' => $this->team_member_limit,
            'disciplines' => DisciplineResource::collection($this->whenLoaded('disciplines')),
        ];
    }
}
