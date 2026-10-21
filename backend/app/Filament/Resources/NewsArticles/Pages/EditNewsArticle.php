<?php

namespace App\Filament\Resources\NewsArticles\Pages;

use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Support\Audit;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditNewsArticle extends EditRecord
{
    protected static string $resource = NewsArticleResource::class;

    private const AUDITED = ['title', 'status', 'category', 'locale', 'is_featured', 'published_at', 'cover_path'];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $old = $record->only(self::AUDITED);
        $record->update($data);
        $changed = array_intersect_key($record->getChanges(), array_flip(self::AUDITED));

        if ($changed !== []) {
            Audit::record('news.updated', $record, old: array_intersect_key($old, $changed), new: $changed);
        }

        return $record;
    }
}
