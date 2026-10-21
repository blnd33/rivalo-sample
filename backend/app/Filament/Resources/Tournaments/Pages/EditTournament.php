<?php

namespace App\Filament\Resources\Tournaments\Pages;

use App\Enums\TournamentStatus;
use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Pages\TournamentFinance;
use App\Filament\Pages\TournamentFixtures;
use App\Filament\Pages\VenueCheckIn;
use App\Filament\Resources\Tournaments\TournamentResource;
use App\Models\Tournament;
use App\Services\TournamentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditTournament extends EditRecord
{
    use HandlesDomainRules;

    protected static string $resource = TournamentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $reason = $data['change_reason'] ?? null;
        unset($data['change_reason']);

        /** @var Tournament $record */
        return self::attempt(fn () => app(TournamentService::class)->update($record, auth()->user(), $data, $reason));
    }

    public function getSubheading(): ?string
    {
        /** @var Tournament $record */
        $record = $this->getRecord();

        return __('Status: :status', ['status' => $record->status->label()])
            .($record->is_sample_data ? ' · '.__('SAMPLE DATA') : '')
            .($record->areMaterialRulesLocked() ? ' · '.__('Material rules locked') : '');
    }

    protected function getHeaderActions(): array
    {
        $transitions = [];
        foreach (TournamentStatus::cases() as $target) {
            $needsReason = in_array($target, [TournamentStatus::Cancelled, TournamentStatus::Postponed], true);

            $transitions[] = Action::make('to_'.$target->value)
                ->label($this->transitionLabel($target))
                ->color(TournamentResource::statusColor($target))
                ->visible(fn (Tournament $record) => $record->status->canTransitionTo($target) && auth()->user()->can('update', $record))
                ->requiresConfirmation()
                ->modalDescription($this->transitionDescription($target))
                ->schema($needsReason
                    ? [Textarea::make('reason')->label(__('Public reason (shown to players)'))->required()]
                    : [Textarea::make('reason')->label(__('Note (optional, audited)'))])
                ->action(function (Tournament $record, array $data) use ($target) {
                    self::attempt(fn () => app(TournamentService::class)->transition($record, auth()->user(), $target, $data['reason'] ?? null));
                    Notification::make()->success()->title(__('Status changed to :status', ['status' => $target->label()]))->send();
                    $this->redirect(TournamentResource::getUrl('edit', ['record' => $record]));
                });
        }

        return [
            Action::make('checkInDesk')
                ->label(__('Open check-in desk'))
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->visible(fn (Tournament $record) => auth()->user()->can('recordCheckIn', $record)
                    && in_array($record->status, [TournamentStatus::RegistrationOpen, TournamentStatus::RegistrationClosed, TournamentStatus::InProgress], true))
                ->url(fn (Tournament $record) => VenueCheckIn::getUrl(['tournament' => $record->id])),
            Action::make('fixtures')
                ->label(__('Fixtures and results'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->visible(fn (Tournament $record) => auth()->user()->can('manageFixtures', $record) || auth()->user()->can('reviewResults', $record))
                ->url(fn (Tournament $record) => TournamentFixtures::getUrl(['tournament' => $record->id])),
            Action::make('finance')
                ->label(__('Finance'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->visible(fn () => auth()->user()->can('finance.manage'))
                ->url(fn (Tournament $record) => TournamentFinance::getUrl(['tournament' => $record->id])),
            ActionGroup::make($transitions)
                ->label(__('Change status'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->button(),
            DeleteAction::make(),
        ];
    }

    private function transitionLabel(TournamentStatus $target): string
    {
        return __(match ($target) {
            TournamentStatus::Draft => 'Return to draft',
            TournamentStatus::Published => 'Publish',
            TournamentStatus::RegistrationOpen => 'Open registration',
            TournamentStatus::RegistrationClosed => 'Close registration',
            TournamentStatus::InProgress => 'Start tournament',
            TournamentStatus::Completed => 'Mark completed',
            TournamentStatus::Postponed => 'Postpone',
            TournamentStatus::Cancelled => 'Cancel tournament',
        });
    }

    private function transitionDescription(TournamentStatus $target): string
    {
        return __(match ($target) {
            TournamentStatus::Published => 'Players will see this tournament. The discipline must be active with a confirmed ruleset, and paid entry must be allowed by the compliance flag.',
            TournamentStatus::RegistrationOpen => 'Players can register. Fees, prize rules, entry format and roster size become locked.',
            TournamentStatus::RegistrationClosed => 'No more registrations or player cancellations. All rosters are locked.',
            TournamentStatus::Cancelled => 'This cannot be undone. Record any in-person refunds separately (Milestone 4).',
            default => 'This change is recorded in the audit log.',
        });
    }
}
