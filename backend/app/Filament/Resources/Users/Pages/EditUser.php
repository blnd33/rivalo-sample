<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<string, mixed> */
    private array $before = [];

    protected function beforeSave(): void
    {
        $this->before = [
            'status' => $this->record->status->value,
            'roles' => $this->record->getRoleNames()->sort()->values()->all(),
        ];
    }

    protected function afterSave(): void
    {
        $user = $this->record->fresh();
        $after = [
            'status' => $user->status->value,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
        ];

        if ($after['status'] === UserStatus::Suspended->value && $this->before['status'] !== UserStatus::Suspended->value) {
            $user->tokens()->delete(); // End every app session immediately.
        }

        if ($after !== $this->before) {
            Audit::record('user.admin_updated', $user, $this->before, $after, $user->suspension_reason);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
