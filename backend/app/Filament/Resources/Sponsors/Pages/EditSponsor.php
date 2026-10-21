<?php

namespace App\Filament\Resources\Sponsors\Pages;

use App\Filament\Resources\Sponsors\SponsorResource;
use App\Support\Audit;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSponsor extends EditRecord
{
    protected static string $resource = SponsorResource::class;

    private const AUDITED = ['name', 'is_public', 'status', 'logo_path', 'website_url'];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $old = $record->only(self::AUDITED);
        $record->update($data);
        $changed = array_intersect_key($record->getChanges(), array_flip(self::AUDITED));
        if ($changed !== []) {
            Audit::record('sponsor.updated', $record, array_intersect_key($old, $changed), $changed);
        }

        return $record;
    }
}
