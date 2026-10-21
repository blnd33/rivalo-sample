<?php

namespace App\Http\Resources;

use App\Models\TournamentEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An entry as seen by its owner/members (includes the four status tracks).
 *
 * @mixin TournamentEntry
 */
class TournamentEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'tournament' => $this->whenLoaded('tournament', fn () => [
                'slug' => $this->tournament->slug,
                'name' => $this->tournament->name,
                'starts_at' => $this->tournament->starts_at?->toIso8601String(),
                'status' => $this->tournament->status->value,
                'discipline' => $this->tournament->discipline?->localizedName(),
                'cover_url' => $this->tournament->coverUrl(),
                // Only when the caller loaded it (lazy loading is disabled).
                'sport_icon' => $this->tournament->relationLoaded('sport') ? $this->tournament->sport?->icon : null,
            ]),
            'entry_type' => $this->entry_type->value,
            'display_name' => $this->display_name,
            'team' => $this->team ? ['slug' => $this->team->slug, 'name' => $this->team->name] : null,
            'registration_status' => $this->registration_status->value,
            'payment_status' => $this->payment_status->value,
            'check_in_status' => $this->check_in_status->value,
            'eligibility_status' => $this->eligibility_status->value,
            'waitlist_position' => $this->waitlist_position,
            'roster_locked' => $this->isRosterLocked(),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'eligibility_reason' => $this->eligibility_reason,
            // In-person receipts (valid records only). Staff names are not exposed to players.
            'payments' => $this->payments()->valid()->orderBy('recorded_at')->get()->map(fn ($p) => [
                'kind' => $p->kind,
                'amount_minor' => $p->amount_minor,
                'currency' => $p->currency,
                'formatted' => $p->formattedAmount(),
                'reference' => $p->reference,
                'recorded_at' => $p->recorded_at->toIso8601String(),
            ])->values(),
            'members' => $this->activeMembers->map(fn ($m) => [
                'user_id' => $m->user_id,
                'username' => $m->username_snapshot,
                'display_name' => $m->display_name_snapshot,
                'role' => $m->role->value,
            ])->values(),
            'can_manage' => $user ? $this->isOwnedBy($user) : false,
            'reserved_at' => $this->reserved_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
