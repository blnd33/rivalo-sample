<?php

namespace App\Filament\Seller\Resources\Listings;

use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Seller\Resources\Listings\Pages\CreateListing;
use App\Filament\Seller\Resources\Listings\Pages\EditListing;
use App\Filament\Seller\Resources\Listings\Pages\ListListings;
use App\Filament\Seller\Resources\Listings\Pages\ViewListing;
use App\Filament\Seller\Resources\Listings\RelationManagers\SizesRelationManager;
use App\Models\Product;
use App\Services\StoreService;
use App\Support\Money;
use App\Support\SellerContext;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The seller's own listings. Drafts and listings sent back for changes can be edited; submitting
 * sends a listing to RIVALO staff for review. Stock is counted in by RIVALO staff, not here.
 */
class ListingResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'products';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 1;

    /** Only this shop's products, whatever the request asks for. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('seller_id', SellerContext::id() ?? 0);
    }

    public static function form(Schema $schema): Schema
    {
        $lang = fn (string $locale, string $label, bool $required) => Tab::make($label)->schema([
            TextInput::make("name_translations.{$locale}")->label(__('Name'))->maxLength(120)->required($required),
            Textarea::make("description_translations.{$locale}")->label(__('Description'))->rows(5)->maxLength(2000)
                ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']),
        ]);

        return $schema->columns(3)->components([
            Section::make(__('Changes requested by RIVALO'))->columnSpanFull()
                ->visible(fn (?Product $record) => $record?->status === ProductStatus::ChangesRequested && filled($record->review_note))
                ->schema([TextEntry::make('review_note')->hiddenLabel()->color('warning')]),
            Section::make(__('Product'))->columnSpan(2)->schema([
                Tabs::make('languages')->tabs([
                    $lang('en', 'English', true),
                    $lang('ar', 'العربية', false),
                    $lang('kmr', 'Kurdî (Badînî)', false),
                ]),
            ]),
            Section::make(__('Selling'))->columnSpan(1)->schema([
                TextInput::make('price_minor')->label(__('Price (minor units)'))->integer()->minValue(1)->maxValue(100000000)->required()
                    ->suffix(StoreService::currency())
                    ->helperText(__('IQD has no minor unit, so 25000 means 25,000 IQD. A size can override it.')),
                Select::make('category')->options(ProductCategory::options())->required()->native(false),
                Select::make('sport_id')->label(__('Sport'))->relationship('sport', 'name')->native(false),
                TextInput::make('max_per_order')->label(__('Most per order'))->integer()->minValue(1)->maxValue(50)->default(5)->required(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Changes requested by RIVALO'))->columnSpanFull()
                ->visible(fn (Product $record) => $record->status === ProductStatus::ChangesRequested && filled($record->review_note))
                ->schema([TextEntry::make('review_note')->hiddenLabel()->color('warning')]),
            Section::make(__('Product'))->columnSpan(2)->schema([
                TextEntry::make('name')->label(__('Name'))->state(fn (Product $p) => $p->name()),
                TextEntry::make('description')->label(__('Description'))->state(fn (Product $p) => $p->description())->placeholder('—'),
            ]),
            Section::make(__('Selling'))->columnSpan(1)->schema([
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (ProductStatus $state) => $state->label())
                    ->color(fn (ProductStatus $state) => $state->color()),
                TextEntry::make('price_minor')->label(__('Price'))->state(fn (Product $p) => Money::format($p->price_minor, $p->currency)),
                TextEntry::make('category')->formatStateUsing(fn (ProductCategory $state) => $state->label()),
                TextEntry::make('max_per_order')->label(__('Most per order')),
                TextEntry::make('submitted_at')->label(__('Submitted'))->dateTime()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['images', 'variants']))
            ->columns([
                ImageColumn::make('photo')->label(__('Photo'))->state(fn (Product $p) => $p->images->first()?->url())->imageHeight(40),
                TextColumn::make('name')->label(__('Name'))->state(fn (Product $p) => $p->name())->weight('semibold')
                    ->description(fn (Product $p) => $p->status === ProductStatus::ChangesRequested ? $p->review_note : null),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProductStatus $state) => $state->label())
                    ->color(fn (ProductStatus $state) => $state->color()),
                TextColumn::make('price_minor')->label(__('Price'))->formatStateUsing(fn (Product $p) => Money::format($p->price_minor, $p->currency)),
                TextColumn::make('stock')->label(__('Stock at RIVALO'))
                    ->state(fn (Product $p) => $p->variants->where('is_active', true)->sum('stock'))
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 3 ? 'warning' : null)),
                TextColumn::make('updated_at')->label(__('Last changed'))->since()->toggleable(),
            ])
            ->filters([SelectFilter::make('status')->options(ProductStatus::options())])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading(__('No products yet'))
            ->emptyStateDescription(__('Add your first product, then submit it for review.'));
    }

    public static function getRelations(): array
    {
        return [SizesRelationManager::class, ImagesRelationManager::class];
    }

    public static function getNavigationLabel(): string
    {
        return __('Your products');
    }

    public static function getModelLabel(): string
    {
        return __('Product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Your products');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListings::route('/'),
            'create' => CreateListing::route('/create'),
            'view' => ViewListing::route('/{record}'),
            'edit' => EditListing::route('/{record}/edit'),
        ];
    }
}
