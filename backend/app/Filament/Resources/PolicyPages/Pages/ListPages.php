<?php

namespace App\Filament\Resources\PolicyPages\Pages;

use App\Filament\Resources\PolicyPages\PageResource;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function getSubheading(): ?string
    {
        return __('Terms and Privacy show "Pending legal review" in the app until a super admin records that a qualified reviewer approved the live version.');
    }
}
