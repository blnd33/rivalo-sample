<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['created_by_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('edit', ['record' => $this->record]);
    }
}
