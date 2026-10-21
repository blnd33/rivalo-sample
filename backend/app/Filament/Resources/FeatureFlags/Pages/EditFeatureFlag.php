<?php

namespace App\Filament\Resources\FeatureFlags\Pages;

use App\Filament\Resources\FeatureFlags\FeatureFlagResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;

class EditFeatureFlag extends EditRecord
{
    protected static string $resource = FeatureFlagResource::class;

    private bool $wasEnabled = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->wasEnabled = (bool) $this->record->enabled;
        $data['updated_by_id'] = auth()->id();

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->wasEnabled !== (bool) $this->record->enabled) {
            Audit::record(
                'feature_flag.toggled',
                $this->record,
                ['enabled' => $this->wasEnabled],
                ['enabled' => (bool) $this->record->enabled],
                $this->record->last_change_reason,
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
