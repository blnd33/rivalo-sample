<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Support\SellerContext;

/** Product photos: store staff, or in the Seller Hub the seller of an editable listing. */
class ProductImagePolicy
{
    public function viewAny(User $user): bool
    {
        return SellerContext::current() ? true : $user->can('store.manage');
    }

    public function view(User $user, ProductImage $image): bool
    {
        return SellerContext::current() ? $this->ownProduct($image) !== null : $user->can('store.manage');
    }

    public function create(User $user): bool
    {
        return SellerContext::current() ? SellerContext::canWrite() : $user->can('store.manage');
    }

    public function update(User $user, ProductImage $image): bool
    {
        if (SellerContext::current()) {
            return SellerContext::canWrite() && (bool) $this->ownProduct($image)?->status->sellerEditable();
        }

        return $user->can('store.manage');
    }

    public function delete(User $user, ProductImage $image): bool
    {
        return $this->update($user, $image);
    }

    private function ownProduct(ProductImage $image): ?Product
    {
        $product = Product::find($image->product_id);

        return $product && $product->seller_id === SellerContext::id() ? $product : null;
    }
}
