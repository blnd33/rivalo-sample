<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\SellerContext;

/**
 * Store staff manage the catalog. A product that was ever ordered is archived, never deleted.
 * In the Seller Hub (Milestone 9) a seller sees only their own listings and edits them only
 * while they are drafts or sent back for changes; going on sale needs staff approval.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return SellerContext::current() ? true : $user->can('store.manage');
    }

    public function view(User $user, Product $product): bool
    {
        if ($seller = SellerContext::current()) {
            return $product->seller_id === $seller->id;
        }

        return $user->can('store.manage');
    }

    public function create(User $user): bool
    {
        return SellerContext::current() ? SellerContext::canWrite() : $user->can('store.manage');
    }

    public function update(User $user, Product $product): bool
    {
        if ($seller = SellerContext::current()) {
            return SellerContext::canWrite() && $product->seller_id === $seller->id && $product->status->sellerEditable();
        }

        return $user->can('store.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        $neverOrdered = fn () => ! OrderItem::whereIn('product_variant_id', $product->variants()->select('id'))->exists();
        if (SellerContext::current()) {
            return $this->update($user, $product) && $neverOrdered();
        }

        return $user->can('store.manage') && $neverOrdered();
    }
}
