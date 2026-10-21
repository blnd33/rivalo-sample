<?php

namespace App\Support;

use App\Models\Seller;
use Filament\Facades\Filament;

/**
 * The shop of the person using the Seller Hub (/seller), worked out per request.
 *
 * It exists only while the current Filament panel is the Seller Hub, so seller rules ("your own
 * listings, while editable") can never widen what the admin panel or the API allow. Filament sets
 * the current panel on page requests and on every Livewire request (persistent middleware), so
 * tables, actions and lazy widgets all see the same shop. Remembered on the request, not globally.
 */
final class SellerContext
{
    public const PANEL = 'seller';

    public static function current(): ?Seller
    {
        if (Filament::getCurrentPanel()?->getId() !== self::PANEL) {
            return null;
        }
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $attributes = request()->attributes;
        $key = 'rivalo.seller_context.'.$user->getAuthIdentifier();
        if (! $attributes->has($key)) {
            $attributes->set($key, Seller::where('user_id', $user->getAuthIdentifier())->first());
        }

        return $attributes->get($key);
    }

    public static function id(): ?int
    {
        return self::current()?->id;
    }

    /** True in the Seller Hub while the shop is active (suspended and closed shops are read-only). */
    public static function canWrite(): bool
    {
        return self::current()?->isActive() ?? false;
    }
}
