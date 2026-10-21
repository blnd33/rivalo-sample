<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public product shape. Exact stock counts are not published: each size shows "in stock",
 * "only N left" (3 or fewer) or "sold out".
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public const LOW_STOCK = 3;

    public function toArray(Request $request): array
    {
        $variants = $this->variants->where('is_active', true)->values();
        $prices = $variants->map(fn (ProductVariant $v) => $v->unitPrice($this->resource));

        return [
            'slug' => $this->slug,
            'name' => $this->name(),
            'description' => $this->description(),
            'category' => $this->category->value,
            // NULL = official RIVALO product. Sellers are shown by their public shop name only.
            'seller' => $this->seller_id && $this->seller ? ['slug' => $this->seller->slug, 'name' => $this->seller->name] : null,
            'sport' => $this->sport ? ['id' => $this->sport->id, 'name' => $this->sport->name, 'icon' => $this->sport->icon] : null,
            'currency' => $this->currency,
            'price_minor' => $prices->min() ?? $this->price_minor,
            'price_varies' => $prices->unique()->count() > 1,
            'is_featured' => $this->is_featured,
            'max_per_order' => $this->max_per_order,
            'images' => $this->images->map(fn ($i) => ['url' => $i->url(), 'alt' => $i->alt])->values(),
            'sold_out' => $variants->every(fn (ProductVariant $v) => $v->stock <= 0),
            'variants' => $variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'label' => $v->option_label,
                'price_minor' => $v->unitPrice($this->resource),
                'availability' => match (true) {
                    $v->stock <= 0 => 'sold_out',
                    $v->stock <= self::LOW_STOCK => 'low_stock',
                    default => 'in_stock',
                },
                'left' => $v->stock > 0 && $v->stock <= self::LOW_STOCK ? $v->stock : null,
            ]),
            'is_sample_data' => $this->is_sample_data,
        ];
    }
}
