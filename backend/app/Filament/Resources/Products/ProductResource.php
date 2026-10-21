<?php

namespace App\Filament\Resources\Products;

use App\Enums\AdminNavigationGroup;
use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\Seller;
use App\Services\StoreService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Official RIVALO products and approved sellers' listings (Milestone 9). Sizes, stock and photos
 * are managed in the tabs below the form. Seller listings are approved or sent back from here.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Store;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $currency = StoreService::currency();
        $lang = fn (string $locale, string $label, bool $required) => Tab::make($label)->schema([
            TextInput::make("name_translations.{$locale}")->label(__('Name'))->maxLength(120)->required($required)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, $set, $get) use ($locale) {
                    if ($locale === 'en' && blank($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            Textarea::make("description_translations.{$locale}")->label(__('Description'))->rows(5)->maxLength(2000)
                ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']),
        ]);

        return $schema->columns(3)->components([
            Section::make(__('Product'))->columnSpan(2)->schema([
                Tabs::make('languages')->tabs([
                    $lang('en', 'English', true),
                    $lang('ar', 'العربية', false),
                    $lang('kmr', 'Kurdî (Badînî)', false),
                ]),
                TextInput::make('slug')->required()->maxLength(80)->unique(ignoreRecord: true)->alphaDash()
                    ->helperText(__('Part of the product link. Letters, numbers and dashes.')),
            ]),
            Section::make(__('Selling'))->columnSpan(1)->schema([
                Select::make('status')->required()->default('draft')->native(false)
                    ->options(fn (?Product $record) => $record?->seller_id ? ProductStatus::options() : ProductStatus::officialOptions())
                    // Seller listings change status only through review (Approve / Request changes) or the seller.
                    ->disabled(fn (?Product $record) => (bool) $record?->seller_id)
                    ->helperText(fn (?Product $record) => $record?->seller_id
                        ? __('Seller product: use Approve or Request changes above.')
                        : __('Only active products are visible and can be bought.')),
                TextInput::make('price_minor')->label(__('Price (minor units)'))->integer()->minValue(1)->maxValue(100000000)->required()
                    ->suffix($currency)
                    ->helperText(__('IQD has no minor unit, so 25000 means 25,000 IQD. A size can override it.')),
                Select::make('category')->options(ProductCategory::options())->required()->native(false),
                Select::make('sport_id')->label(__('Sport'))->relationship('sport', 'name')->native(false),
                TextInput::make('max_per_order')->label(__('Most per order'))->integer()->minValue(1)->maxValue(50)->default(5)->required(),
                Toggle::make('is_featured')->label(__('Feature at the top of the store')),
                TextInput::make('sort_order')->integer()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->with(['images', 'variants', 'seller']))
            ->columns([
                ImageColumn::make('photo')->label(__('Photo'))->state(fn (Product $p) => $p->images->first()?->url())->imageHeight(40),
                TextColumn::make('name')->label(__('Name'))->state(fn (Product $p) => $p->name())->weight('semibold')
                    ->searchable(query: fn ($query, string $search) => $query->where('name_translations', 'like', '%'.addcslashes($search, '%_\\').'%'))
                    ->description(fn (Product $p) => $p->is_sample_data ? new HtmlString('<span class="rivalo-sample-badge">'.e(__('Sample data')).'</span>') : null),
                TextColumn::make('seller.name')->label(__('Sold by'))->placeholder('RIVALO')->toggleable(),
                TextColumn::make('category')->badge()->formatStateUsing(fn (ProductCategory $state) => $state->label()),
                TextColumn::make('price_minor')->label(__('Price'))->formatStateUsing(fn (Product $p) => Money::format($p->price_minor, $p->currency)),
                TextColumn::make('stock')->label(__('Stock'))
                    ->state(fn (Product $p) => $p->variants->where('is_active', true)->sum('stock'))
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 5 ? 'warning' : null)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProductStatus $state) => $state->label())
                    ->color(fn (ProductStatus $state) => $state->color()),
                IconColumn::make('is_featured')->boolean()->label(__('Featured')),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProductStatus::options()),
                SelectFilter::make('category')->options(ProductCategory::options()),
                SelectFilter::make('sold_by')->label(__('Sold by'))
                    ->options(fn () => ['official' => 'RIVALO'] + Seller::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'official' => $query->whereNull('seller_id'),
                        default => $query->where('seller_id', $data['value']),
                    }),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [VariantsRelationManager::class, ImagesRelationManager::class];
    }

    public static function getModelLabel(): string
    {
        return __('Product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Products');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
