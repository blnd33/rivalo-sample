<?php

namespace App\Support;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

/**
 * Server-controlled feature flags. Flags gate features that need compliance approval or a
 * staged rollout. They are never used to hide functionality from app-store review.
 */
class FeatureFlags
{
    /** Paid-entry participation. Disabled until legal, payment and app-store eligibility is established (D7). */
    public const PAID_ENTRY = 'paid_entry_participation';

    /** Commerce tab and store APIs (Milestone 8). */
    public const STORE = 'store_enabled';

    /** Approved outside sellers' listings and seller applications (Milestone 9). Needs the store on. */
    public const MARKETPLACE = 'marketplace_enabled';

    /** Push delivery through the configured provider (Milestone 6). */
    public const PUSH = 'push_notifications';

    private const CACHE_KEY = 'rivalo.feature_flags';

    public static function enabled(string $key): bool
    {
        return (bool) (self::all()[$key] ?? false);
    }

    /** @return array<string, bool> */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, fn () => FeatureFlag::query()->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all());
    }

    /** @return array<string, bool> Flags the mobile app may read. */
    public static function public(): array
    {
        return FeatureFlag::query()->where('is_public', true)->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
