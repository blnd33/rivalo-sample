<?php

namespace App\Support;

use App\Filament\Seller\Resources\Statement\StatementResource;
use App\Models\Seller;
use App\Models\SellerLedgerEntry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** One seller's statement as CSV (Seller Hub and admin). Amounts in minor units. */
final class SellerStatementCsv
{
    public static function download(Seller $seller): StreamedResponse
    {
        Audit::record('seller.statement_exported', $seller);
        $entries = SellerLedgerEntry::where('seller_id', $seller->id)->with(['order:id,number', 'orderItem:id,product_name'])->orderBy('occurred_at')->orderBy('id')->get();

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'type', 'details', 'currency', 'item_value_minor', 'commission_minor', 'amount_minor', 'running_balance_minor']);
            $running = [];
            foreach ($entries as $e) {
                $running[$e->currency] = ($running[$e->currency] ?? 0) + $e->amount_minor;
                fputcsv($out, [
                    $e->occurred_at?->toDateString(), $e->type->value, StatementResource::details($e), $e->currency,
                    $e->gross_minor, $e->commission_minor, $e->amount_minor, $running[$e->currency],
                ]);
            }
            fclose($out);
        }, 'rivalo-statement-'.$seller->slug.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
