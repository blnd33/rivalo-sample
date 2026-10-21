<?php

namespace App\Services;

use App\Enums\PayoutMethod;
use App\Enums\SellerLedgerType;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What RIVALO owes each seller (Milestone 9), as an append-only statement.
 *
 * - Sale: posted when an order is completed (handed over and paid), per seller line:
 *   item value minus RIVALO's commission at the rate agreed when the order was placed.
 * - Return: a returned item after its sale was posted reverses that item's value and commission.
 *   Cancelled orders never post anything. A refund without a returned item does not touch the
 *   seller's statement; if the seller agreed to bear it, staff record an adjustment.
 * - Payout: money RIVALO actually paid the seller, recorded by finance staff with a reference.
 *   Nothing here moves money.
 *
 * Commission is rounded down to the currency's smallest unit (in the seller's favour).
 */
class SellerLedgerService
{
    public function __construct(private readonly Notifier $notifier) {}

    public static function commission(int $grossMinor, int $bps): int
    {
        return intdiv($grossMinor * $bps, 10000);
    }

    /** Callers hold the order lock inside a transaction (OrderService::complete). */
    public function postSales(Order $order, ?int $actorId): void
    {
        $items = OrderItem::where('order_id', $order->id)->whereNotNull('seller_id')->orderBy('id')->get();
        foreach ($items as $item) {
            $qty = $item->quantity - $item->returned_quantity;
            $key = "sale:{$item->id}";
            if ($qty <= 0 || SellerLedgerEntry::where('idempotency_key', $key)->exists()) {
                continue;
            }
            $gross = $item->unit_price_minor * $qty;
            $commission = self::commission($gross, (int) $item->commission_bps);
            SellerLedgerEntry::create([
                'seller_id' => $item->seller_id,
                'type' => SellerLedgerType::Sale,
                'currency' => $order->currency,
                'gross_minor' => $gross,
                'commission_minor' => $commission,
                'amount_minor' => $gross - $commission,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'quantity' => $qty,
                'idempotency_key' => $key,
                'created_by_id' => $actorId,
                'occurred_at' => now(),
            ]);
        }
    }

    /**
     * A returned item whose sale was already posted. Callers hold the order lock.
     * $returnedAfter is the item's returned quantity including this return (makes the key unique).
     */
    public function postReturn(OrderItem $item, int $qty, int $returnedAfter, ?int $actorId, string $note): void
    {
        if (! $item->seller_id || $qty <= 0 || ! SellerLedgerEntry::where('idempotency_key', "sale:{$item->id}")->exists()) {
            return;
        }
        $gross = $item->unit_price_minor * $qty;
        $commission = self::commission($gross, (int) $item->commission_bps);
        SellerLedgerEntry::create([
            'seller_id' => $item->seller_id,
            'type' => SellerLedgerType::Return,
            'currency' => Order::whereKey($item->order_id)->value('currency'),
            'gross_minor' => -$gross,
            'commission_minor' => -$commission,
            'amount_minor' => -($gross - $commission),
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'quantity' => $qty,
            'note' => mb_substr($note, 0, 500),
            'idempotency_key' => "return:{$item->id}:{$returnedAfter}",
            'created_by_id' => $actorId,
            'occurred_at' => now(),
        ]);
    }

    private function assertFinance(User $staff, Seller $seller): void
    {
        if (! $staff->can('finance.manage')) {
            throw DomainRuleException::make('forbidden', 'Only finance staff can record seller payouts and adjustments.', 403);
        }
        if ($seller->user_id === $staff->id) {
            throw DomainRuleException::make('conflict_of_interest', 'You cannot review or change your own shop. Ask another staff member.', 403);
        }
    }

