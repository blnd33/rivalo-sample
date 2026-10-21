<?php

namespace App\Filament\Resources\PointsRuleSets\Pages;

use App\Filament\Resources\PointsRuleSets\PointsRuleSetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPointsRuleSets extends ListRecords
{
    protected static string $resource = PointsRuleSetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
