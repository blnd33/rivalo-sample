<?php

namespace App\Filament\Widgets;

use App\Enums\RegistrationStatus;
use App\Enums\TournamentStatus;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Support\FeatureFlags;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard counts computed from the database. Seeded development rows are counted separately
 * and labeled, so sample data is never presented as real adoption.
 */
class PlatformOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getHeading(): ?string
    {
        return __('Platform overview');
    }

    protected function getDescription(): ?string
    {
        return __('Live counts from the database. Sample (seeded) records are shown separately.');
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        $players = User::where('status', UserStatus::Active->value);
        $realPlayers = (clone $players)->where('is_sample_data', false)->count();
        $samplePlayers = (clone $players)->where('is_sample_data', true)->count();

        $tournaments = Tournament::query()->manageableBy($user);
        $openRegistration = (clone $tournaments)->where('status', TournamentStatus::RegistrationOpen->value);
        $realOpen = (clone $openRegistration)->where('is_sample_data', false)->count();
        $sampleOpen = (clone $openRegistration)->where('is_sample_data', true)->count();

        $reserved = TournamentEntry::query()
            ->whereIn('tournament_id', (clone $tournaments)->select('id'))
            ->where('registration_status', RegistrationStatus::Reserved->value);
        $realReserved = (clone $reserved)->whereHas('tournament', fn ($q) => $q->where('is_sample_data', false))->count();
        $sampleReserved = (clone $reserved)->whereHas('tournament', fn ($q) => $q->where('is_sample_data', true))->count();
        $waitlisted = TournamentEntry::query()
            ->whereIn('tournament_id', (clone $tournaments)->where('is_sample_data', false)->select('id'))
            ->where('registration_status', RegistrationStatus::Waitlisted->value)
            ->count();

        $teams = Team::where('status', 'active');

        return [
            Stat::make(__('Active players'), number_format($realPlayers))
                ->description($samplePlayers ? __('+ :count sample accounts (not real)', ['count' => $samplePlayers]) : __('No sample accounts'))
                ->descriptionColor($samplePlayers ? 'warning' : 'gray'),
            Stat::make(__('Registration open'), number_format($realOpen))
                ->description($sampleOpen ? __('+ :count sample tournaments', ['count' => $sampleOpen]) : __('Tournaments you can manage'))
                ->descriptionColor($sampleOpen ? 'warning' : 'gray'),
            Stat::make(__('Reserved entries'), number_format($realReserved))
                ->description(__(':count on waitlists', ['count' => $waitlisted]).($sampleReserved ? ' · '.__('+ :count in sample tournaments', ['count' => $sampleReserved]) : ''))
                ->descriptionColor($sampleReserved ? 'warning' : 'gray'),
            Stat::make(__('Active teams'), number_format((clone $teams)->where('is_sample_data', false)->count()))
                ->description(__(':count sample teams', ['count' => (clone $teams)->where('is_sample_data', true)->count()])),
            Stat::make(__('Paid entry'), FeatureFlags::enabled(FeatureFlags::PAID_ENTRY) ? __('Enabled') : __('Disabled'))
                ->description(__('Compliance flag (decision D7)'))
                ->descriptionColor(FeatureFlags::enabled(FeatureFlags::PAID_ENTRY) ? 'warning' : 'gray'),
        ];
    }
}
