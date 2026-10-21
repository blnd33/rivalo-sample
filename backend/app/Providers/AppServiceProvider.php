<?php

namespace App\Providers;

use App\Support\Push\FcmPushProvider;
use App\Support\Push\LogPushProvider;
use App\Support\Push\PushProvider;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phone notifications: "log" (default, nothing leaves the server) or "fcm" (Firebase).
        $this->app->bind(PushProvider::class, function () {
            $config = config('rivalo.push');

            return ($config['driver'] ?? 'log') === 'fcm' && filled($config['fcm_project_id']) && filled($config['fcm_credentials_path'])
                ? new FcmPushProvider($config['fcm_project_id'], $config['fcm_credentials_path'])
                : new LogPushProvider;
        });

        //
    }

    public function boot(): void
    {
        // Catch N+1 lazy loading and silently discarded attributes during development and tests.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        $this->configureRateLimiting();
        $this->configureAdminTranslations();
    }

    /**
     * Admin panel labels Filament generates from field names ("Starts at", "Max entrants") are
     * passed through the translator, so lang/ar.json can provide the Arabic text.
     */
    private function configureAdminTranslations(): void
    {
        Field::configureUsing(fn (Field $field) => $field->translateLabel());
        Column::configureUsing(fn (Column $column) => $column->translateLabel());
        BaseFilter::configureUsing(fn (BaseFilter $filter) => $filter->translateLabel());
        Entry::configureUsing(fn (Entry $entry) => $entry->translateLabel());
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('login')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('auth-register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('auth-email', fn (Request $request) => Limit::perHour(5)->by(($request->user()?->id ?: mb_strtolower((string) $request->input('email'))).'|'.$request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('orders', fn (Request $request) => [
            Limit::perMinute(3)->by($request->user()?->id ?: $request->ip()),
            Limit::perDay(20)->by($request->user()?->id ?: $request->ip()),
        ]);

        // Seller applications (Milestone 9): a handful a day is plenty (form errors count too).
        RateLimiter::for('applications', fn (Request $request) => [
            Limit::perDay(5)->by($request->user()?->id ?: $request->ip()),
        ]);

        RateLimiter::for('reports', fn (Request $request) => [
            Limit::perMinute(3)->by($request->user()?->id ?: $request->ip()),
            Limit::perDay(30)->by($request->user()?->id ?: $request->ip()),
        ]);
    }
}
