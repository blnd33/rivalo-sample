<?php

namespace App\Filament\Resources\Sponsors;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Sponsors\Pages\CreateSponsor;
use App\Filament\Resources\Sponsors\Pages\EditSponsor;
use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Models\Sponsor;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Sponsor organisations. Contact details stay in the admin; only consenting sponsors appear in the app. */
class SponsorResource extends Resource
{
    protected static ?string $model = Sponsor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Finance;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Sponsor'))->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('website_url')->label(__('Website'))->url()->maxLength(255),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                FileUpload::make('logo_path')->label(__('Logo'))->image()->disk('public')->directory('sponsor-logos')->maxSize(2048)->columnSpanFull()
                    ->helperText(__('Use only a logo the sponsor has provided or approved.')),
            ]),
            Section::make(__('Visibility and contact'))->columnSpan(1)->schema([
                Toggle::make('is_public')->label(__('Show in the app'))
                    ->helperText(__('Turn on only when the sponsor has agreed to be shown. Amounts are never shown.')),
                Select::make('status')->options(['active' => __('Active'), 'inactive' => __('Inactive')])->default('active')->required()->native(false),
                TextInput::make('contact_name')->label(__('Contact name'))->maxLength(120),
                TextInput::make('contact_email')->label(__('Contact email'))->email()->maxLength(255),
                TextInput::make('contact_phone')->label(__('Contact phone'))->tel()->maxLength(40)
                    ->helperText(__('Contact details are private to finance staff.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo_path')->disk('public')->label(__('Logo'))->imageHeight(32),
                TextColumn::make('name')->searchable()->weight('semibold')
                    ->description(fn (Sponsor $record) => $record->is_sample_data ? __('Sample data') : null),
                IconColumn::make('is_public')->boolean()->label(__('Shown in app')),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => __(ucfirst($state)))
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('contributions_count')->counts('contributions')->label(__('Contributions')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Sponsor');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sponsors');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsors::route('/'),
            'create' => CreateSponsor::route('/create'),
            'edit' => EditSponsor::route('/{record}/edit'),
        ];
    }
}
