<?php

namespace App\Filament\Resources\Sponsors\Pages;

use App\Filament\Resources\Sponsors\SponsorResource;
use App\Support\Audit;
use Filament\Resources\Pages\CreateRecord;

class CreateSponsor extends CreateRecord
{
    protected static string $resource = SponsorResource::class;

    protected function afterCreate(): void
    {
        Audit::record('sponsor.created', $this->record, new: $this->record->only(['name', 'is_public', 'status']));
    }
}
