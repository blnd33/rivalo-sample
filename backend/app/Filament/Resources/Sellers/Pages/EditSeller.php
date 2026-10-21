<?php

namespace App\Filament\Resources\Sellers\Pages;

use App\Filament\Resources\Sellers\SellerResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSeller extends EditRecord
{
    protected static string $resource = SellerResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $old = $record->only(['name', 'slug']);
        $record->update($data);
        $changed = array_intersect_key($record->getChanges(), $old);
        Audit::record('seller.updated', $record, array_intersect_key($old, $changed), $changed);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return SellerResource::getUrl('view', ['record' => $this->record]);
    }
}
