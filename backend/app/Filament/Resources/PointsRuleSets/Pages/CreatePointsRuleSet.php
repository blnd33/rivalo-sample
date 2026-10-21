<?php

namespace App\Filament\Resources\PointsRuleSets\Pages;

use App\Filament\Resources\PointsRuleSets\PointsRuleSetResource;
use App\Support\Audit;
use Filament\Resources\Pages\CreateRecord;

class CreatePointsRuleSet extends CreateRecord
{
    protected static string $resource = PointsRuleSetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['version'] = 1;

        return $data;
    }

    protected function afterCreate(): void
    {
        Audit::record('points.rules_drafted', $this->record, new: $this->record->only(['name', 'discipline_id', 'season_id']));
    }
}
