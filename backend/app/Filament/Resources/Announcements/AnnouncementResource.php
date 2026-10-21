<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\AdminNavigationGroup;
use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Models\Discipline;
use App\Models\Tournament;
use App\Services\Notifier;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Messages to players' inboxes (and phones, if each player allows that category). */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Community;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'title';

    public static function categoryOptions(): array
    {
        return [
            'news' => __('News and announcements'),
            'new_tournaments' => __('New tournaments'),
            'registration_updates' => __('Registration updates'),
            'venue_changes' => __('Venue and schedule changes'),
            'match_reminders' => __('Match reminders'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Message'))->columnSpan(2)->schema([
                TextInput::make('title')->required()->maxLength(120),
                Textarea::make('body')->required()->rows(6)->maxLength(2000),
                Select::make('link_kind')->label(__('Opens in the app'))->options([
                    'tournament' => __('Tournament'),
                    'news' => __('News article'),
                ])->placeholder(__('The message itself'))->live()->native(false),
                TextInput::make('link_ref')->label(__('Tournament or article slug'))->maxLength(120)
                    ->visible(fn (Get $get) => filled($get('link_kind')))->required(fn (Get $get) => filled($get('link_kind'))),
            ]),
            Section::make(__('Who receives it'))->columnSpan(1)->schema([
                Select::make('audience')->options(AnnouncementAudience::options())->required()->default('everyone')->live()->native(false),
                Select::make('audience_ref')->label(__('Discipline'))
                    ->options(fn () => Discipline::with('sport')->get()->mapWithKeys(fn ($d) => [$d->id => $d->sport->name.' · '.$d->name]))
                    ->visible(fn (Get $get) => $get('audience') === 'discipline')->required(fn (Get $get) => $get('audience') === 'discipline')->searchable(),
                Select::make('audience_ref')->label(__('Tournament'))
                    ->options(fn () => Tournament::orderByDesc('starts_at')->limit(200)->pluck('name', 'id'))
                    ->visible(fn (Get $get) => $get('audience') === 'tournament_entrants')->required(fn (Get $get) => $get('audience') === 'tournament_entrants')->searchable(),
                Select::make('locale')->label(__('Language'))->options(['en' => 'English', 'ar' => 'العربية', 'kmr' => 'Kurdî (Badînî)'])
                    ->placeholder(__('All app languages'))->native(false),
                Select::make('category')->options(self::categoryOptions())->required()->default('news')->native(false)
                    ->in(Notifier::ANNOUNCEMENT_CATEGORIES)
                    ->helperText(__('Players who turned this category off still see the message in their inbox, without a phone notification.')),
                Toggle::make('send_push')->label(__('Also send as a phone notification'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->weight('semibold')->limit(50),
                TextColumn::make('audience')->formatStateUsing(fn (AnnouncementAudience $state) => $state->label()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (AnnouncementStatus $state) => $state->label())
                    ->color(fn (AnnouncementStatus $state) => match ($state) {
                        AnnouncementStatus::Sent => 'success',
                        AnnouncementStatus::Scheduled, AnnouncementStatus::Sending => 'info',
                        AnnouncementStatus::Draft => 'warning',
                        AnnouncementStatus::Cancelled => 'gray',
                    }),
                TextColumn::make('send_at')->dateTime('d M Y H:i')->label(__('Scheduled for'))->placeholder('—'),
                TextColumn::make('sent_at')->dateTime('d M Y H:i')->label(__('Sent'))->placeholder('—'),
                TextColumn::make('recipients_count')->label(__('Recipients')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Announcement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Announcements');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
