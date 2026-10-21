<?php

namespace App\Models;

use App\Enums\SellerStatus;
use App\Services\StoreService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * An approved outside seller (Milestone 9). Public: name, bio, logo. Private (staff and the
 * seller only): contact details, commission, agreement and verification notes, balance.
 */
#[Fillable([
    'user_id', 'slug', 'name', 'bio_translations', 'logo_path', 'contact_phone', 'contact_email', 'business_address',
    'status', 'status_reason', 'commission_bps', 'agreement_reference', 'verification_note', 'approved_by_id', 'approved_at',
])]
#[Hidden(['contact_phone', 'contact_email', 'business_address', 'commission_bps', 'agreement_reference', 'verification_note', 'status_reason'])]
class Seller extends Model
{
    protected $attributes = ['status' => 'active', 'is_sample_data' => false];

    protected function casts(): array
    {
        return [
            'bio_translations' => 'array',
            'status' => SellerStatus::class,
            'commission_bps' => 'integer',
            'approved_at' => 'datetime',
            'is_sample_data' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(SellerLedgerEntry::class);
    }

    public function isActive(): bool
    {
        return $this->status === SellerStatus::Active;
    }

    public function bio(?string $locale = null): ?string
    {
        $t = $this->bio_translations ?? [];
        $l = $locale ?? app()->getLocale();

        return filled($t[$l] ?? null) ? $t[$l] : ($t['en'] ?? null);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** "12.5 %" */
    public function commissionLabel(): string
    {
        return rtrim(rtrim(number_format($this->commission_bps / 100, 2, '.', ''), '0'), '.').' %';
    }

    /** What RIVALO owes the seller, in the store currency (negative = the seller owes RIVALO). */
    public function balance(?string $currency = null): int
    {
        return (int) $this->ledger()->where('currency', $currency ?? StoreService::currency())->sum('amount_minor');
    }
}
