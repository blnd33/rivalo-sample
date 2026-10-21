<?php

namespace App\Filament\Resources\NewsArticles;

use App\Enums\AdminNavigationGroup;
use App\Enums\NewsCategory;
use App\Enums\NewsStatus;
use App\Filament\Resources\NewsArticles\Pages\CreateNewsArticle;
use App\Filament\Resources\NewsArticles\Pages\EditNewsArticle;
use App\Filament\Resources\NewsArticles\Pages\ListNewsArticles;
use App\Models\NewsArticle;
use App\Models\Tournament;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** News and announcements shown in the app's News tab and Home slider. Content editors only. */
class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Community;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Article'))->columnSpan(2)->columns(2)->schema([
                TextInput::make('title')->required()->maxLength(160)->columnSpanFull(),
                Textarea::make('summary')
                    ->rows(2)
                    ->maxLength(300)
                    ->columnSpanFull()
                    ->helperText(__('One or two sentences shown on cards and in the slider.')),
                Textarea::make('body')
                    ->required()
                    ->rows(14)
                    ->columnSpanFull()
                    ->helperText(__('Plain text. Leave an empty line between paragraphs.')),
                FileUpload::make('cover_path')
                    ->label(__('Cover photo'))
                    ->image()
                    ->disk('public')
                    ->directory('news-covers')
                    ->maxSize(4096)
                    ->imageAspectRatio('16:9')
                    ->columnSpanFull()
                    ->helperText(__('Use only photos you own or have permission to publish. Without a photo the app shows sport artwork.')),
            ]),
            Section::make(__('Publishing'))->columnSpan(1)->schema([
                Select::make('status')->options(NewsStatus::options())->required()->default('draft')->native(false),
                DateTimePicker::make('published_at')
                    ->label(__('Publish at'))
                    ->seconds(false)
                    ->helperText(__('Leave empty to publish immediately. A future time schedules the article.')),
                Toggle::make('is_featured')
                    ->label(__('Feature in the photo slider'))
                    ->helperText(__('Featured articles appear in the slider on Home and News.')),
                Select::make('category')->options(NewsCategory::options())->required()->default('announcement')->native(false),
                Select::make('locale')
                    ->label(__('Language'))
                    ->options([
                        'en' => 'English',
                        'ar' => 'العربية',
                        'kmr' => 'Kurdî (Badînî)',
                    ])
                    ->placeholder(__('All app languages'))
                    ->native(false)
                    ->helperText(__('Readers only see articles in their app language, or articles for all languages.')),
                Select::make('sport_id')->label(__('Sport'))->relationship('sport', 'name')->native(false),
                Select::make('tournament_id')
                    ->label(__('Tournament'))
                    ->options(fn () => Tournament::query()->orderByDesc('starts_at')->limit(200)->pluck('name', 'id'))
                    ->searchable()
                    ->native(false)
                    ->helperText(__('Readers can open the tournament from the article while it is public.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('cover_path')->disk('public')->label(__('Cover'))->imageHeight(40),
                TextColumn::make('title')->searchable()->weight('semibold')->limit(60)
                    ->description(fn (NewsArticle $record) => $record->is_sample_data ? __('Sample data') : null),
                TextColumn::make('category')->badge()->formatStateUsing(fn (NewsCategory $state) => $state->label()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (NewsStatus $state) => $state->label())
                    ->color(fn (NewsStatus $state) => match ($state) {
                        NewsStatus::Published => 'success',
                        NewsStatus::Draft => 'warning',
                        NewsStatus::Archived => 'gray',
                    }),
                IconColumn::make('is_featured')->boolean()->label(__('Featured')),
                TextColumn::make('locale')->label(__('Language'))->placeholder(__('All'))->badge(),
                TextColumn::make('published_at')->dateTime('d M Y H:i')->label(__('Published'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(NewsStatus::options()),
                SelectFilter::make('category')->options(NewsCategory::options()),
                TernaryFilter::make('is_featured')->label(__('Featured')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('News article');
    }

    public static function getPluralModelLabel(): string
    {
        return __('News');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsArticles::route('/'),
            'create' => CreateNewsArticle::route('/create'),
            'edit' => EditNewsArticle::route('/{record}/edit'),
        ];
    }
}
