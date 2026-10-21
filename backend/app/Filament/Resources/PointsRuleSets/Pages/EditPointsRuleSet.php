<?php

namespace App\Filament\Resources\PointsRuleSets\Pages;

use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\PointsRuleSets\PointsRuleSetResource;
use App\Models\PointsRuleSet;
use App\Services\PointsService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPointsRuleSet extends EditRecord
{
    use HandlesDomainRules;

    protected static string $resource = PointsRuleSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(__('Publish'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('success')
                ->visible(fn (PointsRuleSet $record) => $record->isDraft())
                ->modalDescription(__('Published points are awarded for approved results in tournaments that use this table. It replaces the current published table for this discipline and season. Publish only a table the owner has decided.'))
                ->schema([Textarea::make('note')->label(__('Approval note (who decided, where it is documented)'))->required()])
                ->action(function (PointsRuleSet $record, array $data) {
                    $this->save(shouldRedirect: false);
                    self::attempt(fn () => app(PointsService::class)->publish($record, auth()->user(), $data['note']));
                    Notification::make()->success()->title(__('Points table published'))->send();
                    $this->redirect(PointsRuleSetResource::getUrl('edit', ['record' => $record]));
                }),
            Action::make('newVersion')
                ->label(__('New version'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->visible(fn (PointsRuleSet $record) => ! $record->isDraft())
                ->action(function (PointsRuleSet $record) {
                    $copy = self::attempt(fn () => app(PointsService::class)->newVersion($record, auth()->user()));
                    $this->redirect(PointsRuleSetResource::getUrl('edit', ['record' => $copy]));
                }),
            Action::make('retire')
                ->label(__('Retire'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('danger')
                ->visible(fn (PointsRuleSet $record) => $record->isPublished())
                ->schema([Textarea::make('reason')->label(__('Reason (audited)'))->required()])
                ->action(function (PointsRuleSet $record, array $data) {
                    self::attempt(fn () => app(PointsService::class)->retire($record, auth()->user(), $data['reason']));
                    Notification::make()->success()->title(__('Points table retired'))->send();
                    $this->redirect(PointsRuleSetResource::getUrl('edit', ['record' => $record]));
                }),
            DeleteAction::make()->visible(fn (PointsRuleSet $record) => $record->isDraft()),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->record->isDraft() ? parent::getFormActions() : [];
    }
}
