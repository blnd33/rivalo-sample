<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Policy page (Terms, Privacy, Community guidelines). Staff edit a draft here; players only ever
 * see a published version (page_versions). A legal review is recorded per version by staff, from
 * what a qualified reviewer told them. The software never assumes legal approval.
 */
#[Fillable([
    'slug', 'title_translations', 'body_translations', 'requires_legal_review', 'current_version', 'published_at',
    'legal_reviewed_version', 'legal_reviewed_at', 'legal_reviewer', 'legal_review_note', 'legal_recorded_by_id', 'updated_by_id',
])]
class Page extends Model
{
    public const TERMS = 'terms';

    public const PRIVACY = 'privacy';

    public const GUIDELINES = 'community-guidelines';

    /** Milestone 9: terms for approved outside sellers (needs legal review, decisions D6 and D22). */
    public const SELLER_TERMS = 'seller-terms';

    public const LOCALES = ['en', 'ar', 'kmr'];

    protected $attributes = ['current_version' => 0, 'requires_legal_review' => true];

    protected function casts(): array
    {
        return [
            'title_translations' => 'array',
            'body_translations' => 'array',
            'requires_legal_review' => 'boolean',
            'current_version' => 'integer',
            'legal_reviewed_version' => 'integer',
            'published_at' => 'datetime',
            'legal_reviewed_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(PageVersion::class)->ofMany('version', 'max');
    }

    public function legalRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'legal_recorded_by_id');
    }

    public function isPublished(): bool
    {
        return $this->current_version > 0;
    }

    /** The version players see now was reviewed (publishing a new version needs a new review). */
    public function legallyReviewed(): bool
    {
        return $this->isPublished() && $this->legal_reviewed_version === $this->current_version;
    }

    /** "pending" | "reviewed" | "not_required" */
    public function legalStatus(): string
    {
        if (! $this->requires_legal_review) {
            return 'not_required';
        }

        return $this->legallyReviewed() ? 'reviewed' : 'pending';
    }

    /** The draft differs from what players see. */
    public function hasUnpublishedChanges(): bool
    {
        $live = $this->currentVersion;

        return ! $live
            || $live->title_translations != $this->title_translations
            || $live->body_translations != $this->body_translations;
    }

    public function title(?string $locale = null): string
    {
        $t = $this->title_translations ?? [];

        return filled($t[$locale ?? app()->getLocale()] ?? null) ? $t[$locale ?? app()->getLocale()] : ($t['en'] ?? $this->slug);
    }
}
