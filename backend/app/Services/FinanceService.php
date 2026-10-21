<?php

namespace App\Services;

use App\Enums\ContributionRestriction;
use App\Enums\ContributionStatus;
use App\Enums\ContributionType;
use App\Enums\FinanceRulesStatus;
use App\Enums\LedgerFund;
use App\Enums\LedgerType;
use App\Enums\Permission;
use App\Enums\PrizeBasis;
use App\Enums\PrizeLabel;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\EntryPayment;
use App\Models\Sponsor;
use App\Models\SponsorContribution;
use App\Models\Tournament;
use App\Models\TournamentFinanceRule;
use App\Models\TournamentLedgerEntry;
use App\Models\TournamentPrize;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Milestone 4B: sponsors, prize tiers, draft finance rules and the append-only tournament ledger.
 *
 * Principles
 * - Every amount is integer minor units in the tournament's currency. Shares are basis points.
 * - The ledger records cash that actually moved. Nothing here transfers money automatically.
 * - Restricted money stays in its fund: prize-only cash sits in the prize fund and can never pay
 *   the organizer; expenses-only cash sits in the expense fund. Only unrestricted money can be
 *   allocated elsewhere. No fund may go below zero through a manual entry.
 * - Formula results (prize pool, organizer share) are projections while the rules are drafts
 *   (decision D4). Formula-based payouts are refused until a finance manager approves the rules.
 * - After registration opens (financial_rules_locked_at), changes need a written reason and are
 *   audited as corrections. Changing rules always returns them to draft for re-approval.
 */
class FinanceService
{
    private const BP = 10000;

    // ================================================================ Reading

    public function rules(Tournament $tournament): TournamentFinanceRule
    {
        return $tournament->financeRule()->first() ?? new TournamentFinanceRule(['tournament_id' => $tournament->id]);
    }

