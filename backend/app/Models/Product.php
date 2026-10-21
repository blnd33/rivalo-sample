<?php

namespace App\Models;

use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Support\FeatureFlags;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A product in the store: official RIVALO merchandise (seller_id NULL) or an approved seller's
 * listing (Milestone 9). Sizes and options are variants, each with its own stock held by RIVALO.
 */
#[Fillable([
    'seller_id', 'slug', 'name_translations', 'description_translations', 'category', 'sport_id', 'price_minor', 'currency',
    'status', 'is_featured', 'sort_order', 'max_per_order', 'review_note', 'submitted_at', 'reviewed_at', 'reviewed_by_id',
])]
class Product extends Model
{
    protected $attributes = ['status' => 'draft', 'is_featured' => false, 'sort_order' => 0, 'max_per_order' => 5, 'is_sample_data' => false];

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'description_translations' => 'array',
            'category' => ProductCategory::class,
            'status' => ProductStatus::class,
            'price_minor' => 'integer',
            'is_featured' => 'boolean',
            'is_sample_data' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function isOfficial(): bool
    {
        return $this->seller_id === null;
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Active, and either official or from an active seller while the marketplace is switched on. */
    public function scopeOnSale(Builder $q): Builder
    {
        return $q->where('products.status', ProductStatus::Active->value)
            ->where(fn (Builder $w) => FeatureFlags::enabled(FeatureFlags::MARKETPLACE)
                ? $w->whereNull('products.seller_id')->orWhereIn('products.seller_id', Seller::select('id')->where('status', SellerStatus::Active->value))
                : $w->whereNull('products.seller_id'));
    }

    public function isOnSale(): bool
    {
        if ($this->status !== ProductStatus::Active) {
            return false;
        }
        if ($this->seller_id === null) {
            return true;
        }
        if (! FeatureFlags::enabled(FeatureFlags::MARKETPLACE)) {
            return false;
        }
        // An explicit query when the seller isn't loaded (not a lazy load).
        $seller = $this->relationLoaded('seller') ? $this->seller : Seller::find($this->seller_id);

        return (bool) $seller?->isActive();
    }

    /** Name in the request language, falling back to English. */
    public function name(?string $locale = null): string
    {
        $t = $this->name_translations ?? [];

        return (string) (filled($t[$locale ?? app()->getLocale()] ?? null) ? $t[$locale ?? app()->getLocale()] : ($t['en'] ?? $this->slug));
    }

    public function description(?string $locale = null): ?string
    {
        $t = $this->description_translations ?? [];

        return filled($t[$locale ?? app()->getLocale()] ?? null) ? $t[$locale ?? app()->getLocale()] : ($t['en'] ?? null);
    }
}
