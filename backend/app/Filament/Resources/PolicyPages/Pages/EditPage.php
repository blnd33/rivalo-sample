<?php

namespace App\Filament\Resources\PolicyPages\Pages;

use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\PolicyPages\PageResource;
use App\Models\Page;
use App\Services\PageService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditPage extends EditRecord
{
    use HandlesDomainRules;

    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return $this->record->title();
    }

    public function getSubheading(): ?string
    {
        /** @var Page $page */
        $page = $this->record;
        $live = $page->isPublished() ? __('Live: version :v, published :date.', ['v' => $page->current_version, 'date' => $page->published_at?->format('d M Y')]) : __('Not published yet.');

        return $live.' '.PageResource::legalStatusLabel($page).'. '.($page->isPublished() && $page->hasUnpublishedChanges() ? __('The draft has changes players do not see yet.') : '');
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label(__('Save draft'));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return self::attempt(fn () => app(PageService::class)->saveDraft($record, auth()->user(), $data));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(__('Publish new version'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn () => auth()->user()->can('update', $this->record))
                ->modalDescription(fn (Page $record) => $record->requires_legal_review
                    ? __('Players will see the saved draft as a new version. Its legal review will show as pending until a super admin records a review for this version. Save the draft first.')
                    : __('Players will see the saved draft as a new version. Save the draft first.'))
                ->schema([Textarea::make('note')->label(__('What changed (kept with the version)'))->required()->maxLength(500)])
                ->action(function (array $data) {
                    $version = self::attempt(fn () => app(PageService::class)->publish($this->record, auth()->user(), $data['note']));
                    Notification::make()->success()->title(__('Version :v published', ['v' => $version->version]))->send();
                    $this->record->refresh();
                }),
            Action::make('legalReview')
                ->label(__('Record legal review'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn () => auth()->user()->can('recordLegalReview', $this->record) && $this->record->isPublished() && ! $this->record->legallyReviewed())
                ->modalDescription(fn (Page $record) => __('Record this only when a qualified reviewer has approved version :v in writing. The app will stop showing "Pending legal review" for this version.', ['v' => $record->current_version]))
                ->schema([
                    TextInput::make('reviewer')->label(__('Reviewer (name and firm)'))->required()->maxLength(160),
                    Textarea::make('note')->label(__('Note (for example the date and reference of the written approval)'))->required(),
                    Checkbox::make('confirm')->label(__('I confirm a qualified reviewer approved this exact version.'))->accepted(),
                ])
                ->action(function (array $data) {
                    self::attempt(fn () => app(PageService::class)->recordLegalReview($this->record, auth()->user(), $data['reviewer'], $data['note']));
                    Notification::make()->success()->title(__('Legal review recorded'))->send();
                    $this->record->refresh();
                }),
        ];
    }
}
