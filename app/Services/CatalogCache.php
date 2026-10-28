<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Public catalogue cache — menu, banners, offers.
 *
 * Default Laravel cache store use hota hai (abhi database). Admin write ke
 * baad flush hota hai taaki next poll turant fresh data de; direct DB edit
 * ho to TTL (15–30s) safety net hai.
 */
final class CatalogCache
{
    public const MENU_KEY = 'catalog:menu:v1';

    public const BANNERS_KEY = 'catalog:banners:v1';

    public const OFFERS_KEY = 'catalog:offers:v1';

    private const MENU_TTL_SECONDS = 30;

    private const BANNERS_TTL_SECONDS = 15;

    private const OFFERS_TTL_SECONDS = 15;

    /**
     * @param  Closure(): array<int, array<string, mixed>>  $callback
     * @return array<int, array<string, mixed>>
     */
    public static function rememberMenu(Closure $callback): array
    {
        return Cache::remember(self::MENU_KEY, self::MENU_TTL_SECONDS, $callback);
    }

    /**
     * @param  Closure(): array<int, array<string, mixed>>  $callback
     * @return array<int, array<string, mixed>>
     */
    public static function rememberBanners(Closure $callback): array
    {
        return Cache::remember(self::BANNERS_KEY, self::BANNERS_TTL_SECONDS, $callback);
    }

    /**
     * @param  Closure(): array<int, array<string, mixed>>  $callback
     * @return array<int, array<string, mixed>>
     */
    public static function rememberOffers(Closure $callback): array
    {
        return Cache::remember(self::OFFERS_KEY, self::OFFERS_TTL_SECONDS, $callback);
    }

    /**
     * Menu, banner ya offer ka koi bhi admin write — sab catalogue keys clear.
     * Writes rare hain, isliye dependencies alag-alag track karne se better
     * hai ek saath flush karna (missed invalidation ka risk zero).
     */
    public static function flush(): void
    {
        Cache::forget(self::MENU_KEY);
        Cache::forget(self::BANNERS_KEY);
        Cache::forget(self::OFFERS_KEY);
    }
}
