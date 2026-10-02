<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\User;
use App\Services\OfferEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Offer engine contract:
 * - Best ek offer lagti hai (discount + free lines server decide karta hai).
 * - Expired/paused/min-order-fail offers skip hoti hain.
 * - Free item unavailable ho to discount part phir bhi lagta hai.
 */
class OfferEngineTest extends TestCase
{
    use RefreshDatabase;

    private Category $cat;
    private MenuItem $thali;
    private MenuItem $jamun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cat = Category::create(['name' => 'Thali', 'slug' => 'thali', 'sort_order' => 0]);
        $this->thali = MenuItem::create([
            'category_id' => $this->cat->id,
            'name' => 'Special Thali',
            'price_label' => '₹120',
            'price_value' => 120,
            'half_price' => 120,
            'is_available' => true,
            'sort_order' => 0,
        ]);
        $this->jamun = MenuItem::create([
            'category_id' => $this->cat->id,
            'name' => 'Gulab Jamun',
            'price_label' => '₹30',
            'price_value' => 30,
            'half_price' => 30,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, portion: string, qty: int, unit: int, category: string}>
     */
    private function thaliLines(): array
    {
        return [[
            'id' => $this->thali->id,
            'name' => 'Special Thali',
            'portion' => 'single',
            'qty' => 1,
            'unit' => 120,
            'category' => 'thali',
        ]];
    }

    public function test_flat_plus_free_offer_applies(): void
    {
        $offer = Offer::create([
            'name' => 'Thali Fest',
            'target_type' => 'item',
            'target_value' => (string) $this->thali->id,
            'discount_type' => 'flat',
            'discount_value' => 20,
            'free_item_id' => $this->jamun->id,
            'is_active' => true,
        ]);

        $result = OfferEngine::bestFor($this->thaliLines(), 120);

        $this->assertNotNull($result['offer']);
        $this->assertSame($offer->id, $result['offer']->id);
        $this->assertSame(20, $result['discount']);
        $this->assertCount(1, $result['freeLines']);
        $this->assertSame($this->jamun->id, $result['freeLines'][0]['id']);
        $this->assertSame(0, $result['freeLines'][0]['unit']);
        $this->assertTrue($result['freeLines'][0]['free']);
    }

    public function test_item_target_touches_only_that_dish(): void
    {
        $special = MenuItem::create([
            'category_id' => $this->cat->id,
            'name' => 'Special Thali',
            'price_label' => '₹120',
            'price_value' => 120,
            'half_price' => 120,
            'is_available' => true,
            'sort_order' => 2,
        ]);

        // Offer SIRF Veg Thali (same category ki dusri dish) pe.
        $veg = MenuItem::create([
            'category_id' => $this->cat->id,
            'name' => 'Veg Thali',
            'price_label' => '₹80',
            'price_value' => 80,
            'half_price' => 80,
            'is_available' => true,
            'sort_order' => 3,
        ]);
        Offer::create([
            'name' => 'Veg Thali Deal',
            'target_type' => 'item',
            'target_value' => (string) $veg->id,
            'discount_type' => 'flat',
            'discount_value' => 20,
            'is_active' => true,
        ]);

        // Cart me Special Thali (offer wali nahi) — kuch nahi lagna chahiye.
        $result = OfferEngine::bestFor([[
            'id' => $special->id, 'name' => 'Special Thali', 'portion' => 'single',
            'qty' => 2, 'unit' => 120, 'category' => 'thali',
        ]], 240);
        $this->assertNull($result['offer']);
        $this->assertSame(0, $result['discount']);

        // Cart me Veg Thali — lagna chahiye.
        $result = OfferEngine::bestFor([[
            'id' => $veg->id, 'name' => 'Veg Thali', 'portion' => 'single',
            'qty' => 2, 'unit' => 80, 'category' => 'thali',
        ]], 160);
        $this->assertNotNull($result['offer']);
        $this->assertSame(20, $result['discount']);
    }

    public function test_flat_per_item_scales_with_qty(): void
    {
        Offer::create([
            'name' => 'Thali Fest',
            'target_type' => 'item',
            'target_value' => (string) $this->thali->id,
            'discount_type' => 'flat',
            'discount_value' => 20,
            'discount_scope' => 'item',
            'is_active' => true,
        ]);

        // 5 thali = 5 x ₹20 = ₹100 off (bill se zyada nahi).
        $result = OfferEngine::bestFor([[
            'id' => $this->thali->id, 'name' => 'Special Thali', 'portion' => 'single',
            'qty' => 5, 'unit' => 120, 'category' => 'thali',
        ]], 600);
        $this->assertSame(100, $result['discount']);
    }

    public function test_flat_per_order_stays_fixed(): void
    {
        Offer::create([
            'name' => 'Thali Fest',
            'target_type' => 'item',
            'target_value' => (string) $this->thali->id,
            'discount_type' => 'flat',
            'discount_value' => 20,
            'discount_scope' => 'order',
            'is_active' => true,
        ]);

        // 5 thali = sirf ₹20 off (ek baar).
        $result = OfferEngine::bestFor([[
            'id' => $this->thali->id, 'name' => 'Special Thali', 'portion' => 'single',
            'qty' => 5, 'unit' => 120, 'category' => 'thali',
        ]], 600);
        $this->assertSame(20, $result['discount']);
    }

    public function test_expired_offer_skipped(): void
    {
        Offer::create([
            'name' => 'Old Deal',
            'target_type' => 'all',
            'discount_type' => 'flat',
            'discount_value' => 50,
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $result = OfferEngine::bestFor($this->thaliLines(), 120);

        $this->assertNull($result['offer']);
        $this->assertSame(0, $result['discount']);
        $this->assertSame([], $result['freeLines']);
    }

    public function test_min_order_gate(): void
    {
        Offer::create([
            'name' => 'Big Cart Only',
            'target_type' => 'all',
            'discount_type' => 'flat',
            'discount_value' => 50,
            'min_order' => 500,
            'is_active' => true,
        ]);

        $result = OfferEngine::bestFor($this->thaliLines(), 120);

        $this->assertNull($result['offer']);
    }

    public function test_unavailable_free_item_keeps_discount(): void
    {
        $this->jamun->update(['is_available' => false]);

        Offer::create([
            'name' => 'Thali Fest',
            'target_type' => 'item',
            'target_value' => (string) $this->thali->id,
            'discount_type' => 'flat',
            'discount_value' => 20,
            'free_item_id' => $this->jamun->id,
            'is_active' => true,
        ]);

        $result = OfferEngine::bestFor($this->thaliLines(), 120);

        $this->assertSame(20, $result['discount']);
        $this->assertSame([], $result['freeLines']);
    }

    public function test_buy_two_get_flat_off(): void
    {
        Offer::create([
            'name' => 'Kadhai 2 Deal',
            'target_type' => 'item',
            'target_value' => (string) $this->thali->id,
            'discount_type' => 'flat',
            'discount_value' => 50,
            'discount_scope' => 'order',
            'min_qty' => 2,
            'is_active' => true,
        ]);

        $one = [[
            'id' => $this->thali->id, 'name' => 'Special Thali', 'portion' => 'single',
            'qty' => 1, 'unit' => 120, 'category' => 'thali',
        ]];
        $two = [[
            'id' => $this->thali->id, 'name' => 'Special Thali', 'portion' => 'single',
            'qty' => 2, 'unit' => 120, 'category' => 'thali',
        ]];

        // 1 thali = shart poori nahi, kuch nahi.
        $result = OfferEngine::bestFor($one, 120);
        $this->assertNull($result['offer']);
        $this->assertSame(0, $result['discount']);

        // 2 thali = ₹50 off.
        $result = OfferEngine::bestFor($two, 240);
        $this->assertNotNull($result['offer']);
        $this->assertSame(50, $result['discount']);
    }

    public function test_public_offers_lists_only_live(): void
    {
        Offer::create(['name' => 'Live Deal', 'target_type' => 'all', 'discount_type' => 'flat', 'discount_value' => 10, 'is_active' => true]);
        Offer::create(['name' => 'Paused Deal', 'target_type' => 'all', 'discount_type' => 'flat', 'discount_value' => 10, 'is_active' => false]);
        Offer::create(['name' => 'Old Deal', 'target_type' => 'all', 'discount_type' => 'flat', 'discount_value' => 10, 'ends_at' => now()->subDay(), 'is_active' => true]);

        $response = $this->getJson('/api/v1/offers');
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Live Deal'], $names);
    }

    public function test_duration_minutes_builds_ends_at(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/offers', [
            'name' => '2 Hour Deal',
            'target_type' => 'all',
            'discount_type' => 'flat',
            'discount_value' => 20,
            'duration_minutes' => 120,
            'is_active' => true,
        ]);
        $response->assertCreated();

        $offer = Offer::query()->firstWhere('name', '2 Hour Deal');
        $this->assertNotNull($offer);
        $this->assertSame(120, $offer->duration_minutes);
        $this->assertNotNull($offer->ends_at);
        // Start (abhi) + 120 min = end (±2 min ki chhoot).
        $this->assertEqualsWithDelta(now()->addMinutes(120)->timestamp, $offer->ends_at->timestamp, 120);

        // Public list me bhi live dikhni chahiye.
        $public = $this->getJson('/api/v1/offers');
        $public->assertOk();
        $this->assertContains('2 Hour Deal', collect($public->json('data'))->pluck('name')->all());
    }

    public function test_banner_carries_offer_countdown(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $offer = Offer::create([
            'name' => 'Thali Fest',
            'target_type' => 'all',
            'discount_type' => 'flat',
            'discount_value' => 20,
            'ends_at' => now()->addHours(2),
            'is_active' => true,
        ]);
        Banner::create([
            'title' => 'Thali Banner',
            'target' => 'thali',
            'offer_id' => $offer->id,
            'theme' => 'forest',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/banners');
        $response->assertOk();

        $first = collect($response->json('data'))->firstWhere('title', 'Thali Banner');
        $this->assertNotNull($first);
        $this->assertSame('Thali Fest', $first['offer_name']);
        $this->assertNotNull($first['offer_ends_at']);
    }

    public function test_banner_hides_when_linked_offer_expires(): void
    {
        $expired = Offer::create([
            'name' => 'Old Deal',
            'target_type' => 'all',
            'discount_type' => 'flat',
            'discount_value' => 10,
            'ends_at' => now()->subHour(),
            'is_active' => true,
        ]);
        Banner::create([
            'title' => 'Dead Banner',
            'target' => 'all',
            'offer_id' => $expired->id,
            'theme' => 'gold',
            'is_active' => true,
        ]);
        Banner::create([
            'title' => 'Plain Banner',
            'target' => 'all',
            'theme' => 'espresso',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/banners');
        $response->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertNotContains('Dead Banner', $titles);
        $this->assertContains('Plain Banner', $titles);
    }
}
