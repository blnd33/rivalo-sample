<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Support\ImageSanitizer;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Product photos. Re-encoded on upload (hidden data such as GPS location is removed). */
class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Photos');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')->label(__('Photo'))->image()->disk('public')->directory('products')->maxSize(6144)->required()
                ->saveUploadedFileUsing(function (TemporaryUploadedFile $file) {
                    $clean = ImageSanitizer::reencode($file, 1600);
                    $path = 'products/'.Str::uuid().'.'.($clean === null ? $file->extension() : 'jpg');
                    Storage::disk('public')->put($path, $clean ?? $file->get());

                    return $path;
                })
                ->helperText(__('Use only photos you own. The first photo is the main one.')),
            TextInput::make('alt')->label(__('Description for screen readers'))->maxLength(160),
            TextInput::make('sort_order')->integer()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('path')->disk('public')->label(__('Photo'))->imageHeight(56),
                TextColumn::make('alt')->label(__('Description for screen readers'))->placeholder('—'),
                TextColumn::make('sort_order'),
            ])
            ->headerActions([CreateAction::make()->label(__('Add photo'))])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
