<?php

namespace App\Filament\Resources\Tournaments\Pages;

use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\Tournaments\TournamentResource;
use App\Services\TournamentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTournament extends CreateRecord
{
    use HandlesDomainRules;

    protected static string $resource = TournamentResource::class;

    /** New tournaments always start as drafts and go through TournamentService validation. */
    protected function handleRecordCreation(array $data): Model
    {
        unset($data['change_reason']);

        return self::attempt(fn () => app(TournamentService::class)->create(auth()->user(), $data));
    }
}
