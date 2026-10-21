<?php

namespace App\Filament\Resources\Tournaments\RelationManagers;

use App\Enums\CheckInStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Concerns\HandlesDomainRules;
use App\Models\TournamentEntry;
use App\Services\RegistrationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Registrations with their four independent status tracks. Check-in and payment recording
 * arrive in Milestone 4; this milestone supports staff cancellation and no-show only.
 */
class EntriesRelationManager extends RelationManager
{
    use HandlesDomainRules;

    protected static string $relationship = 'entries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Registrations');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('manageRegistrations', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['activeMembers', 'team', 'tournament']))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('display_name')->label(__('Entrant'))->searchable()->weight('semibold')
                    ->description(fn (TournamentEntry $r) => $r->activeMembers->pluck('username_snapshot')->implode(', ')),
                TextColumn::make('registration_status')->label(__('Registration'))->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state, TournamentEntry $r) => $state === RegistrationStatus::Waitlisted
                        ? __('Waitlist #:position', ['position' => $r->waitlist_position])
                        : $state->label())
                    ->color(fn (RegistrationStatus $state) => match ($state) {
                        RegistrationStatus::Reserved => 'success',
                        RegistrationStatus::Waitlisted => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payment_status')->label(__('Payment'))->badge()
                    ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                    ->color(fn (PaymentStatus $state) => match ($state) {
                        PaymentStatus::Paid, PaymentStatus::NotRequired => 'success',
                        PaymentStatus::Unpaid => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('check_in_status')->label(__('Check-in'))->badge()
                    ->formatStateUsing(fn (CheckInStatus $state) => $state->label()),
                TextColumn::make('eligibility_status')->label(__('Eligibility'))->badge()
                    ->formatStateUsing(fn (EligibilityStatus $state) => $state->label()),
                TextColumn::make('created_at')->label(__('Registered'))->dateTime('d M, H:i'),
            ])
            ->filters([
                SelectFilter::make('registration_status')->options(RegistrationStatus::options()),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label(__('Cancel'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (TournamentEntry $record) => $record->isActive() && auth()->user()->can('staffManage', $record))
                    ->requiresConfirmation()
                    ->modalDescription(__('The first waitlisted entry is promoted automatically. Record any in-person refund separately.'))
                    ->schema([Textarea::make('reason')->required()->label(__('Reason (shown to the entrant)'))])
                    ->action(function (TournamentEntry $record, array $data) {
                        self::attempt(fn () => app(RegistrationService::class)->cancel($record, auth()->user(), $data['reason'], asStaff: true));
                        Notification::make()->success()->title(__('Registration cancelled'))->send();
                    }),
                Action::make('noShow')
                    ->label(__('No-show'))
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('warning')
                    ->visible(fn (TournamentEntry $record) => $record->registration_status === RegistrationStatus::Reserved
                        && auth()->user()->can('staffManage', $record))
                    ->requiresConfirmation()
                    ->modalDescription(__('Points consequences of no-shows are defined by the published points rules (decision D11). Nothing is awarded or deducted here.'))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(function (TournamentEntry $record, array $data) {
                        self::attempt(fn () => app(RegistrationService::class)->markNoShow($record, auth()->user(), $data['reason']));
                        Notification::make()->success()->title(__('Marked as no-show'))->send();
                    }),
            ]);
    }
}
