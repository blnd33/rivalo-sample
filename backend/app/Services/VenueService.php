<?php

namespace App\Services;

use App\Enums\CheckInStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\EntryPayment;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentEntryMember;
use App\Models\User;
use App\Support\Audit;
use App\Support\CheckInCode;
use App\Support\FeatureFlags;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Venue operations (Milestone 4A): staff-recorded cash payments, corrections, refunds,
 * check-in and eligibility.
 *
 * Every method re-checks staff permission and assignment (defense in depth, in addition to the
 * admin UI), runs in one transaction with row locks, and writes an audit record.
 *
 * Eligibility to compete = reserved place + checked in + entry fee settled (paid or not
 * required), unless staff marked the entry ineligible with a reason.
 */
class VenueService
{
    public function __construct(private readonly FinanceService $finance) {}

    /** Statuses during which venue operations may happen. */
    private const OPERATIONAL = [
        TournamentStatus::RegistrationOpen,
        TournamentStatus::RegistrationClosed,
        TournamentStatus::InProgress,
    ];

    // ---------------------------------------------------------------- Codes

    /** Resolve a scanned/typed check-in code to an active roster member, or refuse. */
    public function resolveCode(string $input, User $staff): TournamentEntryMember
    {
        $member = CheckInCode::resolve($input);
        if (! $member) {
            throw DomainRuleException::make('invalid_code', 'This check-in code is not valid.', 404);
        }

        $tournament = $member->entry->tournament;
        if (! $staff->can('recordCheckIn', $tournament)) {
            throw DomainRuleException::make('not_assigned', 'You are not assigned to check in entrants for this tournament.', 403);
        }
        if (! $member->is_active || ! $member->entry->isActive()) {
            throw DomainRuleException::make('code_inactive', 'This player is no longer on an active entry for this tournament.', 409);
        }

        return $member;
    }

    // ---------------------------------------------------------------- Payments

