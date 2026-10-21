<?php

namespace App\Services;

use App\Enums\InventoryReason;
use App\Exceptions\DomainRuleException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Stock. Every change is an append-only movement; the variant's `stock` column is the running
 * balance, changed only here, under a row lock, in the same transaction as the movement.
 */
class InventoryService
{
    /** Callers must hold a lock on $variant (lockForUpdate) inside a transaction. */
    public function move(ProductVariant $variant, int $delta, InventoryReason $reason, ?int $orderId = null, ?string $note = null, ?int $actorId = null): InventoryMovement
    {
        if ($delta === 0) {
            throw DomainRuleException::make('invalid_quantity', 'The quantity must not be zero.');
        }
        $balance = $variant->stock + $delta;
        if ($balance < 0) {
            throw DomainRuleException::make('insufficient_stock', 'Only :count left.', 409, ['count' => max(0, $variant->stock)]);
        }

        $movement = InventoryMovement::create([
            'product_variant_id' => $variant->id,
            'quantity_delta' => $delta,
            'reason' => $reason,
            'order_id' => $orderId,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'created_by_id' => $actorId,
        ]);
        $variant->forceFill(['stock' => $balance])->save();

        return $movement;
    }

    public function restock(ProductVariant $variant, User $staff, int $quantity, ?string $note = null): ProductVariant
    {
        if ($quantity < 1 || $quantity > 100000) {
            throw DomainRuleException::make('invalid_quantity', 'Enter how many items arrived (1 or more).');
        }

        return DB::transaction(function () use ($variant, $staff, $quantity, $note) {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $this->move($locked, $quantity, InventoryReason::Restock, note: $note, actorId: $staff->id);
            Audit::record('inventory.restocked', $locked, new: ['quantity' => $quantity, 'stock' => $locked->stock], reason: $note, actorId: $staff->id);

            return $locked;
        });
    }

    /** Stock count correction (damaged, lost, miscounted). A reason is required. */
    public function setCount(ProductVariant $variant, User $staff, int $counted, string $reason): ProductVariant
    {
        if ($counted < 0) {
            throw DomainRuleException::make('invalid_quantity', 'The counted stock cannot be negative.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw DomainRuleException::make('reason_required', 'A reason is required.');
        }

        return DB::transaction(function () use ($variant, $staff, $counted, $reason) {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $before = $locked->stock;
            if ($counted === $before) {
                throw DomainRuleException::make('stock_unchanged', 'The stock is already :count.', 422, ['count' => $before]);
            }
            $this->move($locked, $counted - $before, InventoryReason::Adjustment, note: $reason, actorId: $staff->id);
            Audit::record('inventory.adjusted', $locked, ['stock' => $before], ['stock' => $counted], $reason, $staff->id);

            return $locked;
        });
    }

    /** Unsold stock handed back to its seller (Milestone 9). Official products have no seller to return to. */
    public function returnToSeller(ProductVariant $variant, User $staff, int $quantity, ?string $note = null): ProductVariant
    {
        if ($quantity < 1) {
            throw DomainRuleException::make('invalid_quantity', 'Enter how many items were handed back (1 or more).');
        }

        return DB::transaction(function () use ($variant, $staff, $quantity, $note) {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            if (Product::whereKey($locked->product_id)->value('seller_id') === null) {
                throw DomainRuleException::make('not_a_listing', 'Only seller products can be returned to a seller.');
            }
            $this->move($locked, -$quantity, InventoryReason::ReturnedToSeller, note: $note, actorId: $staff->id);
            Audit::record('inventory.returned_to_seller', $locked, new: ['quantity' => $quantity, 'stock' => $locked->stock], reason: $note, actorId: $staff->id);

            return $locked;
        });
    }

    /** Sum of the ledger, for consistency checks. */
    public function ledgerBalance(ProductVariant $variant): int
    {
        return (int) InventoryMovement::where('product_variant_id', $variant->id)->sum('quantity_delta');
    }
}
