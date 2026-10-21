<?php

namespace App\Providers\Filament;

use App\Enums\AdminNavigationGroup;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\PlatformOverview;
use App\Http\Middleware\SetAdminLocale;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * RIVALO staff admin panel at /admin.
 *
 * Security: session auth + CSRF, access limited to verified staff (User::canAccessPanel), and
 * TOTP multi-factor authentication REQUIRED for every account (with recovery codes).
 * Authorization inside the panel uses the same policies as the API.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return self::brand($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                AppAuthentication::make()->brandName('RIVALO Admin')->recoverable(),
                isRequired: true,
            )
            ->brandName('RIVALO Admin')
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            // Keyed like Filament's enum groups, but labelled lazily so they follow the staff
            // member's language (the panel is configured before the request locale is known).
            ->navigationGroups(collect(AdminNavigationGroup::cases())
                ->mapWithKeys(fn (AdminNavigationGroup $group) => [
                    $group->name => NavigationGroup::make()->label(fn () => $group->getLabel()),
                ])
                ->all())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                NeedsAttention::class,
                PlatformOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent so Livewire requests (forms, tables, actions) use the same language.
            ->middleware([SetAdminLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * RIVALO look shared by the admin panel and the Seller Hub: navy and lime, dark mode,
     * system font, and the English / Arabic switch (Arabic switches the panel to right-to-left).
     */
    public static function brand(Panel $panel): Panel
    {
        return $panel
            ->brandLogo(fn () => new HtmlString(Blade::render('<x-wordmark :height="22" />')))
            ->brandLogoHeight('1.5rem')
            ->favicon("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230B1020'/%3E%3Cpath d='M17 8 L32 52 L47 8' fill='none' stroke='%23C7FF3D' stroke-width='10'/%3E%3C/svg%3E")
            ->defaultThemeMode(ThemeMode::Dark)
            ->font('RIVALO System', provider: LocalFontProvider::class)
            ->colors([
                'primary' => Color::hex('#C7FF3D'),
                'gray' => self::navyGrayPalette(),
                'success' => Color::hex('#35D07F'),
                'warning' => Color::hex('#FFBF47'),
                'danger' => Color::hex('#FF626D'),
                'info' => Color::hex('#4DA3FF'),
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.theme'))
            // English / Arabic switch for staff (Arabic switches the panel to right-to-left).
            ->userMenuItems([
                Action::make('language')
                    ->label(fn () => app()->getLocale() === 'ar' ? 'English' : 'العربية')
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn () => route('admin.language', app()->getLocale() === 'ar' ? 'en' : 'ar')),
            ])
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_END, fn () => view('filament.language-switch'));
    }

    /**
     * Filament renders dark mode from the gray palette: 950 = page background, 900 = cards.
     * Mapped onto the RIVALO navy tokens (design-system/tokens.json).
     *
     * @return array<int, string>
     */
    private static function navyGrayPalette(): array
    {
        $hex = [
            50 => '#F5F7FA', 100 => '#E6EAF1', 200 => '#C9D0DD', 300 => '#A6B0C3', 400 => '#8792A8',
            500 => '#5F6B85', 600 => '#3A4868', 700 => '#2A3650', 800 => '#1F2B45', 900 => '#182238', 950 => '#0B1020',
        ];

        return array_map(fn (string $c) => Color::convertToOklch($c), $hex);
    }
}
