<?php

namespace App\Http\Resources;

use App\Models\Discipline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Discipline */
class DisciplineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sport_id' => $this->sport_id,
            'slug' => $this->slug,
            'name' => $this->localizedName(),
            'sport_name' => $this->whenLoaded('sport', fn () => $this->sport->localizedName()),
            'registration_types' => $this->registration_types,
            'team_size_min' => $this->team_size_min,
            'team_size_max' => $this->team_size_max,
            'substitutes_max' => $this->substitutes_max,
            'game_title' => $this->game_title,
            'game_version' => $this->game_version,
            'platform' => $this->platform,
            'region' => $this->region,
            'match_format' => $this->match_format,
            'competition_mode' => $this->competition_mode?->value,
            'ruleset_confirmed' => $this->ruleset_status?->value === 'confirmed',
        ];
    }
}
