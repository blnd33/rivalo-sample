<?php

namespace App\Http\Resources;

use App\Models\NewsArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public news. No author identity or other staff fields are exposed.
 *
 * @mixin NewsArticle
 */
class NewsArticleResource extends JsonResource
{
    private bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $tournament = $this->tournament;

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'cover_url' => $this->coverUrl(),
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toIso8601String(),
            'reading_minutes' => $this->readingMinutes(),
            'sport' => $this->sport_id ? ['id' => $this->sport_id, 'name' => $this->sport?->localizedName(), 'icon' => $this->sport?->icon] : null,
            // Linked tournament only while it is publicly visible (never leak drafts).
            'tournament' => $tournament && $tournament->status->isPublic()
                ? ['slug' => $tournament->slug, 'name' => $tournament->name]
                : null,
            'is_sample_data' => $this->is_sample_data,
        ];

        if ($this->detailed) {
            $data['body'] = $this->body;
        }

        return $data;
    }
}
