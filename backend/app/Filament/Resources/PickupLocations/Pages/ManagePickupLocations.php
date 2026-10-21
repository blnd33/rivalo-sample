<?php

namespace App\Filament\Resources\PickupLocations\Pages;

use App\Filament\Resources\PickupLocations\PickupLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePickupLocations extends ManageRecords
{
    protected static string $resource = PickupLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
