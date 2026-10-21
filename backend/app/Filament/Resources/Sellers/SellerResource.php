<?php

namespace App\Filament\Resources\Sellers;

use App\Enums\AdminNavigationGroup;
use App\Enums\SellerStatus;
use App\Filament\Resources\Sellers\Pages\EditSeller;
use App\Filament\Resources\Sellers\Pages\ListSellers;
use App\Filament\Resources\Sellers\Pages\ViewSeller;
use App\Filament\Resources\Sellers\RelationManagers\ListingsRelationManager;
use App\Filament\Resources\Sellers\RelationManagers\StatementRelationManager;
use App\Models\Seller;
use App\Services\SellerLedgerService;
use App\Services\StoreService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Approved sellers: their terms, what RIVALO owes them, payouts and listings.
 * Shops are created by approving an application and are never deleted.
 */
class SellerResource extends Resource
{
    protected static ?string $model = Seller::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Marketplace;

    protected static ?int $navigationSort = 2;

    public static function statusColor(SellerStatus $s): string
    {
        return match ($s) {
            SellerStatus::Active => 'success',
            SellerStatus::Suspended => 'warning',
            SellerStatus::Closed => 'gray',
        };
    }

    public static function form(Schema $schema): Schema
    {
        $bio = fn (string $locale, string $label) => Tab::make($label)->schema([
            Textarea::make("bio_translations.{$locale}")->hiddenLabel()->rows(4)->maxLength(600)
                ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']),
        ]);

        return $schema->columns(2)->components([
            Section::make(__('Shown in the app'))->schema([
                TextInput::make('name')->label(__('Shop name'))->required()->maxLength(120),
                TextInput::make('slug')->required()->maxLength(80)->alphaDash()->unique(ignoreRecord: true)
                    ->helperText(__('Part of the shop link. Letters, numbers and dashes.')),
                Tabs::make('bio')->label(__('About the shop'))->tabs([$bio('en', 'English'), $bio('ar', 'العربية'), $bio('kmr', 'Kurdî (Badînî)')]),
            ]),
            Section::make(__('Private contact details'))->schema([
                TextInput::make('contact_phone')->label(__('Phone'))->tel()->maxLength(32),
                TextInput::make('contact_email')->label(__('Email'))->email()->maxLength(160),
                TextInput::make('business_address')->label(__('Business address'))->maxLength(255),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Balance'))->columnSpan(2)->columns(3)->schema(fn (Seller $record) => self::balanceEntries($record)),
            Section::make(__('Shop'))->columnSpan(1)->schema([
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (SellerStatus $state) => $state->label())
                    ->color(fn (SellerStatus $state) => self::statusColor($state)),
                TextEntry::make('status_reason')->label(__('Reason'))->placeholder('—')->visible(fn (Seller $s) => ! $s->isActive()),
                TextEntry::make('user.username')->label(__('Account')),
                TextEntry::make('contact_phone')->label(__('Phone'))->placeholder('—'),
                TextEntry::make('contact_email')->label(__('Email'))->placeholder('—'),
                TextEntry::make('business_address')->label(__('Business address'))->placeholder('—'),
            ]),
            Section::make(__('Agreement'))->columnSpanFull()->columns(4)->schema([
                TextEntry::make('commission')->label(__('Commission'))->state(fn (Seller $s) => $s->commissionLabel()),
                TextEntry::make('agreement_reference')->label(__('Agreement reference')),
                TextEntry::make('approved_at')->label(__('Approved'))->dateTime(),
                TextEntry::make('approvedBy.username')->label(__('Approved by'))->placeholder('—'),
                TextEntry::make('verification_note')->label(__('What was checked'))->columnSpanFull(),
            ]),
        ]);
    }

    /** @return list<TextEntry> */
    private static function balanceEntries(Seller $seller): array
    {
        $s = app(SellerLedgerService::class)->summary($seller);
        $m = fn (int $v) => Money::format($v, $s['currency']);

        return [
            TextEntry::make('owed')->label($s['owed'] < 0 ? __('Seller owes RIVALO') : __('RIVALO owes the seller'))->state($m(abs($s['owed'])))
                ->weight('bold')->size('lg')->color($s['owed'] < 0 ? 'danger' : 'primary'),
            TextEntry::make('sales')->label(__('Completed sales'))->state($m($s['sales'])),
            TextEntry::make('commission_total')->label(__('RIVALO commission'))->state($m($s['commission'])),
            TextEntry::make('returns')->label(__('Returns'))->state($m($s['returns'])),
            TextEntry::make('adjustments')->label(__('Adjustments'))->state($m($s['adjustments'])),
            TextEntry::make('paid')->label(__('Paid to the seller'))->state($m($s['paid'])),
        ];
    }

    public static function table(Table $table): Table
    {
        $currency = StoreService::currency();

        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with('user')
                ->withSum(['ledger as owed_minor' => fn ($q) => $q->where('currency', $currency)], 'amount_minor')
                ->withCount(['products as on_sale_count' => fn ($q) => $q->where('status', 'active')]))
            ->columns([
                TextColumn::make('name')->label(__('Shop name'))->weight('semibold')->searchable()
                    ->description(fn (Seller $s) => $s->is_sample_data ? new HtmlString('<span class="rivalo-sample-badge">'.e(__('Sample data')).'</span>') : $s->user?->username),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (SellerStatus $state) => $state->label())
                    ->color(fn (SellerStatus $state) => self::statusColor($state)),
                TextColumn::make('commission_bps')->label(__('Commission'))->formatStateUsing(fn (Seller $s) => $s->commissionLabel()),
                TextColumn::make('on_sale_count')->label(__('On sale')),
                TextColumn::make('owed_minor')->label(__('RIVALO owes'))->weight('bold')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state, $currency))
                    ->color(fn ($state) => (int) $state < 0 ? 'danger' : ((int) $state > 0 ? 'primary' : null)),
            ])
            ->filters([SelectFilter::make('status')->options(SellerStatus::options())])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [StatementRelationManager::class, ListingsRelationManager::class];
    }

    public static function getModelLabel(): string
    {
        return __('Seller');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sellers');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellers::route('/'),
            'view' => ViewSeller::route('/{record}'),
            'edit' => EditSeller::route('/{record}/edit'),
        ];
    }
}
