<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Shop open/close + availability contract:
 * - Public menu me unavailable dishes bhi aate hain (is_available=false flag ke sath).
 * - Shop band ho to order placement 422 pe rukta hai (Razorpay tak pahunchta hi nahi).
 */
class ShopAndMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_menu_includes_unavailable_items_with_flag(): void
    {
        $cat = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat', 'sort_order' => 0]);
        MenuItem::create([
            'category_id' => $cat->id,
            'name' => 'Off Dish',
            'price_label' => '₹100',
            'price_value' => 100,
            'half_price' => 100,
            'is_available' => false,
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/v1/menu');
        $response->assertOk();

        $items = collect($response->json('data'))->flatMap(fn ($c) => $c['items']);
        $found = $items->firstWhere('name', 'Off Dish');

        $this->assertNotNull($found, 'Unavailable dish must still be listed.');
        $this->assertFalse((bool) $found['is_available']);
    }

    public function test_store_blocked_when_shop_closed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        cache(['shop_open' => false]);

        $response = $this->postJson('/api/v1/orders', [
            'items' => [['id' => 1, 'portion' => 'half', 'qty' => 1]],
            'lat' => 30.2710150,
            'lng' => 77.9926317,
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('closed', strtolower($response->json('message')));
    }

    public function test_store_allowed_when_shop_open_reaches_pricing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        cache(['shop_open' => true]);

        // Bina menu items ke pricing fail hogi — par "closed" wali line nahi aani chahiye.
        $response = $this->postJson('/api/v1/orders', [
            'items' => [['id' => 999999, 'portion' => 'half', 'qty' => 1]],
            'lat' => 30.2710150,
            'lng' => 77.9926317,
        ]);

        $this->assertStringNotContainsString('closed', strtolower($response->json('message') ?? ''));
    }

    public function test_shop_status_is_public(): void
    {
        cache(['shop_open' => true]);

        $this->getJson('/api/v1/shop-status')
            ->assertOk()
            ->assertJsonPath('data.shop_open', true)
            ->assertJsonStructure(['data' => ['shop_open', 'min_order', 'delivery_charge', 'radius_m']]);
    }

    public function test_quarter_portion_maps_to_smallest_price(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        cache(['shop_open' => true]);

        $cat = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat-q', 'sort_order' => 0]);
        $item = MenuItem::create([
            'category_id' => $cat->id,
            'name' => 'Test Dahi',
            'price_label' => '₹30 / ₹50 / ₹90',
            'price_value' => 30,
            'half_price' => 30,
            'mid_price' => 50,
            'full_price' => 90,
            'is_available' => true,
            'sort_order' => 0,
        ]);

        // 20 x 30 = 600 (min order 500 cross). Gateway ka result jo bhi ho
        // (200 sandbox / 503 no-keys) — order me unit smallest hona chahiye.
        $response = $this->postJson('/api/v1/orders', [
            'items' => [['id' => $item->id, 'portion' => 'quarter', 'qty' => 20]],
            'lat' => 30.2710150,
            'lng' => 77.9926317,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 503]);
        $order = \App\Models\Order::query()->where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertSame(30, $order->items[0]['unit']);
        $this->assertSame('quarter', $order->items[0]['portion']);
    }

    public function test_half_portion_on_three_tier_maps_to_middle_price(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        cache(['shop_open' => true]);

        $cat = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat-h', 'sort_order' => 0]);
        $item = MenuItem::create([
            'category_id' => $cat->id,
            'name' => 'Test Dahi 2',
            'price_label' => '₹30 / ₹50 / ₹90',
            'price_value' => 30,
            'half_price' => 30,
            'mid_price' => 50,
            'full_price' => 90,
            'is_available' => true,
            'sort_order' => 0,
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'items' => [['id' => $item->id, 'portion' => 'half', 'qty' => 10]],
            'lat' => 30.2710150,
            'lng' => 77.9926317,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 503]);
        $order = \App\Models\Order::query()->where('user_id', $user->id)->latest()->first();
        $this->assertSame(50, $order->items[0]['unit']);
    }
}
