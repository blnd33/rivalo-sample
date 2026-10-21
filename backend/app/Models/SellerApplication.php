<?php

namespace App\Models;

use App\Enums\ProductCategory;
use App\Enums\SellerApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A player's request to sell on RIVALO. Private to the applicant and staff. */
#[Fillable([
    'user_id', 'business_name', 'contact_phone', 'city', 'categories', 'description', 'link', 'status', 'decision_note',
    'reviewed_by_id', 'reviewed_at', 'seller_id',
])]
class SellerApplication extends Model
{
    protected $attributes = ['status' => 'pending', 'is_sample_data' => false];

    protected function casts(): array
    {
        return ['status' => SellerApplicationStatus::class, 'reviewed_at' => 'datetime', 'is_sample_data' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return list<string> */
    public function categoryList(): array
    {
        return array_values(array_filter(explode(',', (string) $this->categories), fn ($c) => ProductCategory::tryFrom($c) !== null));
    }
}
