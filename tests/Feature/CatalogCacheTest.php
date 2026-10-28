<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\User;
use App\Services\CatalogCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Public catalogue cache contract:
 * - GET menu/banners/offers default Laravel cache store me cache hota hai.
 * - Admin ka koi bhi catalog write cache flush karta hai, isliye next poll
 *   turant fresh data deta hai (TTL ka wait nahi).
 */
class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menu_update_invalidates_cached_public_menu(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat-cache', 'sort_order' => 0]);
        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Old Dish',
            'price_label' => '₹100',
            'price_value' => 100,
            'half_price' => 100,
            'is_available' => true,
            'sort_order' => 0,
        ]);

        $this->getJson('/api/v1/menu')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.price_value', 100);

        $this->assertTrue(Cache::has(CatalogCache::MENU_KEY));

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/menu/items/{$item->id}", [
            'category_id' => $category->id,
            'name' => 'New Dish',
            'price_label' => '₹250',
            'price_value' => 250,
            'half_price' => 250,
        ])->assertOk();

        $this->assertFalse(Cache::has(CatalogCache::MENU_KEY));

        $this->getJson('/api/v1/menu')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.name', 'New Dish')
            ->assertJsonPath('data.0.items.0.price_value', 250);
    }

    public function test_admin_offer_change_invalidates_offers_and_banners_cache(): void
    {
        $admin = User::factory()->admin()->create();
        $offer = Offer::create([
            'name' => 'Live Offer',
            'target_type' => 'all',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
        ]);
        Banner::create([
            'title' => 'Fest Banner',
            'offer_id' => $offer->id,
            'theme' => 'espresso',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/offers')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/banners')->assertOk()->assertJsonCount(1, 'data');

        $this->assertTrue(Cache::has(CatalogCache::OFFERS_KEY));
        $this->assertTrue(Cache::has(CatalogCache::BANNERS_KEY));

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/offers/{$offer->id}/active", ['is_active' => false])
            ->assertOk();

        $this->assertFalse(Cache::has(CatalogCache::OFFERS_KEY));
        $this->assertFalse(Cache::has(CatalogCache::BANNERS_KEY));

        $this->getJson('/api/v1/offers')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/banners')->assertOk()->assertJsonCount(0, 'data');
    }
}
