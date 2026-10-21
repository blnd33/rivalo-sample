<?php

namespace App\Models;

use App\Enums\FundingModel;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Enums\StaffRole;
use App\Enums\ThirdPlaceMethod;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'slug', 'name', 'cover_path', 'sport_id', 'discipline_id', 'season_id', 'description',
    'registration_type', 'eligibility_notes', 'min_entrants', 'max_entrants', 'roster_min',
    'roster_max', 'substitutes_max', 'registration_opens_at', 'registration_closes_at',
    'check_in_closes_at', 'starts_at', 'ends_at', 'timezone', 'venue_name', 'venue_address',
    'city', 'online_region', 'lobby_details', 'rules', 'format', 'third_place_method',
    'third_place_notes', 'funding_model', 'entry_fee_minor', 'currency', 'prize_rules',
    'ranking_eligible',
])]
class Tournament extends Model
{
    use HasFactory;

    /** Fields whose change after registration opens requires a reason and audit (financial/material rules). */
    public const MATERIAL_FIELDS = [
        'entry_fee_minor', 'currency', 'funding_model', 'prize_rules', 'registration_type',
        'roster_min', 'roster_max', 'substitutes_max', 'format', 'third_place_method', 'ranking_eligible',
    ];

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'IQD',
        'entry_fee_minor' => 0,
        'funding_model' => 'free',
        'third_place_method' => 'playoff_match',
    ];

    protected function casts(): array
    {
        return [
            'registration_type' => RegistrationType::class,
            'format' => TournamentFormat::class,
            'third_place_method' => ThirdPlaceMethod::class,
            'funding_model' => FundingModel::class,
            'status' => TournamentStatus::class,
            'entry_fee_minor' => 'integer',
            'min_entrants' => 'integer',
            'max_entrants' => 'integer',
            'roster_min' => 'integer',
            'roster_max' => 'integer',
            'substitutes_max' => 'integer',
            'ranking_eligible' => 'boolean',
            'is_sample_data' => 'boolean',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'check_in_closes_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'financial_rules_locked_at' => 'datetime',
            'points_rules_locked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------------------------------------------------------------- Relationships

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(TournamentStaff::class);
    }

    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_staff')
            ->wherePivot('role', StaffRole::Organizer->value);
    }

    public function referees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_staff')
            ->wherePivot('role', StaffRole::Referee->value);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TournamentEntry::class);
    }

    public function reservedEntries(): HasMany
    {
        return $this->entries()->where('registration_status', RegistrationStatus::Reserved->value);
    }

    public function waitlistedEntries(): HasMany
    {
        return $this->entries()->where('registration_status', RegistrationStatus::Waitlisted->value);
    }

    // ---------------------------------------------------------------- Competition (M5)

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('sequence');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function pointsRuleSet(): BelongsTo
    {
        return $this->belongsTo(PointsRuleSet::class);
    }

    // ---------------------------------------------------------------- Finance (M4B)

    public function financeRule(): HasOne
    {
        return $this->hasOne(TournamentFinanceRule::class);
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(TournamentPrize::class)->orderBy('place');
    }

    public function sponsorContributions(): HasMany
    {
        return $this->hasMany(SponsorContribution::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TournamentLedgerEntry::class);
    }

    // ---------------------------------------------------------------- Scopes

    /** Tournaments players may see (everything except drafts). */
    public function scopeVisibleToPublic(Builder $query): void
    {
        $query->where('status', '!=', TournamentStatus::Draft->value);
    }

    /** Tournaments the given staff member may manage in the admin panel. */
    public function scopeManageableBy(Builder $query, User $user): void
    {
        if ($user->can('tournaments.manage_all')) {
            return;
        }

        $query->whereHas('staff', fn ($q) => $q->where('user_id', $user->id));
    }

    // ---------------------------------------------------------------- Helpers

    public function isPaidEntry(): bool
    {
        return $this->entry_fee_minor > 0;
    }

    public function isRegistrationWindowOpen(): bool
    {
        if ($this->status !== TournamentStatus::RegistrationOpen) {
            return false;
        }

        $now = now();

        return ($this->registration_opens_at === null || $this->registration_opens_at->lte($now))
            && ($this->registration_closes_at === null || $this->registration_closes_at->gt($now));
    }

    public function areMaterialRulesLocked(): bool
    {
        return $this->financial_rules_locked_at !== null;
    }

    public function formattedEntryFee(): string
    {
        return $this->isPaidEntry() ? Money::format($this->entry_fee_minor, $this->currency) : __('Free');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
