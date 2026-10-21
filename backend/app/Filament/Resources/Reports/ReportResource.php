<?php

namespace App\Filament\Resources\Reports;

use App\Enums\AdminNavigationGroup;
use App\Enums\ModerationAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReportSubject;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Models\Report;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Moderation queue. Reports come from players in the app; staff dismiss them or act on them
 * through ModerationService. Reports can't be created, edited or deleted here.
 */
class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Community;

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $open = Report::open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Open reports');
    }

    public static function statusColor(ReportStatus $state): string
    {
        return match ($state) {
            ReportStatus::Open => 'danger',
            ReportStatus::Actioned => 'success',
            ReportStatus::Dismissed => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Report'))->columnSpan(2)->columns(2)->schema([
                TextEntry::make('subject')->label(__('Reported'))->state(fn (Report $r) => $r->subjectLabel())
                    ->url(fn (Report $r) => match ($r->subject_type) {
                        ReportSubject::User => $r->subject_user_id && auth()->user()->can('users.manage') ? route('filament.admin.resources.users.edit', $r->subject_user_id) : null,
                        ReportSubject::Team => $r->subject_team_id ? route('filament.admin.resources.teams.view', $r->subject_team_id) : null,
                        default => null,
                    })->weight('semibold'),
                TextEntry::make('reason')->formatStateUsing(fn (ReportReason $state) => $state->label())->badge()->color('warning'),
                TextEntry::make('details')->label(__('What the player wrote'))->columnSpanFull()->placeholder(__('No details'))->prose(),
                TextEntry::make('reporter.username')->label(__('Reported by'))->prefix('@')->placeholder(__('Deleted account'))
                    ->helperText(__('Private: never shown to the reported player.')),
                TextEntry::make('created_at')->dateTime('d M Y H:i')->label(__('Received')),
                TextEntry::make('reporter_history')->label(__('Reports by this player'))
                    ->state(fn (Report $r) => $r->reporter_id ? __(':total in total, :dismissed dismissed', [
                        'total' => Report::where('reporter_id', $r->reporter_id)->count(),
                        'dismissed' => Report::where('reporter_id', $r->reporter_id)->where('status', ReportStatus::Dismissed->value)->count(),
                    ]) : '—'),
                TextEntry::make('subject_history')->label(__('Other reports about the same subject'))
                    ->state(fn (Report $r) => $r->dedupe_key ? __(':open open, :actioned actioned before', [
                        'open' => Report::open()->where('dedupe_key', $r->dedupe_key)->whereKeyNot($r->id)->count(),
                        'actioned' => Report::where('dedupe_key', $r->dedupe_key)->where('status', ReportStatus::Actioned->value)->count(),
                    ]) : '—'),
                KeyValueEntry::make('context')->label(__('App details'))->visible(fn (Report $r) => $r->subject_type === ReportSubject::App)->columnSpanFull(),
            ]),
            Section::make(__('When it was reported'))->columnSpan(1)
                ->description(__('What the profile or team looked like at the time. It may have changed since.'))
                ->visible(fn (Report $r) => $r->subject_type !== ReportSubject::App)
                ->schema([
                    ImageEntry::make('snapshot_image')->label(__('Photo'))->circular()
                        ->state(fn (Report $r) => self::snapshotImage($r))->placeholder(__('No photo, or it was removed since')),
                    TextEntry::make('snapshot.display_name')->label(__('Display name'))->placeholder('—')
                        ->visible(fn (Report $r) => $r->subject_type === ReportSubject::User),
                    TextEntry::make('snapshot.city')->label(__('City'))->placeholder('—')
                        ->visible(fn (Report $r) => $r->subject_type === ReportSubject::User),
                    TextEntry::make('snapshot.name')->label(__('Team name'))
                        ->visible(fn (Report $r) => $r->subject_type === ReportSubject::Team),
                ]),
            Section::make(__('Decision'))->columnSpanFull()->columns(3)
                ->visible(fn (Report $r) => ! $r->isOpen())
                ->schema([
                    TextEntry::make('status')->badge()->formatStateUsing(fn (ReportStatus $state) => $state->label())->color(fn (ReportStatus $state) => self::statusColor($state)),
                    TextEntry::make('action')->formatStateUsing(fn (?ModerationAction $state) => $state?->label())->placeholder('—'),
                    TextEntry::make('handledBy.username')->label(__('Handled by'))->prefix('@')
                        ->suffix(fn (Report $r) => ' · '.$r->handled_at?->format('d M Y H:i')),
                    TextEntry::make('resolution_note')->label(__('Internal note'))->columnSpanFull(),
                ]),
        ]);
    }

    /** Snapshot photo if the file still exists (removed photos are deleted from storage). */
    private static function snapshotImage(Report $r): ?string
    {
        $path = $r->snapshot['avatar_path'] ?? $r->snapshot['logo_path'] ?? null;

        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['reporter', 'handledBy']))
            ->columns([
                TextColumn::make('created_at')->since()->label(__('Received'))->sortable(),
                TextColumn::make('subject')->label(__('Reported'))->state(fn (Report $r) => $r->subjectLabel())->weight('semibold')
                    ->description(fn (Report $r) => $r->subject_type->label()),
                TextColumn::make('reason')->formatStateUsing(fn (ReportReason $state) => $state->label())->badge()->color('warning'),
                TextColumn::make('details')->limit(60)->placeholder('—')->toggleable(),
                TextColumn::make('reporter.username')->label(__('Reported by'))->prefix('@')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ReportStatus $state) => $state->label())
                    ->color(fn (ReportStatus $state) => self::statusColor($state)),
                TextColumn::make('action')->formatStateUsing(fn (?ModerationAction $state) => $state?->label())->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReportStatus::options())->default(ReportStatus::Open->value),
                SelectFilter::make('subject_type')->label(__('Type'))->options(ReportSubject::options()),
                SelectFilter::make('reason')->options(ReportReason::options()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Reports');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'view' => ViewReport::route('/{record}'),
        ];
    }
}
