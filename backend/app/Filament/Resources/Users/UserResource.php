<?php

namespace App\Filament\Resources\Users;

use App\Enums\AdminNavigationGroup;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Community;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Account'))
                ->description(__('Profile content belongs to the player. Staff manage status and roles only.'))
                ->columns(2)
                ->schema([
                    TextInput::make('username')->disabled(),
                    TextInput::make('email')->disabled(),
                    TextInput::make('display_name')->disabled(),
                    TextInput::make('city')->disabled(),
                ]),
            Section::make(__('Status'))
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->options([
                            UserStatus::Active->value => UserStatus::Active->label(),
                            UserStatus::Suspended->value => UserStatus::Suspended->label(),
                        ])
                        ->disabled(fn (?User $record) => in_array($record?->status, [UserStatus::Deleted, UserStatus::PendingDeletion], true))
                        ->helperText(__('Suspension blocks sign-in and revokes app sessions. Deletion uses the player-initiated workflow.'))
                        ->native(false)
                        ->live(),
                    Textarea::make('suspension_reason')
                        ->label(__('Internal moderation note'))
                        ->helperText(__('Private: never shown in the app or public API.'))
                        ->required(fn (Get $get) => $get('status') === UserStatus::Suspended->value)
                        ->rows(3),
                ]),
            Section::make(__('Roles'))
                ->description(__('Staff roles require an authenticator app (MFA) at the next admin sign-in.'))
                ->visible(fn (?User $record) => $record && auth()->user()->can('assignRoles', $record))
                ->schema([
                    CheckboxList::make('roles')
                        ->relationship('roles', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => RoleName::tryFrom($record->name)?->label() ?? $record->name)
                        ->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('username')->searchable()->weight('semibold')
                    ->description(fn (User $record) => $record->is_sample_data ? new HtmlString('<span class="rivalo-sample-badge">'.e(__('Sample data')).'</span>') : null),
                TextColumn::make('display_name')->searchable()->toggleable(),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('roles.name')->badge()->label(__('Roles'))
                    ->formatStateUsing(fn (string $state) => RoleName::tryFrom($state)?->label() ?? $state),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())
                    ->color(fn (UserStatus $state) => match ($state) {
                        UserStatus::Active => 'success',
                        UserStatus::Suspended => 'danger',
                        UserStatus::PendingDeletion => 'warning',
                        UserStatus::Deleted => 'gray',
                    }),
                IconColumn::make('email_verified_at')->label(__('Verified'))->boolean()
                    ->getStateUsing(fn (User $record) => $record->email_verified_at !== null),
                IconColumn::make('app_authentication_secret')->label(__('MFA'))->boolean()
                    ->getStateUsing(fn (User $record) => filled($record->app_authentication_secret))
                    ->toggleable(),
                TextColumn::make('last_login_at')->since()->label(__('Last sign-in'))->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(UserStatus::options()),
                SelectFilter::make('roles')->relationship('roles', 'name')->multiple(),
                TernaryFilter::make('is_sample_data')->label(__('Sample data')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetMfa')
                    ->label(__('Reset MFA'))
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->visible(fn (User $record) => auth()->user()->hasRole(RoleName::SuperAdmin->value)
                        && filled($record->app_authentication_secret) && auth()->id() !== $record->id)
                    ->requiresConfirmation()
                    ->modalDescription(__('The user must enroll a new authenticator app at their next admin sign-in. Verify their identity out-of-band first.'))
                    ->schema([Textarea::make('reason')->required()->label(__('Reason (audited)'))])
                    ->action(function (User $record, array $data) {
                        $record->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
                        Audit::record('user.mfa_reset', $record, reason: $data['reason']);
                        Notification::make()->success()->title(__('MFA reset'))->send();
                    }),
            ]);
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
