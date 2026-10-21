<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Read-only, append-only record of privileged actions. */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('actor.username')->label(__('Actor'))->placeholder(__('System')),
            TextEntry::make('action'),
            TextEntry::make('subject_type')->formatStateUsing(fn (?string $state, AuditLog $record) => class_basename((string) $state).' #'.$record->subject_id),
            TextEntry::make('reason')->columnSpanFull()->placeholder(__('—')),
            KeyValueEntry::make('old_values')->label(__('Before'))->state(fn (AuditLog $r) => self::flatten($r->old_values)),
            KeyValueEntry::make('new_values')->label(__('After'))->state(fn (AuditLog $r) => self::flatten($r->new_values)),
            TextEntry::make('ip_address')->placeholder(__('—')),
            TextEntry::make('user_agent')->placeholder(__('—')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('actor.username')->label(__('Actor'))->placeholder(__('System'))->searchable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('subject_type')->label(__('Subject'))
                    ->formatStateUsing(fn (?string $state, AuditLog $record) => class_basename((string) $state).' #'.$record->subject_id),
                TextColumn::make('reason')->limit(60)->placeholder(__('—')),
            ])
            ->filters([
                Filter::make('action')
                    ->schema([TextInput::make('action')->placeholder(__('e.g. tournament.'))])
                    ->query(fn (Builder $query, array $data) => $query->when($data['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', $v.'%'))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /** @return array<string, string> */
    private static function flatten(?array $values): array
    {
        return collect($values ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v))->all();
    }

    public static function getModelLabel(): string
    {
        return __('Audit log entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Audit logs');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
