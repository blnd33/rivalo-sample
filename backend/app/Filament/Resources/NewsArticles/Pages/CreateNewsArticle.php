<?php

namespace App\Filament\Resources\NewsArticles\Pages;

use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Support\Audit;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $article = static::getModel()::make($data);
        $article->author_id = auth()->id();
        $article->save();

        Audit::record('news.created', $article, new: $article->only(['title', 'status', 'category', 'locale', 'is_featured', 'published_at']));

        return $article;
    }
}
