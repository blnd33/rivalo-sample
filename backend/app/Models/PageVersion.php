<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** A published policy text. Append-only: what players were shown stays on record. */
#[Fillable(['page_id', 'version', 'title_translations', 'body_translations', 'change_note', 'published_by_id', 'published_at'])]
class PageVersion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'title_translations' => 'array',
            'body_translations' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published policy versions are append-only.'));
        static::deleting(fn () => throw new LogicException('Published policy versions are append-only.'));
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    /**
     * Text in the requested language, falling back to English.
     *
     * @return array{title: string, body: string, locale: string, fallback: bool}
     */
    public function localized(string $locale): array
    {
        $hasTranslation = filled($this->title_translations[$locale] ?? null) && filled($this->body_translations[$locale] ?? null);
        $use = $hasTranslation ? $locale : 'en';

        return [
            'title' => (string) ($this->title_translations[$use] ?? ''),
            'body' => (string) ($this->body_translations[$use] ?? ''),
            'locale' => $use,
            'fallback' => $use !== $locale,
        ];
    }
}
