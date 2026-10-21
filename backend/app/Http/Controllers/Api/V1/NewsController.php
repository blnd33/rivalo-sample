<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NewsCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsArticleResource;
use App\Models\NewsArticle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Public news feed; guests can read it. Only published articles for the reader's language. */
class NewsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(NewsCategory::values())],
            'sport_id' => ['nullable', 'integer'],
            'featured' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $articles = NewsArticle::query()
            ->visibleTo(app()->getLocale())
            ->with(['sport', 'tournament'])
            ->when($data['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($data['sport_id'] ?? null, fn ($q, $v) => $q->where('sport_id', $v))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 15);

        return NewsArticleResource::collection($articles);
    }

    public function show(NewsArticle $article): NewsArticleResource
    {
        abort_unless($article->isVisibleTo(app()->getLocale()), 404);

        return NewsArticleResource::make($article->load(['sport', 'tournament']))->detailed();
    }
}
