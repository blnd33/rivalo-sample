<?php

namespace App\Http\Resources;

use App\Enums\RegistrationStatus;
use App\Enums\TournamentStatus;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\AchievementService;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public profile. This is a whitelist: email, phone, privacy settings, status, moderation data,
 * notification preferences and any identity data are never included.
 *
 * @mixin User
 */
class PublicProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $showTeams = $this->privacySetting('show_teams');
        $showHistory = $this->privacySetting('show_tournament_history');

        return [
            'username' => $this->username,
            'display_name' => $this->privacySetting('show_display_name') ? $this->display_name : null,
            'avatar_url' => $this->avatarUrl(),
            'city' => $this->privacySetting('show_city') ? $this->city : null,
            'disciplines' => DisciplineResource::collection($this->disciplines()->active()->with('sport')->get()),
            'teams' => $showTeams
                ? $this->activeMemberships()->with('team.sport')->get()
                    ->filter(fn ($m) => $m->team?->isActive())
                    ->map(fn ($m) => [
                        'slug' => $m->team->slug,
                        'name' => $m->team->name,
                        'sport' => $m->team->sport->name,
                        'logo_url' => $m->team->logoUrl(),
                        'role' => $m->role->value,
                    ])->values()
                : null,
            // Verified history: only completed tournaments where the entry was not cancelled.
            'tournament_history' => $showHistory
                ? $this->entryMemberships()
                    ->whereHas('entry', fn ($q) => $q->whereNotIn('registration_status', [RegistrationStatus::Cancelled->value, RegistrationStatus::Rejected->value]))
                    ->whereHas('tournament', fn ($q) => $q->where('status', TournamentStatus::Completed->value))
                    ->with('tournament.discipline')
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn ($m) => [
                        'tournament_slug' => $m->tournament->slug,
                        'tournament_name' => $m->tournament->name,
                        'discipline' => $m->tournament->discipline->name,
                        'ended_at' => $m->tournament->ends_at?->toIso8601String(),
                    ])->values()
                : null,
            // Badges and points reveal results, so they follow the tournament-history privacy setting.
            'points' => $showHistory ? ['total' => app(PointsService::class)->totalFor($this->resource)] : null,
            'badges' => $showHistory
                ? array_map(fn ($b) => array_intersect_key($b, array_flip(['key', 'name', 'description', 'icon', 'awarded_at'])), app(AchievementService::class)->progress($this->resource, earnedOnly: true))
                : null,
            'member_since' => $this->created_at?->format('Y-m'),
            // Only about the signed-in viewer's own choices (never whether they are blocked).
            'viewer' => $request->user() ? [
                'is_self' => $request->user()->id === $this->id,
                'blocked' => UserBlock::where('blocker_id', $request->user()->id)->where('blocked_id', $this->id)->exists(),
            ] : null,
        ];
    }
}
