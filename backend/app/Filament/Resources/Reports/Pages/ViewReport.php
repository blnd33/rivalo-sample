<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Enums\ModerationAction;
use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Report;
use App\Services\ModerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ViewReport extends ViewRecord
{
    use HandlesDomainRules;

    protected static string $resource = ReportResource::class;

    public function getTitle(): string
    {
        return __('Report #:id', ['id' => $this->record->id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('act')
                ->label(fn (Report $record) => $record->subject_type->value === 'app' ? __('Mark as handled') : __('Take action'))
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->color('danger')
                ->visible(fn (Report $record) => $record->isOpen())
                ->modalDescription(__('The player is told what was removed. Other open reports about the same subject are closed with this decision.'))
                ->schema([
                    Select::make('action')
                        ->options(fn (Report $record) => collect($record->subject_type->actions())->mapWithKeys(fn (ModerationAction $a) => [$a->value => $a->label()])->all())
                        ->default(fn (Report $record) => count($record->subject_type->actions()) === 1 ? $record->subject_type->actions()[0]->value : null)
                        ->required()->live()->native(false),
                    TextInput::make('new_name')->label(__('New team name'))->maxLength(60)
                        ->visible(fn (Get $get) => $get('action') === ModerationAction::RenameTeam->value)
                        ->required(fn (Get $get) => $get('action') === ModerationAction::RenameTeam->value),
                    Textarea::make('message')->label(__('Message to the player (optional)'))->rows(3)->maxLength(1000)
                        ->helperText(__('Shown in their inbox. Leave empty for the standard text pointing to the community guidelines.'))
                        ->visible(fn (Get $get) => $get('action') === ModerationAction::Warn->value),
                    Textarea::make('note')->label(__('Reason (audited, internal)'))->required()->rows(3)->maxLength(2000),
                ])
                ->action(function (Report $record, array $data) {
                    self::attempt(fn () => app(ModerationService::class)->act(
                        $record, auth()->user(), ModerationAction::from($data['action']), $data['note'],
                        ['message' => $data['message'] ?? null, 'new_name' => $data['new_name'] ?? null],
                    ));
                    Notification::make()->success()->title(__('Report handled'))->send();
                    $this->record->refresh();
                }),
            Action::make('dismiss')
                ->label(__('Dismiss'))
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->visible(fn (Report $record) => $record->isOpen() && $record->subject_type->value !== 'app')
                ->modalDescription(__('No breach found. The reporter is told their report was reviewed.'))
                ->schema([Textarea::make('note')->label(__('Reason (audited, internal)'))->required()->rows(3)->maxLength(2000)])
                ->action(function (Report $record, array $data) {
                    self::attempt(fn () => app(ModerationService::class)->dismiss($record, auth()->user(), $data['note']));
                    Notification::make()->success()->title(__('Report dismissed'))->send();
                    $this->record->refresh();
                }),
        ];
    }
}
