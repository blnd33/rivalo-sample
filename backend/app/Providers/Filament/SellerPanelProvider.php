<?php

namespace App\Providers\Filament;

use App\Filament\Seller\Pages\SellerDashboard;
use App\Http\Middleware\SetAdminLocale;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * RIVALO Seller Hub at /seller (Milestone 9), for approved outside sellers.
 *
 * Sellers sign in with their RIVALO account and must use an authenticator app, like staff.
 * Everything is limited to the seller's own shop: each screen filters by the shop, and policies
 * apply seller rules while the Seller Hub is the current panel (SellerContext). Sellers never
 * see customers' names, phone numbers or addresses. Like the admin panel, the Hub is in English
 * and Arabic.
 */
class SellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return AdminPanelProvider::brand($panel)
            ->id('seller')
            ->path('seller')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                AppAuthentication::make()->brandName('RIVALO Seller Hub')->recoverable(),
                isRequired: true,
            )
            ->brandName('RIVALO Seller Hub')
            ->discoverResources(in: app_path('Filament/Seller/Resources'), for: 'App\Filament\Seller\Resources')
            ->discoverPages(in: app_path('Filament/Seller/Pages'), for: 'App\Filament\Seller\Pages')
            ->pages([SellerDashboard::class])
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
            ->middleware([SetAdminLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
