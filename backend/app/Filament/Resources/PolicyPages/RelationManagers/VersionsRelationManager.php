<?php

namespace App\Filament\Resources\PolicyPages\RelationManagers;

use App\Models\PageVersion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only history of what players were shown. */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Published versions');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('publishedBy'))
            ->columns([
                TextColumn::make('version')->formatStateUsing(fn (int $state) => 'v'.$state)->weight('semibold'),
                TextColumn::make('change_note')->label(__('What changed'))->wrap(),
                TextColumn::make('publishedBy.username')->label(__('Published by'))->prefix('@'),
                TextColumn::make('published_at')->dateTime('d M Y H:i')->label(__('Published')),
                TextColumn::make('languages')->label(__('Languages'))
                    ->state(fn (PageVersion $v) => implode(' · ', array_keys(array_filter($v->body_translations ?? [])))),
            ]);
    }
}