    /**
     * Totals by base type and fund. A reversal counts toward the type it reverses.
     *
     * @return array<string, array<string, int>> base_type => fund => signed total
     */
    public function matrix(Tournament $tournament): array
    {
        $rows = DB::table('tournament_ledger_entries as e')
            ->leftJoin('tournament_ledger_entries as o', 'o.id', '=', 'e.reverses_id')
            ->where('e.tournament_id', $tournament->id)
            ->selectRaw('COALESCE(o.type, e.type) as base_type, e.fund as fund, SUM(e.amount_minor) as total')
            ->groupByRaw('COALESCE(o.type, e.type), e.fund')
            ->get();

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row->base_type][$row->fund] = (int) $row->total;
        }

        return $matrix;
    }

    /** @return array<string, int> fund => balance */
    public function balances(Tournament $tournament): array
    {
        $balances = array_fill_keys(LedgerFund::values(), 0);
        foreach ($this->matrix($tournament) as $funds) {
            foreach ($funds as $fund => $total) {
                $balances[$fund] += $total;
            }
        }

        return $balances;
    }

    /**
     * Everything the finance page and report need, in minor units (outflows as positive numbers).
     *
     * @return array<string, mixed>
     */
    public function summary(Tournament $tournament): array
    {
        $m = $this->matrix($tournament);
        $type = fn (LedgerType $t) => array_sum($m[$t->value] ?? []);

        $grossEntry = $type(LedgerType::EntryRevenue);
        $refunds = -$type(LedgerType::Refund);
        $expenses = -array_sum(array_map($type, LedgerType::expenses()));
        $balances = $this->balances($tournament);

        $pledgedOutstanding = 0;
        foreach ($tournament->sponsorContributions()->where('type', ContributionType::Cash->value)->where('status', '!=', ContributionStatus::Cancelled->value)->get() as $c) {
            $pledgedOutstanding += max(0, $c->committed_minor - $c->receivedMinor());
        }

        return [
            'currency' => $tournament->currency,
            'gross_entry' => $grossEntry,
            'refunds' => $refunds,
            'net_entry' => $grossEntry - $refunds,
            'sponsor_cash' => $type(LedgerType::SponsorCash),
            'sponsor_cash_by_fund' => $m[LedgerType::SponsorCash->value] ?? [],
            'expenses' => $expenses,
            'organizer_paid' => -$type(LedgerType::OrganizerPayout),
            'prizes_paid' => -$type(LedgerType::PrizePayout),
            'balances' => $balances,
            'cash_on_hand' => array_sum($balances),
            'pledged_outstanding' => $pledgedOutstanding,
            'projection' => $this->projection($tournament, $grossEntry - $refunds, $m),
        ];
    }

    /**
     * Formula results from the (draft or approved) rules. Amounts are null when a share is not set.
     *
     * @return array{status: string, approved: bool, net_entry: int, prize_pool: ?int, organizer_share: ?int, prize_only_sponsor_cash: int}
     */
    public function projection(Tournament $tournament, ?int $netEntry = null, ?array $matrix = null): array
    {
        $matrix ??= $this->matrix($tournament);
        $netEntry ??= array_sum($matrix[LedgerType::EntryRevenue->value] ?? []) + array_sum($matrix[LedgerType::Refund->value] ?? []);
        $rules = $this->rules($tournament);
        $prizeSponsor = (int) ($matrix[LedgerType::SponsorCash->value][LedgerFund::Prize->value] ?? 0);
        $base = max(0, $netEntry);

        return [
            'status' => $rules->status->value,
            'approved' => $rules->isApproved(),
            'net_entry' => $netEntry,
            'prize_pool' => $rules->prize_pool_bp === null ? null : intdiv($base * $rules->prize_pool_bp, self::BP) + $prizeSponsor,
            'organizer_share' => $rules->organizer_share_bp === null ? null : intdiv($base * $rules->organizer_share_bp, self::BP),
            'prize_only_sponsor_cash' => $prizeSponsor,
        ];
    }

    /** Cash amount of a prize tier, or null when it can't be stated yet (in-kind only, or pool share under draft rules). */
    public function prizeAmount(TournamentPrize $prize, ?array $projection = null): ?int
    {
        if ($prize->basis === PrizeBasis::Fixed) {
            return $prize->amount_minor;
        }
        $projection ??= $this->projection($prize->tournament);
        if (! $projection['approved'] || $projection['prize_pool'] === null) {
            return null;
        }

        return intdiv($projection['prize_pool'] * (int) $prize->pool_share_bp, self::BP);
    }

    public function prizePaid(TournamentPrize $prize): int
    {
        return -(int) TournamentLedgerEntry::where('prize_id', $prize->id)->sum('amount_minor');
    }

    // ================================================================ Rules (D4 drafts)

    public function saveRules(Tournament $tournament, User $actor, ?int $prizePoolBp, ?int $organizerShareBp, ?string $notes = null, ?string $reason = null): TournamentFinanceRule
    {
        $this->assertCan($actor);
        foreach ([$prizePoolBp, $organizerShareBp] as $bp) {
            if ($bp !== null && ($bp < 0 || $bp > self::BP)) {
                throw DomainRuleException::make('invalid_share', 'Shares must be between 0% and 100%.', 422);
            }
        }
        if (($prizePoolBp ?? 0) + ($organizerShareBp ?? 0) > self::BP) {
            throw DomainRuleException::make('shares_over_100', 'The prize pool and organizer share together cannot exceed 100% of net entry revenue.', 422);
        }

        return DB::transaction(function () use ($tournament, $actor, $prizePoolBp, $organizerShareBp, $notes, $reason) {
            $tournament = $this->lockTournament($tournament);
            $rules = $this->rules($tournament);
            $old = ['prize_pool_bp' => $rules->prize_pool_bp, 'organizer_share_bp' => $rules->organizer_share_bp, 'status' => $rules->status?->value];

            $changed = $rules->prize_pool_bp !== $prizePoolBp || $rules->organizer_share_bp !== $organizerShareBp;
            if ($changed) {
                $this->assertReasonIfLocked($tournament, $reason);
                if ($this->hasFormulaPayouts($tournament)) {
                    throw DomainRuleException::make('payouts_exist', 'Payouts based on these rules are already recorded. Reverse them before changing the rules.', 409);
                }
            }

            $rules->fill(['prize_pool_bp' => $prizePoolBp, 'organizer_share_bp' => $organizerShareBp, 'notes' => $notes]);
            if ($changed) {
                // Any change to the numbers needs a fresh approval.
                $rules->fill(['status' => FinanceRulesStatus::Draft, 'approved_by_id' => null, 'approved_at' => null, 'approval_note' => null]);
            }
            $rules->save();

            if ($changed) {
                Audit::record(
                    $tournament->financial_rules_locked_at ? 'finance.rules_corrected' : 'finance.rules_updated',
                    $tournament,
                    $old,
                    ['prize_pool_bp' => $prizePoolBp, 'organizer_share_bp' => $organizerShareBp, 'status' => 'draft'],
                    $reason,
                    $actor->id,
                );
            }

            return $rules;
        });
    }

    public function approveRules(Tournament $tournament, User $actor, string $note): TournamentFinanceRule
    {
        $this->assertCan($actor);
        $this->assertReason($note, 'An approval note is required (for example, who decided and where it is documented).');

        return DB::transaction(function () use ($tournament, $actor, $note) {
            $tournament = $this->lockTournament($tournament);
            $rules = $this->rules($tournament);
            if (! $rules->exists || ! $rules->isComplete()) {
                throw DomainRuleException::make('rules_incomplete', 'Set both the prize pool share and the organizer share before approving.', 422);
            }
            if ($rules->isApproved()) {
                throw DomainRuleException::make('already_approved', 'These rules are already approved.', 409);
            }

            $rules->update([
                'status' => FinanceRulesStatus::Approved,
                'approved_by_id' => $actor->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ]);

            Audit::record('finance.rules_approved', $tournament, new: [
                'prize_pool_bp' => $rules->prize_pool_bp, 'organizer_share_bp' => $rules->organizer_share_bp,
            ], reason: $note, actorId: $actor->id);

            return $rules;
        });
    }

    // ================================================================ Sponsors

    public function addContribution(Tournament $tournament, Sponsor $sponsor, User $actor, array $data): SponsorContribution
    {
        $this->assertCan($actor);
        $type = ContributionType::from($data['type']);
        $restriction = isset($data['restriction']) ? ContributionRestriction::from($data['restriction']) : null;
        $committed = (int) ($data['committed_minor'] ?? 0);

        if ($type === ContributionType::Cash) {
            if ($committed <= 0) {
                throw DomainRuleException::make('invalid_amount', 'Enter the amount the sponsor committed.', 422);
            }
            if ($restriction === null) {
                throw DomainRuleException::make('restriction_required', 'Choose how this cash may be used (unrestricted, prizes only, or expenses only).', 422);
            }
        } else {
            if (blank($data['in_kind_description'] ?? null)) {
                throw DomainRuleException::make('description_required', 'Describe what the sponsor provides.', 422);
            }
            $restriction = null;
            $committed = 0;
        }

        return DB::transaction(function () use ($tournament, $sponsor, $actor, $data, $type, $restriction, $committed) {
            $tournament = $this->lockTournament($tournament);
            $contribution = SponsorContribution::create([
                'sponsor_id' => $sponsor->id,
                'tournament_id' => $tournament->id,
                'type' => $type,
                'restriction' => $restriction,
                'committed_minor' => $committed,
                'currency' => $tournament->currency,
                'in_kind_description' => $data['in_kind_description'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => ContributionStatus::Pledged,
                'created_by_id' => $actor->id,
            ]);

            Audit::record('sponsor.contribution_added', $tournament, new: [
                'sponsor' => $sponsor->name, 'type' => $type->value, 'restriction' => $restriction?->value, 'committed_minor' => $committed,
            ], actorId: $actor->id);

            return $contribution;
        });
    }

    /** Record sponsor cash actually handed over. It lands in the fund its restriction dictates. */
    public function receiveContribution(SponsorContribution $contribution, User $actor, int $amountMinor, ?string $note = null): TournamentLedgerEntry
    {
        $this->assertCan($actor);

        return DB::transaction(function () use ($contribution, $actor, $amountMinor, $note) {
            $tournament = $this->lockTournament($contribution->tournament);
            $contribution = SponsorContribution::whereKey($contribution->id)->lockForUpdate()->firstOrFail();

            if (! $contribution->isCash()) {
                throw DomainRuleException::make('not_cash', 'Only cash contributions are recorded in the money records.', 409);
            }
            if ($contribution->status === ContributionStatus::Cancelled) {
                throw DomainRuleException::make('contribution_cancelled', 'This contribution was cancelled.', 409);
            }
            $remaining = $contribution->committed_minor - $contribution->receivedMinor();
            if ($amountMinor <= 0 || $amountMinor > $remaining) {
                throw DomainRuleException::make('invalid_amount', 'The amount must be more than 0 and at most the :remaining still expected. Add a new contribution for extra money.', 422, [
                    'remaining' => Money::format(max(0, $remaining), $contribution->currency),
                ]);
            }

            $entry = $this->write($tournament, $actor, [
                'type' => LedgerType::SponsorCash,
                'fund' => $contribution->restriction->fund(),
                'amount_minor' => $amountMinor,
                'sponsor_contribution_id' => $contribution->id,
                'payee' => $contribution->sponsor->name,
                'note' => $note,
            ]);
            $this->refreshContribution($contribution);

            Audit::record('sponsor.cash_received', $tournament, new: [
                'reference' => $entry->reference, 'amount_minor' => $amountMinor, 'fund' => $entry->fund->value,
            ], reason: $note, actorId: $actor->id);

            return $entry;
        });
    }

    public function cancelContribution(SponsorContribution $contribution, User $actor, string $reason): SponsorContribution
    {
        $this->assertCan($actor);
        $this->assertReason($reason);

        return DB::transaction(function () use ($contribution, $actor, $reason) {
            $tournament = $this->lockTournament($contribution->tournament);
            $contribution = SponsorContribution::whereKey($contribution->id)->lockForUpdate()->firstOrFail();

            if ($contribution->receivedMinor() > 0) {
                throw DomainRuleException::make('cash_received', 'Cash was already received. Reverse the receipts first.', 409);
            }
            if (TournamentPrize::where('backing_contribution_id', $contribution->id)->exists()) {
                throw DomainRuleException::make('backs_prize', 'A prize is guaranteed by this contribution. Change that prize first.', 409);
            }

            $contribution->update(['status' => ContributionStatus::Cancelled, 'cancel_reason' => $reason]);
            Audit::record('sponsor.contribution_cancelled', $tournament, new: ['contribution_id' => $contribution->id], reason: $reason, actorId: $actor->id);

            return $contribution;
        });
    }

    // ================================================================ Prizes

    /** Create or update a prize tier, enforcing the label rules. */
    public function savePrize(Tournament $tournament, User $actor, array $data, ?TournamentPrize $prize = null, ?string $reason = null): TournamentPrize
    {
        $this->assertCan($actor);

        return DB::transaction(function () use ($tournament, $actor, $data, $prize, $reason) {
            $tournament = $this->lockTournament($tournament);
            $prize = $prize ? TournamentPrize::whereKey($prize->id)->lockForUpdate()->firstOrFail() : new TournamentPrize(['tournament_id' => $tournament->id]);
            $old = $prize->exists ? $this->prizeSnapshot($prize) : [];

            $basis = PrizeBasis::from($data['basis'] ?? 'fixed');
            $label = PrizeLabel::from($data['label'] ?? 'estimated');
            $prize->fill([
                'place' => (int) $data['place'],
                'title' => $data['title'] ?? null,
                'basis' => $basis,
                'amount_minor' => $basis === PrizeBasis::Fixed && filled($data['amount_minor'] ?? null) ? (int) $data['amount_minor'] : null,
                'pool_share_bp' => $basis === PrizeBasis::PoolShare ? (int) ($data['pool_share_bp'] ?? 0) : null,
                'in_kind_description' => $data['in_kind_description'] ?? null,
                'label' => $label,
                'backing_contribution_id' => $label === PrizeLabel::Guaranteed ? ($data['backing_contribution_id'] ?? null) : null,
            ]);

            $this->validatePrize($tournament, $prize);

            if ($prize->exists && $tournament->financial_rules_locked_at && ! $this->isOnlyMoreCertain($prize)) {
                $this->assertReasonIfLocked($tournament, $reason);
            }
            if ($prize->exists && $this->prizePaid($prize) > 0 && $prize->isDirty(['basis', 'amount_minor', 'pool_share_bp'])) {
                throw DomainRuleException::make('prize_paid', 'Money was already paid for this prize. Reverse the payout before changing the amount.', 409);
            }

            $isNew = ! $prize->exists;
            $dirty = $prize->getDirty();
            $prize->save();

            if ($isNew || $dirty) {
                Audit::record(
                    $isNew ? 'prize.created' : ($tournament->financial_rules_locked_at ? 'prize.corrected' : 'prize.updated'),
                    $tournament,
                    $old,
                    $this->prizeSnapshot($prize),
                    $reason,
                    $actor->id,
                );
            }

            return $prize;
        });
    }

    public function deletePrize(TournamentPrize $prize, User $actor, ?string $reason = null): void
    {
        $this->assertCan($actor);

        DB::transaction(function () use ($prize, $actor, $reason) {
            $tournament = $this->lockTournament($prize->tournament);
            $this->assertReasonIfLocked($tournament, $reason);
            if (TournamentLedgerEntry::where('prize_id', $prize->id)->exists()) {
                throw DomainRuleException::make('prize_has_payouts', 'This prize has payout records and cannot be removed.', 409);
            }
            $snapshot = $this->prizeSnapshot($prize);
            $prize->delete();
            Audit::record('prize.deleted', $tournament, $snapshot, [], $reason, $actor->id);
        });
    }

    // ================================================================ Manual ledger entries

    public function recordExpense(Tournament $tournament, User $actor, LedgerType $type, int $amountMinor, LedgerFund $fund, string $payee, string $note): TournamentLedgerEntry
    {
        $this->assertCan($actor);
        if (! in_array($type, LedgerType::expenses(), true)) {
            throw DomainRuleException::make('invalid_type', 'Choose an expense type.', 422);
        }
        $this->assertFundAllowed($fund, [LedgerFund::Expense, LedgerFund::Unrestricted], 'Expenses can be paid from the expense fund or unrestricted money only.');
        $this->assertReason($payee, 'Enter who was paid.');
        $this->assertReason($note, 'Describe the expense.');

        return $this->outflow($tournament, $actor, $type, $amountMinor, $fund, ['payee' => $payee, 'note' => $note], 'finance.expense_recorded');
    }

    /** Move unrestricted money into a purpose fund. Restricted money can never be moved. */
    public function allocate(Tournament $tournament, User $actor, LedgerFund $to, int $amountMinor, string $reason): array
    {
        $this->assertCan($actor);
        $this->assertReason($reason);
        $this->assertFundAllowed($to, [LedgerFund::Prize, LedgerFund::Expense, LedgerFund::Organizer], 'Unrestricted money can be set aside for prizes, expenses or the organizer.');
        $this->assertPositive($amountMinor);

        return DB::transaction(function () use ($tournament, $actor, $to, $amountMinor, $reason) {
            $tournament = $this->lockTournament($tournament);
            $this->assertBalance($tournament, LedgerFund::Unrestricted, $amountMinor);
            $group = (string) Str::uuid();

            $out = $this->write($tournament, $actor, ['type' => LedgerType::Allocation, 'fund' => LedgerFund::Unrestricted, 'amount_minor' => -$amountMinor, 'transfer_group' => $group, 'note' => $reason]);
            $in = $this->write($tournament, $actor, ['type' => LedgerType::Allocation, 'fund' => $to, 'amount_minor' => $amountMinor, 'transfer_group' => $group, 'note' => $reason]);

            Audit::record('finance.allocated', $tournament, new: ['to' => $to->value, 'amount_minor' => $amountMinor, 'references' => [$out->reference, $in->reference]], reason: $reason, actorId: $actor->id);

            return [$out, $in];
        });
    }

    /** Organizer's share actually paid out. Needs approved rules; never from prize or expense money. */
    public function recordOrganizerPayout(Tournament $tournament, User $actor, int $amountMinor, LedgerFund $fund, string $payee, ?string $note = null): TournamentLedgerEntry
    {
        $this->assertCan($actor);
        $this->assertFundAllowed($fund, [LedgerFund::Organizer, LedgerFund::Unrestricted], 'The organizer can only be paid from organizer or unrestricted money. Prize-only and expense money can never fund the organizer.');
        $this->assertReason($payee, 'Enter who was paid.');

        return DB::transaction(function () use ($tournament, $actor, $amountMinor, $fund, $payee, $note) {
            $tournament = $this->lockTournament($tournament);
            $projection = $this->projection($tournament);
            if (! $projection['approved']) {
                throw DomainRuleException::make('rules_not_approved', 'The finance rules are still a draft. Approve them before paying the organizer share.', 409);
            }
            $paid = -array_sum($this->matrix($tournament)[LedgerType::OrganizerPayout->value] ?? []);
            $remaining = (int) $projection['organizer_share'] - $paid;
            if ($amountMinor > $remaining) {
                throw DomainRuleException::make('over_share', 'This is more than the organizer share still due (:remaining).', 422, ['remaining' => Money::format(max(0, $remaining), $tournament->currency)]);
            }

            return $this->outflow($tournament, $actor, LedgerType::OrganizerPayout, $amountMinor, $fund, ['payee' => $payee, 'note' => $note], 'finance.organizer_paid', locked: true);
        });
    }

    /** A prize actually handed to a winner. Needs a completed tournament and a finalized/guaranteed prize. */
    public function recordPrizePayout(TournamentPrize $prize, User $actor, int $amountMinor, LedgerFund $fund, string $payee, ?string $note = null): TournamentLedgerEntry
    {
        $this->assertCan($actor);
        $this->assertFundAllowed($fund, [LedgerFund::Prize, LedgerFund::Unrestricted], 'Prizes can be paid from the prize fund or unrestricted money only.');
        $this->assertReason($payee, 'Enter the winner who received the prize.');

        return DB::transaction(function () use ($prize, $actor, $amountMinor, $fund, $payee, $note) {
            $tournament = $this->lockTournament($prize->tournament);
            $prize = TournamentPrize::whereKey($prize->id)->lockForUpdate()->firstOrFail();

            if ($tournament->status !== TournamentStatus::Completed) {
                throw DomainRuleException::make('not_completed', 'Prizes are paid after the tournament is completed.', 409);
            }
            if ($prize->label === PrizeLabel::Estimated) {
                throw DomainRuleException::make('prize_estimated', 'This prize is still an estimate. Finalize it before paying.', 409);
            }
            $amount = $this->prizeAmount($prize);
            if ($amount === null) {
                throw DomainRuleException::make('no_cash_amount', 'This prize has no cash amount to pay.', 409);
            }
            $remaining = $amount - $this->prizePaid($prize);
            if ($amountMinor > $remaining) {
                throw DomainRuleException::make('over_prize', 'This is more than the prize amount still due (:remaining).', 422, ['remaining' => Money::format(max(0, $remaining), $tournament->currency)]);
            }

            return $this->outflow($tournament, $actor, LedgerType::PrizePayout, $amountMinor, $fund, ['payee' => $payee, 'note' => $note, 'prize_id' => $prize->id], 'finance.prize_paid', locked: true);
        });
    }

    /** Correct a manual entry by recording its exact opposite. Allocations reverse as a pair. */
    public function reverse(TournamentLedgerEntry $entry, User $actor, string $reason): array
    {
        $this->assertCan($actor);
        $this->assertReason($reason);

        return DB::transaction(function () use ($entry, $actor, $reason) {
            $tournament = $this->lockTournament($entry->tournament);
            $entry = TournamentLedgerEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->type->isAutomatic()) {
                throw DomainRuleException::make('automatic_entry', 'Entry fees and refunds follow the venue receipts. Void the receipt at the check-in desk instead.', 409);
            }
            if ($entry->type === LedgerType::Reversal) {
                throw DomainRuleException::make('is_reversal', 'A reversal cannot be reversed. Record a new entry instead.', 409);
            }

            $targets = $entry->transfer_group
                ? TournamentLedgerEntry::where('transfer_group', $entry->transfer_group)->lockForUpdate()->get()
                : collect([$entry]);

            foreach ($targets as $target) {
                if (TournamentLedgerEntry::where('reverses_id', $target->id)->exists()) {
                    throw DomainRuleException::make('already_reversed', 'This entry was already reversed.', 409);
                }
                // Taking money back out of a fund must not drive it below zero.
                if ($target->amount_minor > 0) {
                    $this->assertBalance($tournament, $target->fund, $target->amount_minor, 'This money has already been used. Reverse the later entries first.');
                }
            }

            $reversals = [];
            foreach ($targets as $target) {
                $reversals[] = $this->write($tournament, $actor, [
                    'type' => LedgerType::Reversal,
                    'fund' => $target->fund,
                    'amount_minor' => -$target->amount_minor,
                    'reverses_id' => $target->id,
                    'sponsor_contribution_id' => $target->sponsor_contribution_id,
                    'prize_id' => $target->prize_id,
                    'transfer_group' => $target->transfer_group,
                    'payee' => $target->payee,
                    'note' => $reason,
                ]);
            }

            if ($entry->sponsor_contribution_id) {
                $contribution = SponsorContribution::whereKey($entry->sponsor_contribution_id)->lockForUpdate()->firstOrFail();
                $this->assertGuaranteesStillBacked($contribution);
                $this->refreshContribution($contribution);
            }

            Audit::record('finance.reversed', $tournament, ['reference' => $entry->reference], ['reversals' => array_map(fn ($r) => $r->reference, $reversals)], $reason, $actor->id);

            return $reversals;
        });
    }

    // ================================================================ Venue receipts (called inside VenueService transactions)

    /** Mirror a venue payment or refund into the ledger. Idempotent: one ledger row per receipt. */
    public function syncEntryPayment(EntryPayment $payment, ?User $actor = null): TournamentLedgerEntry
    {
        if ($existing = TournamentLedgerEntry::where('entry_payment_id', $payment->id)->first()) {
            return $existing;
        }
        $tournament = Tournament::findOrFail($payment->tournament_id);

        return $this->write($tournament, $actor, [
            'type' => $payment->isRefund() ? LedgerType::Refund : LedgerType::EntryRevenue,
            'fund' => LedgerFund::Unrestricted,
            'amount_minor' => $payment->isRefund() ? -$payment->amount_minor : $payment->amount_minor,
            'entry_payment_id' => $payment->id,
            'note' => __('Receipt :reference', ['reference' => $payment->reference]),
        ], recordedAt: $payment->recorded_at);
    }

    /** A voided receipt is reversed in the ledger (the original row stays). */
    public function syncVoidedPayment(EntryPayment $payment, User $actor): ?TournamentLedgerEntry
    {
        $original = TournamentLedgerEntry::where('entry_payment_id', $payment->id)->first();
        if (! $original || TournamentLedgerEntry::where('reverses_id', $original->id)->exists()) {
            return null;
        }
        $tournament = Tournament::findOrFail($payment->tournament_id);

        return $this->write($tournament, $actor, [
            'type' => LedgerType::Reversal,
            'fund' => $original->fund,
            'amount_minor' => -$original->amount_minor,
            'reverses_id' => $original->id,
            'note' => __('Receipt :reference voided: :reason', ['reference' => $payment->reference, 'reason' => $payment->void_reason]),
        ]);
    }

    // ================================================================ Internals

    private function outflow(Tournament $tournament, User $actor, LedgerType $type, int $amountMinor, LedgerFund $fund, array $extra, string $auditAction, bool $locked = false): TournamentLedgerEntry
    {
        $this->assertPositive($amountMinor);

        $run = function () use ($tournament, $actor, $type, $amountMinor, $fund, $extra, $auditAction, $locked) {
            $tournament = $locked ? $tournament : $this->lockTournament($tournament);
            $this->assertBalance($tournament, $fund, $amountMinor);
            $entry = $this->write($tournament, $actor, array_merge($extra, ['type' => $type, 'fund' => $fund, 'amount_minor' => -$amountMinor]));
            Audit::record($auditAction, $tournament, new: [
                'reference' => $entry->reference, 'amount_minor' => $amountMinor, 'fund' => $fund->value, 'payee' => $extra['payee'] ?? null,
            ], reason: $extra['note'] ?? null, actorId: $actor->id);

            return $entry;
        };

        return $locked ? $run() : DB::transaction($run);
    }

    private function write(Tournament $tournament, ?User $actor, array $data, $recordedAt = null): TournamentLedgerEntry
    {
        return TournamentLedgerEntry::create(array_merge([
            'tournament_id' => $tournament->id,
            'currency' => $tournament->currency,
            'reference' => $this->nextReference($tournament),
            'recorded_by_id' => $actor?->id,
            'recorded_at' => $recordedAt ?? now(),
        ], $data));
    }

    private function nextReference(Tournament $tournament): string
    {
        $sequence = TournamentLedgerEntry::where('tournament_id', $tournament->id)->count() + 1;
        do {
            $reference = sprintf('LG-%d-%04d', $tournament->id, $sequence++);
        } while (TournamentLedgerEntry::where('reference', $reference)->exists());

        return $reference;
    }

    private function lockTournament(Tournament $tournament): Tournament
    {
        return Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();
    }

    private function refreshContribution(SponsorContribution $contribution): void
    {
        if ($contribution->status === ContributionStatus::Cancelled) {
            return;
        }
        $received = $contribution->receivedMinor();
        $contribution->update(['status' => match (true) {
            $received >= $contribution->committed_minor && $received > 0 => ContributionStatus::Received,
            $received > 0 => ContributionStatus::PartiallyReceived,
            default => ContributionStatus::Pledged,
        }]);
    }

    private function validatePrize(Tournament $tournament, TournamentPrize $prize): void
    {
        if ($prize->place < 1 || $prize->place > 64) {
            throw DomainRuleException::make('invalid_place', 'Place must be between 1 and 64.', 422);
        }
        if (TournamentPrize::where('tournament_id', $tournament->id)->where('place', $prize->place)->whereKeyNot($prize->id)->exists()) {
            throw DomainRuleException::make('place_taken', 'There is already a prize for this place.', 422);
        }

        if ($prize->basis === PrizeBasis::Fixed) {
            if ($prize->amount_minor === null && blank($prize->in_kind_description)) {
                throw DomainRuleException::make('prize_empty', 'Enter a cash amount, an item (trophy, medals…), or both.', 422);
            }
            if ($prize->amount_minor !== null && $prize->amount_minor <= 0) {
                throw DomainRuleException::make('invalid_amount', 'The cash amount must be more than 0.', 422);
            }
        } else {
            if ($prize->pool_share_bp < 1 || $prize->pool_share_bp > self::BP) {
                throw DomainRuleException::make('invalid_share', 'Shares must be between 0% and 100%.', 422);
            }
            $others = (int) TournamentPrize::where('tournament_id', $tournament->id)->where('basis', PrizeBasis::PoolShare->value)->whereKeyNot($prize->id)->sum('pool_share_bp');
            if ($others + $prize->pool_share_bp > self::BP) {
                throw DomainRuleException::make('pool_over_100', 'Prize shares together cannot exceed 100% of the prize pool.', 422);
            }
        }

        if ($prize->label === PrizeLabel::Finalized && $prize->basis === PrizeBasis::PoolShare) {
            $rules = $this->rules($tournament);
            $closed = in_array($tournament->status, [TournamentStatus::RegistrationClosed, TournamentStatus::InProgress, TournamentStatus::Completed], true);
            if (! $rules->isApproved() || ! $closed) {
                throw DomainRuleException::make('cannot_finalize', 'A share of the prize pool can only be finalized after the finance rules are approved and registration has closed.', 409);
            }
        }

        if ($prize->label === PrizeLabel::Guaranteed) {
            if ($prize->basis !== PrizeBasis::Fixed || ! $prize->amount_minor) {
                throw DomainRuleException::make('guarantee_needs_amount', 'Only a fixed cash amount can be guaranteed.', 422);
            }
            $backing = $prize->backing_contribution_id ? SponsorContribution::find($prize->backing_contribution_id) : null;
            if (! $backing || $backing->tournament_id !== $tournament->id || ! $backing->isCash()
                || $backing->status === ContributionStatus::Cancelled
                || ! in_array($backing->restriction, [ContributionRestriction::PrizeOnly, ContributionRestriction::Unrestricted], true)) {
                throw DomainRuleException::make('guarantee_needs_backing', 'A guaranteed prize must be backed by a cash sponsor contribution for this tournament that may be used for prizes.', 422);
            }
            $alreadyBacked = (int) TournamentPrize::where('backing_contribution_id', $backing->id)->whereKeyNot($prize->id)->sum('amount_minor');
            if ($alreadyBacked + $prize->amount_minor > $backing->receivedMinor()) {
                throw DomainRuleException::make('guarantee_not_received', 'Guaranteed prizes can only be promised from sponsor cash already received (:received received).', 422, [
                    'received' => Money::format($backing->receivedMinor(), $backing->currency),
                ]);
            }
        }
    }

    private function assertGuaranteesStillBacked(SponsorContribution $contribution): void
    {
        $guaranteed = (int) TournamentPrize::where('backing_contribution_id', $contribution->id)->sum('amount_minor');
        if ($guaranteed > $contribution->receivedMinor()) {
            throw DomainRuleException::make('guarantee_would_break', 'Guaranteed prizes depend on this money. Change those prizes first.', 409);
        }
    }

    /** True when only the label moved toward more certainty (estimated → finalized/guaranteed). */
    private function isOnlyMoreCertain(TournamentPrize $prize): bool
    {
        $dirty = array_keys($prize->getDirty());
        if (array_diff($dirty, ['label', 'backing_contribution_id'])) {
            return false;
        }
        $rank = ['estimated' => 0, 'finalized' => 1, 'guaranteed' => 2];
        $old = $prize->getOriginal('label');
        $old = $old instanceof PrizeLabel ? $old->value : (string) $old;

        return $rank[$prize->label->value] >= ($rank[$old] ?? 0);
    }

    private function hasFormulaPayouts(Tournament $tournament): bool
    {
        $m = $this->matrix($tournament);
        if (array_sum($m[LedgerType::OrganizerPayout->value] ?? []) !== 0) {
            return true;
        }
        $poolPrizes = TournamentPrize::where('tournament_id', $tournament->id)->where('basis', PrizeBasis::PoolShare->value)->pluck('id');

        return $poolPrizes->isNotEmpty() && (int) TournamentLedgerEntry::whereIn('prize_id', $poolPrizes)->sum('amount_minor') !== 0;
    }

    private function prizeSnapshot(TournamentPrize $prize): array
    {
        return [
            'place' => $prize->place,
            'basis' => $prize->basis?->value,
            'amount_minor' => $prize->amount_minor,
            'pool_share_bp' => $prize->pool_share_bp,
            'in_kind_description' => $prize->in_kind_description,
            'label' => $prize->label?->value,
            'backing_contribution_id' => $prize->backing_contribution_id,
        ];
    }

    private function assertBalance(Tournament $tournament, LedgerFund $fund, int $amountMinor, ?string $message = null): void
    {
        $balance = $this->balances($tournament)[$fund->value];
        if ($balance < $amountMinor) {
            throw DomainRuleException::make('insufficient_funds', $message ?? 'Not enough money in the :fund fund (:balance available).', 409, [
                'fund' => $fund->label(),
                'balance' => Money::format($balance, $tournament->currency),
            ]);
        }
    }

    private function assertFundAllowed(LedgerFund $fund, array $allowed, string $message): void
    {
        if (! in_array($fund, $allowed, true)) {
            throw DomainRuleException::make('fund_not_allowed', $message, 422);
        }
    }

    private function assertPositive(int $amountMinor): void
    {
        if ($amountMinor <= 0) {
            throw DomainRuleException::make('invalid_amount', 'The amount must be more than 0.', 422);
        }
    }

    private function assertReasonIfLocked(Tournament $tournament, ?string $reason): void
    {
        if ($tournament->financial_rules_locked_at && blank($reason)) {
            throw DomainRuleException::make('reason_required', 'Financial rules are locked because registration has opened. A reason is required for this correction.', 422);
        }
    }

    private function assertReason(?string $value, string $message = 'A reason is required.'): void
    {
        if (blank($value)) {
            throw DomainRuleException::make('reason_required', $message, 422);
        }
    }

    private function assertCan(User $actor): void
    {
        if (! $actor->can(Permission::FinanceManage->value)) {
            throw DomainRuleException::make('forbidden', 'Only finance managers can change tournament money records.', 403);
        }
    }
}
