<?php

namespace App\Filament\Resources\PolicyPages;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\PolicyPages\Pages\EditPage;
use App\Filament\Resources\PolicyPages\Pages\ListPages;
use App\Filament\Resources\PolicyPages\RelationManagers\VersionsRelationManager;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Terms, Privacy and Community guidelines. Saving changes the draft; "Publish" makes a new
 * numbered version that players see. Legal review is recorded per version (super admin).
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Community;

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'policy-pages';

    public static function legalStatusLabel(Page $page): string
    {
        return match ($page->legalStatus()) {
            'reviewed' => __('Reviewed (version :v)', ['v' => $page->legal_reviewed_version]),
            'pending' => __('Pending legal review'),
            default => __('Not required'),
        };
    }

    public static function legalStatusColor(Page $page): string
    {
        return match ($page->legalStatus()) {
            'reviewed' => 'success',
            'pending' => 'warning',
            default => 'gray',
        };
    }

    public static function form(Schema $schema): Schema
    {
        $tab = fn (string $locale, string $label, bool $required) => Tab::make($label)->schema([
            TextInput::make("title_translations.{$locale}")->label(__('Title'))->maxLength(120)->required($required),
            Textarea::make("body_translations.{$locale}")->label(__('Text'))->rows(24)->required($required)
                ->helperText(__('Plain text. A blank line starts a new paragraph. A short line on its own is shown as a heading.'))
                ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']),
        ]);

        return $schema->components([
            Tabs::make('languages')->columnSpanFull()->tabs([
                $tab('en', 'English', true),
                $tab('ar', 'العربية', false),
                $tab('kmr', 'Kurdî (Badînî)', false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->state(fn (Page $p) => $p->title())->weight('semibold')->description(fn (Page $p) => '/'.$p->slug),
                TextColumn::make('current_version')->label(__('Live version'))
                    ->formatStateUsing(fn (int $state) => $state > 0 ? 'v'.$state : __('Not published')),
                TextColumn::make('published_at')->dateTime('d M Y H:i')->label(__('Published'))->placeholder('—'),
                TextColumn::make('legal')->label(__('Legal review'))->badge()
                    ->state(fn (Page $p) => self::legalStatusLabel($p))->color(fn (Page $p) => self::legalStatusColor($p)),
                TextColumn::make('translations')->label(__('Languages'))
                    ->state(fn (Page $p) => implode(' · ', array_keys(array_filter($p->body_translations ?? [])))),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    public static function getModelLabel(): string
    {
        return __('Policy page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Policy pages');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