    /**
     * Record the entry fee paid in cash. The amount must equal the published fee exactly; a
     * mistake is corrected by voiding and recording again. Returns the receipt row.
     */
    public function recordPayment(TournamentEntry $entry, User $staff, ?int $amountMinor = null, ?string $note = null): EntryPayment
    {
        try {
            return DB::transaction(function () use ($entry, $staff, $amountMinor, $note) {
                [$tournament, $entry] = $this->lock($entry);
                $this->assertCan($staff, 'recordPayments', $tournament);
                $this->assertOperational($tournament);

                if (! $tournament->isPaidEntry()) {
                    throw DomainRuleException::make('free_entry', 'This tournament is free. No payment is needed.', 409);
                }
                if (! FeatureFlags::enabled(FeatureFlags::PAID_ENTRY)) {
                    throw DomainRuleException::make('paid_entry_disabled', 'Paid-entry participation is not enabled. New payments cannot be recorded.', 409);
                }
                if ($entry->registration_status !== RegistrationStatus::Reserved) {
                    throw DomainRuleException::make('entry_not_reserved', 'Only entries with a reserved place can pay. Waitlisted or cancelled entries cannot.', 409);
                }

                $amount = $amountMinor ?? $tournament->entry_fee_minor;
                if ($amount !== $tournament->entry_fee_minor) {
                    throw DomainRuleException::make('amount_mismatch', 'The amount must equal the entry fee of :fee.', 422, ['fee' => $tournament->formattedEntryFee()]);
                }
                if ($entry->payments()->valid()->where('kind', EntryPayment::KIND_PAYMENT)->exists()) {
                    throw DomainRuleException::make('already_paid', 'A payment is already recorded for this entry. Void it first if it was a mistake.', 409);
                }

                $payment = EntryPayment::create([
                    'tournament_entry_id' => $entry->id,
                    'tournament_id' => $tournament->id,
                    'kind' => EntryPayment::KIND_PAYMENT,
                    'method' => 'cash',
                    'amount_minor' => $amount,
                    'currency' => $tournament->currency,
                    'reference' => $this->nextReference($tournament, 'RV'),
                    'note' => $note,
                    'recorded_by_id' => $staff->id,
                    'recorded_at' => now(),
                ]);

                $this->refreshStatuses($entry, $tournament);
                // Same transaction: the receipt and its ledger row exist together or not at all.
                $this->finance->syncEntryPayment($payment, $staff);

                Audit::record('payment.recorded', $entry, new: [
                    'reference' => $payment->reference, 'amount_minor' => $amount, 'currency' => $payment->currency,
                ], actorId: $staff->id);

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainRuleException::make('already_paid', 'A payment is already recorded for this entry. Void it first if it was a mistake.', 409);
        }
    }

    /** Correct a mistaken payment or refund. The row stays in history, marked void with a reason. */
    public function voidPayment(EntryPayment $payment, User $staff, string $reason): EntryPayment
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($payment, $staff, $reason) {
            [$tournament, $entry] = $this->lock($payment->entry);
            $this->assertCan($staff, 'recordPayments', $tournament);
            $payment = EntryPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->isVoided()) {
                throw DomainRuleException::make('already_voided', 'This record is already void.', 409);
            }
            if (! $payment->isRefund() && $entry->payments()->valid()->where('kind', EntryPayment::KIND_REFUND)->exists()) {
                throw DomainRuleException::make('refunds_exist', 'Void the refunds for this entry before voiding its payment.', 409);
            }

            $payment->update(['voided_at' => now(), 'voided_by_id' => $staff->id, 'void_reason' => $reason]);
            $this->refreshStatuses($entry, $tournament);
            $this->finance->syncVoidedPayment($payment, $staff);

            Audit::record('payment.voided', $entry, ['reference' => $payment->reference], ['voided' => true], $reason, $staff->id);

            return $payment;
        });
    }

    /** Record cash handed back to the entrant. Allowed even when paid entry is switched off. */
    public function recordRefund(TournamentEntry $entry, User $staff, int $amountMinor, string $reason): EntryPayment
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($entry, $staff, $amountMinor, $reason) {
            [$tournament, $entry] = $this->lock($entry);
            $this->assertCan($staff, 'recordPayments', $tournament);

            $net = $entry->netPaidMinor();
            if ($amountMinor <= 0 || $amountMinor > $net) {
                throw DomainRuleException::make('invalid_refund', 'The refund must be more than 0 and at most the :paid received.', 422, ['paid' => Money::format($net, $tournament->currency)]);
            }

            $refund = EntryPayment::create([
                'tournament_entry_id' => $entry->id,
                'tournament_id' => $tournament->id,
                'kind' => EntryPayment::KIND_REFUND,
                'method' => 'cash',
                'amount_minor' => $amountMinor,
                'currency' => $tournament->currency,
                'reference' => $this->nextReference($tournament, 'RF'),
                'note' => $reason,
                'recorded_by_id' => $staff->id,
                'recorded_at' => now(),
            ]);

            $this->refreshStatuses($entry, $tournament);
            $this->finance->syncEntryPayment($refund, $staff);

            Audit::record('payment.refunded', $entry, new: ['reference' => $refund->reference, 'amount_minor' => $amountMinor], reason: $reason, actorId: $staff->id);

            return $refund;
        });
    }

    // ---------------------------------------------------------------- Check-in

    /**
     * Check an entry in at the venue. Requires a reserved place and a settled entry fee. After
     * the check-in deadline, a written reason is required (late check-in), and it is audited.
     */
    public function checkIn(TournamentEntry $entry, User $staff, ?TournamentEntryMember $scanned = null, ?string $lateReason = null): TournamentEntry
    {
        return DB::transaction(function () use ($entry, $staff, $scanned, $lateReason) {
            [$tournament, $entry] = $this->lock($entry);
            $this->assertCan($staff, 'recordCheckIn', $tournament);
            $this->assertOperational($tournament);

            if ($entry->registration_status !== RegistrationStatus::Reserved) {
                throw DomainRuleException::make('entry_not_reserved', 'Only entries with a reserved place can check in.', 409);
            }
            if ($entry->check_in_status === CheckInStatus::CheckedIn) {
                throw DomainRuleException::make('already_checked_in', 'Already checked in at :time.', 409, ['time' => $entry->checked_in_at?->timezone($tournament->timezone)->format('H:i')]);
            }
            if (! in_array($entry->payment_status, [PaymentStatus::Paid, PaymentStatus::NotRequired], true)) {
                throw DomainRuleException::make('payment_required', 'Record the entry fee before checking in.', 409);
            }
            if ($scanned && ($scanned->tournament_entry_id !== $entry->id || ! $scanned->is_active)) {
                throw DomainRuleException::make('code_mismatch', 'This code belongs to a different entry.', 409);
            }

            $late = $tournament->check_in_closes_at !== null && $tournament->check_in_closes_at->isPast();
            if ($late && blank($lateReason)) {
                throw DomainRuleException::make('check_in_closed', 'Check-in has closed. Add a reason to record a late check-in.', 409);
            }

            $entry->forceFill([
                'check_in_status' => CheckInStatus::CheckedIn,
                'checked_in_at' => now(),
                'checked_in_by_id' => $staff->id,
                'checked_in_member_id' => $scanned?->id,
            ])->save();
            $this->refreshStatuses($entry, $tournament);

            Audit::record($late ? 'checkin.recorded_late' : 'checkin.recorded', $entry, new: [
                'scanned_member_id' => $scanned?->id,
                'eligibility' => $entry->eligibility_status->value,
            ], reason: $lateReason, actorId: $staff->id);
            app(AchievementService::class)->evaluateEntries([$entry->id]);

            return $entry;
        });
    }

    public function undoCheckIn(TournamentEntry $entry, User $staff, string $reason): TournamentEntry
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($entry, $staff, $reason) {
            [$tournament, $entry] = $this->lock($entry);
            $this->assertCan($staff, 'recordCheckIn', $tournament);

            if ($entry->check_in_status !== CheckInStatus::CheckedIn) {
                throw DomainRuleException::make('not_checked_in', 'This entry is not checked in.', 409);
            }

            $entry->forceFill([
                'check_in_status' => CheckInStatus::NotCheckedIn,
                'checked_in_at' => null,
                'checked_in_by_id' => null,
                'checked_in_member_id' => null,
            ])->save();
            $this->refreshStatuses($entry, $tournament);

            Audit::record('checkin.undone', $entry, reason: $reason, actorId: $staff->id);

            return $entry;
        });
    }

    /** Staff decision that an entry may not compete (e.g. failed identity check). Reason required. */
    public function markIneligible(TournamentEntry $entry, User $staff, string $reason): TournamentEntry
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($entry, $staff, $reason) {
            [$tournament, $entry] = $this->lock($entry);
            $this->assertCan($staff, 'recordCheckIn', $tournament);

            $old = $entry->eligibility_status->value;
            $entry->forceFill(['eligibility_status' => EligibilityStatus::Ineligible, 'eligibility_reason' => $reason])->save();

            Audit::record('eligibility.ineligible', $entry, ['eligibility' => $old], ['eligibility' => 'ineligible'], $reason, $staff->id);

            return $entry;
        });
    }

    /** Remove a manual ineligibility decision; eligibility is then computed again. */
    public function clearIneligible(TournamentEntry $entry, User $staff, string $reason): TournamentEntry
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($entry, $staff, $reason) {
            [$tournament, $entry] = $this->lock($entry);
            $this->assertCan($staff, 'recordCheckIn', $tournament);

            $entry->forceFill(['eligibility_reason' => null, 'eligibility_status' => EligibilityStatus::Pending])->save();
            $this->refreshStatuses($entry, $tournament);

            Audit::record('eligibility.cleared', $entry, ['eligibility' => 'ineligible'], ['eligibility' => $entry->eligibility_status->value], $reason, $staff->id);

            return $entry;
        });
    }

    // ---------------------------------------------------------------- Internals

    /** Recompute payment status (from valid payments/refunds) and eligibility. */
    private function refreshStatuses(TournamentEntry $entry, Tournament $tournament): void
    {
        $rows = $entry->payments()->valid()->get(['kind', 'amount_minor']);
        $paid = (int) $rows->where('kind', EntryPayment::KIND_PAYMENT)->sum('amount_minor');
        $refunded = (int) $rows->where('kind', EntryPayment::KIND_REFUND)->sum('amount_minor');

        $payment = match (true) {
            ! $tournament->isPaidEntry() && $paid === 0 => PaymentStatus::NotRequired,
            $paid === 0 => PaymentStatus::Unpaid,
            $refunded === 0 => PaymentStatus::Paid,
            $paid - $refunded <= 0 => PaymentStatus::Refunded,
            default => PaymentStatus::PartiallyRefunded,
        };

        $manualIneligible = $entry->eligibility_status === EligibilityStatus::Ineligible && filled($entry->eligibility_reason);
        $eligibility = match (true) {
            $manualIneligible => EligibilityStatus::Ineligible,
            $entry->registration_status === RegistrationStatus::Reserved
                && $entry->check_in_status === CheckInStatus::CheckedIn
                && in_array($payment, [PaymentStatus::Paid, PaymentStatus::NotRequired], true) => EligibilityStatus::Eligible,
            default => EligibilityStatus::Pending,
        };

        $entry->forceFill(['payment_status' => $payment, 'eligibility_status' => $eligibility])->save();
    }

    /** @return array{0: Tournament, 1: TournamentEntry} */
    private function lock(TournamentEntry $entry): array
    {
        $tournament = Tournament::whereKey($entry->tournament_id)->lockForUpdate()->firstOrFail();
        $entry = TournamentEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

        return [$tournament, $entry];
    }

    private function assertCan(User $staff, string $ability, Tournament $tournament): void
    {
        if (! $staff->can($ability, $tournament)) {
            throw DomainRuleException::make('not_assigned', 'You are not assigned to this tournament for this task.', 403);
        }
    }

    private function assertOperational(Tournament $tournament): void
    {
        if (! in_array($tournament->status, self::OPERATIONAL, true)) {
            throw DomainRuleException::make('tournament_not_operational', 'Check-in and payments are only possible while the tournament is open for registration, closed for registration, or in progress.', 409);
        }
    }

    private function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw DomainRuleException::make('reason_required', 'A reason is required.', 422);
        }
    }

    /** Human-friendly receipt numbers, sequential per tournament: RV-12-0007, RF-12-0002. */
    private function nextReference(Tournament $tournament, string $prefix): string
    {
        $sequence = EntryPayment::where('tournament_id', $tournament->id)->count() + 1;

        do {
            $reference = sprintf('%s-%d-%04d', $prefix, $tournament->id, $sequence++);
        } while (EntryPayment::where('reference', $reference)->exists());

        return $reference;
    }
}
