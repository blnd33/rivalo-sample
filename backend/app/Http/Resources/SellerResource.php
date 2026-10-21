<?php

namespace App\Http\Resources;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shop shape. Contact details, commission, agreement and balance are never included.
 *
 * @mixin Seller
 */
class SellerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'bio' => $this->bio(),
            'logo_url' => $this->logoUrl(),
            'product_count' => $this->whenCounted('products'),
            'is_sample_data' => $this->is_sample_data,
        ];
    }
}
