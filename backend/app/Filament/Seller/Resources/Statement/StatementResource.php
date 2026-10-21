<?php

namespace App\Filament\Seller\Resources\Statement;

use App\Enums\SellerLedgerType;
use App\Filament\Seller\Resources\Statement\Pages\ListStatement;
use App\Models\SellerLedgerEntry;
use App\Support\Money;
use App\Support\SellerContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The seller's statement: completed sales, returns, adjustments and payouts. Read-only. */
class StatementResource extends Resource
{
    protected static ?string $model = SellerLedgerEntry::class;

    protected static ?string $slug = 'statement';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('seller_id', SellerContext::id() ?? 0);
    }

    public static function table(Table $table): Table
    {
        return self::columns($table)
            ->defaultSort('occurred_at', 'desc')
            ->filters([SelectFilter::make('type')->label(__('Type'))->options(SellerLedgerType::options())])
            ->emptyStateHeading(__('Nothing on your statement yet'))
            ->emptyStateDescription(__('Sales appear here when the customer has the item and has paid.'));
    }

    /** Shared with the admin seller page, so staff and the seller read the same statement. */
    public static function columns(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['order:id,number', 'orderItem:id,product_name']))
            ->columns([
                TextColumn::make('occurred_at')->label(__('Date'))->dateTime('j M Y'),
                TextColumn::make('type')->label(__('Type'))->badge()
                    ->formatStateUsing(fn (SellerLedgerType $state) => $state->label())
                    ->color(fn (SellerLedgerType $state) => match ($state) {
                        SellerLedgerType::Sale => 'success',
                        SellerLedgerType::Return => 'warning',
                        SellerLedgerType::Adjustment => 'info',
                        SellerLedgerType::Payout => 'gray',
                    }),
                TextColumn::make('details')->label(__('Details'))->wrap()
                    ->state(fn (SellerLedgerEntry $e) => self::details($e)),
                TextColumn::make('gross_minor')->label(__('Item value'))
                    ->state(fn (SellerLedgerEntry $e) => $e->gross_minor !== 0 ? Money::format($e->gross_minor, $e->currency) : '—'),
                TextColumn::make('commission_minor')->label(__('Commission'))
                    ->state(fn (SellerLedgerEntry $e) => $e->commission_minor !== 0 ? Money::format(-$e->commission_minor, $e->currency) : '—'),
                TextColumn::make('amount_minor')->label(__('Effect on balance'))->weight('bold')
                    ->state(fn (SellerLedgerEntry $e) => ($e->amount_minor > 0 ? '+' : '').Money::format($e->amount_minor, $e->currency))
                    ->color(fn (SellerLedgerEntry $e) => $e->amount_minor >= 0 ? 'success' : 'danger'),
            ]);
    }

    public static function details(SellerLedgerEntry $e): string
    {
        return match ($e->type) {
            SellerLedgerType::Sale, SellerLedgerType::Return => trim(($e->order?->number ?? '').' · '.$e->quantity.' × '.($e->orderItem?->product_name ?? ''), ' ·'),
            SellerLedgerType::Payout => trim($e->payoutNumber().' · '.$e->payout_method?->label().($e->reference ? ' · '.$e->reference : '').($e->note ? ' · '.$e->note : '')),
            SellerLedgerType::Adjustment => (string) $e->note,
        };
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Statement');
    }

    public static function getModelLabel(): string
    {
        return __('Statement line');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Statement');
    }

    public static function getPages(): array
    {
        return ['index' => ListStatement::route('/')];
    }
}
