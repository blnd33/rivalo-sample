<?php

namespace App\Filament\Resources\Achievements\Pages;

use App\Filament\Resources\Achievements\AchievementResource;
use App\Support\Audit;
use Filament\Resources\Pages\CreateRecord;

class CreateAchievement extends CreateRecord
{
    protected static string $resource = AchievementResource::class;

    protected function afterCreate(): void
    {
        Audit::record('achievement.defined', $this->record, new: $this->record->only(['key', 'criteria_type', 'threshold', 'status']));
    }
}
