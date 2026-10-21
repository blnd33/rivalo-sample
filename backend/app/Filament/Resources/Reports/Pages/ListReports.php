<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use Filament\Resources\Pages\ListRecords;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    public function getSubheading(): ?string
    {
        return __('Reports from players. Each decision needs a reason and is audited. Reporters are told their report was reviewed, never what happened to the other player.');
    }
}