    /** Money actually paid to the seller (cash or bank transfer), at most what is owed. */
    public function recordPayout(Seller $seller, User $staff, int $amountMinor, PayoutMethod $method, ?string $reference, Carbon $paidAt, ?string $note = null): SellerLedgerEntry
    {
        $this->assertFinance($staff, $seller);
        if ($amountMinor <= 0) {
            throw DomainRuleException::make('invalid_amount', 'Enter the amount paid.');
        }
        if ($method === PayoutMethod::BankTransfer && blank($reference)) {
            throw DomainRuleException::make('reference_required', 'Enter the bank transfer reference.');
        }
        if ($paidAt->isFuture()) {
            throw DomainRuleException::make('invalid_date', 'The payment date cannot be in the future.');
        }
        $currency = StoreService::currency();

        $entry = DB::transaction(function () use ($seller, $staff, $amountMinor, $method, $reference, $paidAt, $note, $currency) {
            Seller::whereKey($seller->id)->lockForUpdate()->first();
            $owed = (int) SellerLedgerEntry::where('seller_id', $seller->id)->where('currency', $currency)->sum('amount_minor');
            if ($amountMinor > $owed) {
                throw DomainRuleException::make('payout_exceeds_balance', 'RIVALO owes this seller :owed. A payout cannot be more than that.', 409, ['owed' => Money::format(max(0, $owed), $currency)]);
            }
            $entry = SellerLedgerEntry::create([
                'seller_id' => $seller->id,
                'type' => SellerLedgerType::Payout,
                'currency' => $currency,
                'amount_minor' => -$amountMinor,
                'payout_method' => $method,
                'reference' => filled($reference) ? mb_substr(trim($reference), 0, 120) : null,
                'note' => filled($note) ? mb_substr(trim($note), 0, 500) : null,
                'idempotency_key' => 'payout:'.Str::uuid(),
                'created_by_id' => $staff->id,
                'occurred_at' => $paidAt,
            ]);
            Audit::record('seller.payout_recorded', $seller, new: ['amount_minor' => $amountMinor, 'currency' => $currency, 'method' => $method->value, 'number' => $entry->payoutNumber()], actorId: $staff->id);

            return $entry;
        });
        $this->notifier->send([$seller->user_id], 'seller_payout_recorded', ['amount' => Money::format($amountMinor, $currency), 'number' => $entry->payoutNumber()], ['seller', null], "seller_payout:{$entry->id}");

        return $entry;
    }

    /** A correction both sides agreed (positive: RIVALO owes more; negative: the seller owes). */
    public function recordAdjustment(Seller $seller, User $staff, int $amountMinor, string $reason): SellerLedgerEntry
    {
        $this->assertFinance($staff, $seller);
        if ($amountMinor === 0) {
            throw DomainRuleException::make('invalid_amount', 'Enter an amount other than zero.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw DomainRuleException::make('reason_required', 'A reason is required.');
        }
        $currency = StoreService::currency();

        $entry = DB::transaction(function () use ($seller, $staff, $amountMinor, $reason, $currency) {
            Seller::whereKey($seller->id)->lockForUpdate()->first();
            $entry = SellerLedgerEntry::create([
                'seller_id' => $seller->id,
                'type' => SellerLedgerType::Adjustment,
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'note' => mb_substr(trim($reason), 0, 500),
                'idempotency_key' => 'adjustment:'.Str::uuid(),
                'created_by_id' => $staff->id,
                'occurred_at' => now(),
            ]);
            Audit::record('seller.adjustment_recorded', $seller, new: ['amount_minor' => $amountMinor, 'currency' => $currency], reason: $reason, actorId: $staff->id);

            return $entry;
        });
        $this->notifier->send([$seller->user_id], 'seller_statement_adjusted', ['amount' => Money::format($amountMinor, $currency), 'reason' => $entry->note], ['seller', null], "seller_adjustment:{$entry->id}");

        return $entry;
    }

    /**
     * @return array{owed: int, sales: int, commission: int, returns: int, adjustments: int, paid: int, currency: string}
     */
    public function summary(Seller $seller, ?string $currency = null): array
    {
        $currency ??= StoreService::currency();
        $rows = SellerLedgerEntry::where('seller_id', $seller->id)->where('currency', $currency)
            ->groupBy('type')
            ->selectRaw('type, SUM(gross_minor) as gross, SUM(commission_minor) as commission, SUM(amount_minor) as amount')
            ->get()->keyBy(fn ($r) => $r->type instanceof SellerLedgerType ? $r->type->value : $r->type);
        $get = fn (string $type, string $col) => (int) ($rows[$type]->{$col} ?? 0);

        return [
            'currency' => $currency,
            'sales' => $get('sale', 'gross'),
            'commission' => $get('sale', 'commission') + $get('return', 'commission'),
            'returns' => -$get('return', 'gross'),
            'adjustments' => $get('adjustment', 'amount'),
            'paid' => -$get('payout', 'amount'),
            'owed' => (int) $rows->sum(fn ($r) => (int) $r->amount),
        ];
    }
}
