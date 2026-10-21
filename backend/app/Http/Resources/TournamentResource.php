<?php

namespace App\Http\Resources;

use App\Enums\CheckInStatus;
use App\Enums\ContributionStatus;
use App\Models\Sponsor;
use App\Models\Tournament;
use App\Services\FinanceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public tournament representation. `detail` adds long-form fields.
 * Lobby details are shown only to staff and checked-in entrants.
 *
 * @mixin Tournament
 */
class TournamentResource extends JsonResource
{
    public bool $detail = false;

    public function detailed(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $reserved = $this->reserved_entries_count ?? $this->reservedEntries()->count();
        $waitlisted = $this->waitlisted_entries_count ?? $this->waitlistedEntries()->count();

        $base = [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'cover_url' => $this->coverUrl(),
            'status' => $this->status->value,
            'status_reason' => $this->status_reason,
            'sport' => ['id' => $this->sport_id, 'name' => $this->sport?->localizedName(), 'icon' => $this->sport?->icon],
            'discipline' => ['id' => $this->discipline_id, 'name' => $this->discipline?->localizedName()],
            'registration_type' => $this->registration_type->value,
            'format' => $this->format->value,
            'city' => $this->city,
            'venue_name' => $this->venue_name,
            'online_region' => $this->online_region,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'registration_opens_at' => $this->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $this->registration_closes_at?->toIso8601String(),
            'registration_is_open' => $this->isRegistrationWindowOpen(),
            'capacity' => [
                'min_entrants' => $this->min_entrants,
                'max_entrants' => $this->max_entrants,
                'reserved' => $reserved,
                'waitlisted' => $waitlisted,
                'places_left' => max(0, $this->max_entrants - $reserved),
            ],
            'entry_fee' => [
                'amount_minor' => $this->entry_fee_minor,
                'currency' => $this->currency,
                'formatted' => $this->formattedEntryFee(),
                'is_paid' => $this->isPaidEntry(),
                // Fees are paid in person at the venue; there is no online checkout.
                'payment_method' => $this->isPaidEntry() ? 'in_person' : null,
            ],
            'ranking_eligible' => $this->ranking_eligible,
            'is_sample_data' => $this->is_sample_data,
        ];

        if (! $this->detail) {
            return $base;
        }

        $user = $request->user();
        $showLobby = $user && (
            $user->isAssignedTo($this->resource)
            || $this->entries()
                ->where('check_in_status', CheckInStatus::CheckedIn->value)
                ->whereHas('activeMembers', fn ($q) => $q->where('user_id', $user->id))
                ->exists()
        );

        return $base + [
            'description' => $this->description,
            'eligibility_notes' => $this->eligibility_notes,
            'rules' => $this->rules,
            'prize_rules' => $this->prize_rules,
            'funding_model' => $this->funding_model->value,
            'third_place_method' => $this->third_place_method->value,
            'third_place_notes' => $this->third_place_notes,
            'roster' => [
                'min' => $this->roster_min,
                'max' => $this->roster_max,
                'substitutes_max' => $this->substitutes_max,
            ],
            'check_in_closes_at' => $this->check_in_closes_at?->toIso8601String(),
            'venue_address' => $this->venue_address,
            'lobby_details' => $showLobby ? $this->lobby_details : null,
            'rules_locked' => $this->areMaterialRulesLocked(),
            'season' => $this->season ? ['id' => $this->season->id, 'name' => $this->season->name] : null,
            'prizes' => $this->publicPrizes(),
            'sponsors' => $this->publicSponsors(),
            // Fixtures and brackets arrive in Milestone 5.
            'features' => ['fixtures' => false, 'bracket' => false, 'sponsors' => true],
        ];
    }

    /**
     * Prize tiers with their certainty label. Pool-share amounts appear only once the finance
     * rules are approved; until then the amount is null ("to be announced"), never a guess.
     */
    private function publicPrizes(): array
    {
        $finance = app(FinanceService::class);
        $prizes = $this->prizes()->get();
        if ($prizes->isEmpty()) {
            return [];
        }
        $projection = $finance->projection($this->resource);

        return $prizes->map(function ($prize) use ($finance, $projection) {
            $amount = $finance->prizeAmount($prize->setRelation('tournament', $this->resource), $projection);

            return [
                'place' => $prize->place,
                'title' => $prize->title,
                'label' => $prize->label->value,
                'amount_minor' => $amount,
                'amount_formatted' => $amount === null ? null : Money::format($amount, $this->currency),
                'in_kind' => $prize->in_kind_description,
            ];
        })->all();
    }

    /** Sponsors who agreed to be shown, with an active contribution. No amounts or contacts. */
    private function publicSponsors(): array
    {
        return Sponsor::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->whereHas('contributions', fn ($q) => $q
                ->where('tournament_id', $this->id)
                ->where('status', '!=', ContributionStatus::Cancelled->value))
            ->orderBy('name')
            ->get()
            ->map(fn (Sponsor $s) => [
                'name' => $s->name,
                'logo_url' => $s->logoUrl(),
                'website_url' => $s->website_url,
                'is_sample_data' => $s->is_sample_data,
            ])
            ->all();
    }
}
