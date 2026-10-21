<?php

namespace App\Models;

use App\Enums\NewsCategory;
use App\Enums\NewsStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'slug', 'title', 'summary', 'body', 'cover_path', 'category', 'locale', 'sport_id',
    'tournament_id', 'is_featured', 'status', 'published_at',
])]
class NewsArticle extends Model
{
    protected $attributes = [
        'category' => 'announcement',
        'status' => 'draft',
        'is_featured' => false,
        'is_sample_data' => false,
    ];

    protected function casts(): array
    {
        return [
            'category' => NewsCategory::class,
            'status' => NewsStatus::class,
            'is_featured' => 'boolean',
            'is_sample_data' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (NewsArticle $article) {
            if (blank($article->slug)) {
                $base = Str::slug($article->title) ?: 'article';
                $slug = $base;
                $n = 2;
                while (static::where('slug', $slug)->whereKeyNot($article->getKey())->exists()) {
                    $slug = $base.'-'.$n++;
                }
                $article->slug = $slug;
            }
            // Publishing without a date publishes now; drafts keep whatever was scheduled.
            if ($article->status === NewsStatus::Published && $article->published_at === null) {
                $article->published_at = now();
            }
        });
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Published, release time reached, and written for this reader's language (or all languages). */
    public function scopeVisibleTo(Builder $query, string $locale): Builder
    {
        return $query->where('status', NewsStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('locale')->orWhere('locale', $locale));
    }

    public function isVisibleTo(string $locale): bool
    {
        return $this->status === NewsStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now())
            && ($this->locale === null || $this->locale === $locale);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /** Rough reading time at ~200 words per minute, at least one minute. */
    public function readingMinutes(): int
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags((string) $this->body)), -1, PREG_SPLIT_NO_EMPTY));

        return max(1, (int) ceil($words / 200));
    }
}
