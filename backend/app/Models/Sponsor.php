<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A sponsor organisation. Contact fields are private (admin only). The sponsor appears in the app
 * only when is_public is on (their consent to be shown) and the sponsor is active.
 */
#[Fillable([
    'name', 'slug', 'logo_path', 'website_url', 'description', 'contact_name', 'contact_email',
    'contact_phone', 'is_public', 'status',
])]
class Sponsor extends Model
{
    protected $attributes = [
        'status' => 'active',
        'is_public' => false,
        'is_sample_data' => false,
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'is_sample_data' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Sponsor $sponsor) {
            if (blank($sponsor->slug)) {
                $base = Str::slug($sponsor->name) ?: 'sponsor';
                $slug = $base;
                $n = 2;
                while (static::where('slug', $slug)->whereKeyNot($sponsor->getKey())->exists()) {
                    $slug = $base.'-'.$n++;
                }
                $sponsor->slug = $slug;
            }
        });
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SponsorContribution::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function isShownPublicly(): bool
    {
        return $this->is_public && $this->status === 'active';
    }
}
