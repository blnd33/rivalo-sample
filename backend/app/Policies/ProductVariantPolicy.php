<?php

namespace App\Policies;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\SellerContext;

/**
 * Sizes/options. Stock changes only through restock or count actions (InventoryService), which
 * are staff-only: sellers bring their stock to RIVALO and staff record it when it arrives.
 */
class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return SellerContext::current() ? true : $user->can('store.manage');
    }

    public function view(User $user, ProductVariant $productVariant): bool
    {
        return SellerContext::current() ? $this->ownProduct($productVariant) !== null : $user->can('store.manage');
    }

    /** In the Seller Hub the sizes table also checks that its listing is editable. */
    public function create(User $user): bool
    {
        return SellerContext::current() ? SellerContext::canWrite() : $user->can('store.manage');
    }

    public function update(User $user, ProductVariant $productVariant): bool
    {
        if (SellerContext::current()) {
            return SellerContext::canWrite() && (bool) $this->ownProduct($productVariant)?->status->sellerEditable();
        }

        return $user->can('store.manage');
    }

    public function delete(User $user, ProductVariant $productVariant): bool
    {
        $noHistory = ! InventoryMovement::where('product_variant_id', $productVariant->id)->exists();

        return $this->update($user, $productVariant) && $noHistory;
    }

    private function ownProduct(ProductVariant $variant): ?Product
    {
        $product = Product::find($variant->product_id);

        return $product && $product->seller_id === SellerContext::id() ? $product : null;
    }
}
