<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Order status push contract:
 * - Token register/update idempotent hai.
 * - Bina token ke fulfillment update crash nahi karta (push skip).
 * - Customer order history me fulfillment + bill split aata hai.
 */
class OrderStatusPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_token_upsert_is_idempotent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/push-token', [
            'token' => 'ExponentPushToken[test123]',
            'platform' => 'android',
        ])->assertOk();

        $this->postJson('/api/v1/push-token', [
            'token' => 'ExponentPushToken[test123]',
            'platform' => 'android',
        ])->assertOk();

        $this->assertSame(1, PushToken::query()->where('token', 'ExponentPushToken[test123]')->count());
    }

    public function test_push_token_requires_auth(): void
    {
        $this->postJson('/api/v1/push-token', ['token' => 'ExponentPushToken[x]'])->assertStatus(401);
    }

    public function test_fulfillment_update_without_tokens_does_not_crash(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        Sanctum::actingAs($admin);

        $order = Order::create([
            'user_id' => $customer->id,
            'items' => [['id' => 1, 'name' => 'Dal Makhani', 'portion' => 'full', 'qty' => 3, 'unit' => 180]],
            'subtotal' => 540,
            'total' => 580,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/fulfillment", [
            'fulfillment_status' => 'preparing',
        ])->assertOk();

        $this->assertSame('preparing', $order->fresh()->fulfillment_status);
    }

    public function test_out_for_delivery_flow_and_push_text(): void
    {
        $this->assertNotNull(\App\Services\ExpoPushService::forFulfillment(1, 640, 'out_for_delivery'));

        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        Sanctum::actingAs($admin);

        $order = Order::create([
            'user_id' => $customer->id,
            'items' => [['id' => 1, 'name' => 'Dal Makhani', 'portion' => 'full', 'qty' => 3, 'unit' => 180]],
            'subtotal' => 540,
            'total' => 580,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/fulfillment", [
            'fulfillment_status' => 'out_for_delivery',
        ])->assertOk();

        $this->assertSame('out_for_delivery', $order->fresh()->fulfillment_status);

        // Kitchen scope me out_for_delivery wale bhi dikhe.
        $list = $this->getJson('/api/v1/admin/orders?scope=kitchen')->json('data.orders');
        $this->assertContains($order->id, array_column($list, 'id'));
    }

    public function test_customer_history_exposes_fulfillment_and_split(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Order::create([
            'user_id' => $user->id,
            'items' => [['id' => 1, 'name' => 'Dal Makhani', 'portion' => 'full', 'qty' => 3, 'unit' => 180]],
            'subtotal' => 540,
            'total' => 580,
            'status' => 'paid',
            'fulfillment_status' => 'ready',
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/orders');
        $response->assertOk();

        $first = $response->json('data.0');
        $this->assertSame('ready', $first['fulfillment_status']);
        $this->assertSame(540, $first['subtotal']);
        $this->assertSame(580, $first['total']);
    }
}
