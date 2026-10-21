<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Enums\MembershipStatus;
use App\Enums\TeamRole;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Full membership history (current and past members). Read-only. */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Membership history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('joined_at', 'desc')
            ->columns([
                TextColumn::make('user.username')->label(__('Player'))->searchable(),
                TextColumn::make('role')->badge()->formatStateUsing(fn (TeamRole $state) => $state->label()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (MembershipStatus $state) => $state->label())
                    ->color(fn (MembershipStatus $state) => $state === MembershipStatus::Active ? 'success' : 'gray'),
                TextColumn::make('joined_at')->dateTime(),
                TextColumn::make('left_at')->dateTime()->placeholder(__('—')),
                TextColumn::make('left_reason')->placeholder(__('—')),
            ])
            ->filters([
                SelectFilter::make('status')->options(MembershipStatus::options()),
            ]);
    }
}
