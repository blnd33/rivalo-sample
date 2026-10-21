<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_variant_id', 'product_name', 'option_label', 'sku', 'unit_price_minor', 'quantity', 'line_total_minor', 'returned_quantity', 'seller_id', 'commission_bps'])]
class OrderItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['unit_price_minor' => 'integer', 'quantity' => 'integer', 'line_total_minor' => 'integer', 'returned_quantity' => 'integer', 'commission_bps' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The seller who sold this line (NULL = official RIVALO product), as at checkout. */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
