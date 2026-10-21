<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A size or option of a product. `stock` is the running balance of inventory_movements and is
 * only changed by StoreService inside a transaction with a row lock (never edited directly).
 */
#[Fillable(['product_id', 'sku', 'option_label', 'price_minor', 'is_active', 'sort_order'])]
class ProductVariant extends Model
{
    protected $attributes = ['stock' => 0, 'is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['stock' => 'integer', 'price_minor' => 'integer', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function unitPrice(Product $product): int
    {
        return $this->price_minor ?? $product->price_minor;
    }

    public function isPurchasable(Product $product): bool
    {
        return $this->is_active && $product->isOnSale() && $this->stock > 0;
    }
}
