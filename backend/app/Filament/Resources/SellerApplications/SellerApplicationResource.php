<?php

namespace App\Filament\Resources\SellerApplications;

use App\Enums\AdminNavigationGroup;
use App\Enums\ProductCategory;
use App\Enums\SellerApplicationStatus;
use App\Filament\Resources\SellerApplications\Pages\ListSellerApplications;
use App\Filament\Resources\SellerApplications\Pages\ViewSellerApplication;
use App\Models\SellerApplication;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** Players who asked to sell on RIVALO. Decided on the application page (approve or reject). */
class SellerApplicationResource extends Resource
{
    protected static ?string $model = SellerApplication::class;

    protected static ?string $slug = 'seller-applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Marketplace;

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $n = SellerApplication::where('status', SellerApplicationStatus::Pending->value)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function statusColor(SellerApplicationStatus $s): string
    {
        return match ($s) {
            SellerApplicationStatus::Pending => 'warning',
            SellerApplicationStatus::Approved => 'success',
            SellerApplicationStatus::Rejected => 'danger',
            SellerApplicationStatus::Withdrawn => 'gray',
        };
    }

    public static function categoryLabels(SellerApplication $a): string
    {
        return collect($a->categoryList())->map(fn ($c) => ProductCategory::from($c)->label())->implode(', ');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Application'))->columnSpan(2)->schema([
                TextEntry::make('business_name')->label(__('Business name'))->weight('bold'),
                TextEntry::make('categories')->label(__('Would sell'))->state(fn (SellerApplication $a) => self::categoryLabels($a)),
                TextEntry::make('description')->label(__('What they sell'))->columnSpanFull(),
                TextEntry::make('link')->label(__('Shop page or social profile'))->placeholder('—')
                    ->helperText(__('Opened at your own risk: check the address before you visit it.')),
            ]),
            Section::make(__('Applicant'))->columnSpan(1)->schema([
                TextEntry::make('user.username')->label(__('Account')),
                TextEntry::make('user.email')->label(__('Email')),
                TextEntry::make('contact_phone')->label(__('Phone')),
                TextEntry::make('city')->label(__('City')),
                TextEntry::make('created_at')->label(__('Received'))->dateTime(),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (SellerApplicationStatus $state) => $state->label())
                    ->color(fn (SellerApplicationStatus $state) => self::statusColor($state)),
                TextEntry::make('decision_note')->label(__('Reason given'))->placeholder('—')
                    ->visible(fn (SellerApplication $a) => $a->status === SellerApplicationStatus::Rejected),
                TextEntry::make('reviewedBy.username')->label(__('Decided by'))->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('business_name')->label(__('Business name'))->weight('semibold')->searchable()
                    ->description(fn (SellerApplication $a) => $a->is_sample_data ? new HtmlString('<span class="rivalo-sample-badge">'.e(__('Sample data')).'</span>') : $a->city),
                TextColumn::make('user.username')->label(__('Account')),
                TextColumn::make('categories')->label(__('Would sell'))->state(fn (SellerApplication $a) => self::categoryLabels($a))->wrap(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (SellerApplicationStatus $state) => $state->label())
                    ->color(fn (SellerApplicationStatus $state) => self::statusColor($state)),
                TextColumn::make('created_at')->label(__('Received'))->since(),
            ])
            ->filters([SelectFilter::make('status')->options(SellerApplicationStatus::options())->default(SellerApplicationStatus::Pending->value)])
            ->recordActions([ViewAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Seller application');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Seller applications');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellerApplications::route('/'),
            'view' => ViewSellerApplication::route('/{record}'),
        ];
    }
}
