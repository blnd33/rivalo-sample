<?php

namespace App\Models;

use App\Enums\CheckInStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registration of an individual, pair or team in a tournament.
 * Registration, payment, check-in and eligibility are tracked as independent statuses.
 */
#[Fillable([
    'tournament_id', 'entry_type', 'team_id', 'display_name', 'registered_by_id',
    'registration_status', 'payment_status', 'check_in_status', 'eligibility_status',
    'waitlist_position', 'reserved_at', 'cancelled_at', 'cancellation_reason', 'cancelled_by_id',
    'roster_locked_at', 'seed', 'checked_in_at', 'checked_in_by_id', 'checked_in_member_id',
    'eligibility_reason',
])]
class TournamentEntry extends Model
{
    protected $attributes = [
        'check_in_status' => 'not_checked_in',
        'eligibility_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => RegistrationType::class,
            'registration_status' => RegistrationStatus::class,
            'payment_status' => PaymentStatus::class,
            'check_in_status' => CheckInStatus::class,
            'eligibility_status' => EligibilityStatus::class,
            'waitlist_position' => 'integer',
            'reserved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'roster_locked_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TournamentEntryMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EntryPayment::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_id');
    }

    /** Net amount received in person: valid payments minus valid refunds (minor units). */
    public function netPaidMinor(): int
    {
        $rows = $this->payments()->valid()->get(['kind', 'amount_minor']);

        return (int) $rows->where('kind', EntryPayment::KIND_PAYMENT)->sum('amount_minor')
            - (int) $rows->where('kind', EntryPayment::KIND_REFUND)->sum('amount_minor');
    }

    public function isActive(): bool
    {
        return $this->registration_status->isActive();
    }

    public function isRosterLocked(): bool
    {
        return $this->roster_locked_at !== null;
    }

    /** Whether the user may act on this entry as its owner (registrant or current team captain). */
    public function isOwnedBy(User $user): bool
    {
        if ($this->registered_by_id === $user->id) {
            return true;
        }

        return $this->team !== null && $this->team->isCaptain($user);
    }
}
