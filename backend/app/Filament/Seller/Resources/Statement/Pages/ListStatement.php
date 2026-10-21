<?php

namespace App\Filament\Seller\Resources\Statement\Pages;

use App\Filament\Seller\Resources\Statement\StatementResource;
use App\Services\SellerLedgerService;
use App\Support\Money;
use App\Support\SellerContext;
use App\Support\SellerStatementCsv;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListStatement extends ListRecords
{
    protected static string $resource = StatementResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $seller = SellerContext::current();
        if (! $seller) {
            return null;
        }
        $s = app(SellerLedgerService::class)->summary($seller);
        $m = fn (int $v) => Money::format($v, $s['currency']);

        return __('RIVALO owes you :owed. Sales :sales, commission :commission, returns :returns, adjustments :adjustments, paid to you :paid.', [
            'owed' => $m($s['owed']), 'sales' => $m($s['sales']), 'commission' => $m($s['commission']),
            'returns' => $m($s['returns']), 'adjustments' => $m($s['adjustments']), 'paid' => $m($s['paid']),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')
                ->label(__('Download CSV'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => SellerStatementCsv::download(SellerContext::current())),
        ];
    }
}
