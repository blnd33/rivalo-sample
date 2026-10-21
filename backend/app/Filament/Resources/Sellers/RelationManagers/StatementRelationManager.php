<?php

namespace App\Filament\Resources\Sellers\RelationManagers;

use App\Enums\SellerLedgerType;
use App\Filament\Seller\Resources\Statement\StatementResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The same statement the seller sees in the Seller Hub. Append-only. */
class StatementRelationManager extends RelationManager
{
    protected static string $relationship = 'ledger';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Statement');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return StatementResource::columns($table)
            ->defaultSort('occurred_at', 'desc')
            ->filters([SelectFilter::make('type')->label(__('Type'))->options(SellerLedgerType::options())]);
    }
}
