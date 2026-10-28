<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\OrderLocation;
use App\Models\PushToken;
use App\Models\User;
use Database\Seeders\DeliveryDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assigns_rider_customer_tracks_and_rider_delivers(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Asha']);
        $riderUser = User::factory()->rider()->create(['name' => 'Ravi']);
        $rider = DeliveryPartner::factory()->create(['user_id' => $riderUser->id]);
        $order = Order::factory()->create(['user_id' => $customer->id]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/orders/{$order->id}/assign-rider", [
            'delivery_partner_id' => $rider->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.order.rider.name', 'Ravi')
            ->assertJsonPath('data.order.fulfillment_status', 'ready');

        $order->refresh();
        $this->assertSame($rider->id, $order->delivery_partner_id);
        $this->assertNotNull($order->rider_assigned_at);

        Sanctum::actingAs($riderUser);
        $this->getJson('/api/v1/rider/assignments')
            ->assertOk()
            ->assertJsonPath('data.active_order.id', $order->id)
            ->assertJsonPath('data.rider.is_online', true);

        $this->postJson("/api/v1/rider/orders/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.order.fulfillment_status', 'ready');

        $this->postJson("/api/v1/rider/orders/{$order->id}/location", [
            'latitude' => 30.2720000,
            'longitude' => 77.9930000,
            'accuracy_m' => 8,
        ])->assertCreated();

        Sanctum::actingAs($customer);
        $this->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.rider.name', 'Ravi')
            ->assertJsonPath('data.order.rider.name', 'Ravi')
            ->assertJsonPath('data.is_stale', false)
            ->assertJsonPath('data.arrived', false)
            ->assertJsonPath('data.order.fulfillment_status', 'ready');

        Sanctum::actingAs($riderUser);
        $this->postJson("/api/v1/rider/orders/{$order->id}/pickup")->assertOk();
        $this->postJson("/api/v1/rider/orders/{$order->id}/deliver")->assertOk();

        Sanctum::actingAs($customer);
        $this->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.order.fulfillment_status', 'delivered')
            ->assertJsonPath('data.eta_minutes', 0)
            ->assertJsonPath('data.distance_to_customer_m', 0);

        $this->assertSame(1, OrderLocation::query()->where('order_id', $order->id)->count());
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_rider_arrival_is_marked_when_location_reaches_customer(): void
    {
        $customer = User::factory()->create();
        $riderUser = User::factory()->rider()->create();
        $rider = DeliveryPartner::factory()->create(['user_id' => $riderUser->id]);
        $order = Order::factory()->assignedTo($rider)->pickedUp()->create([
            'user_id' => $customer->id,
            'customer_lat' => 30.2735000,
            'customer_lng' => 77.9955000,
        ]);

        Sanctum::actingAs($riderUser);
        $this->postJson("/api/v1/rider/orders/{$order->id}/location", [
            'latitude' => 30.2735100,
            'longitude' => 77.9955100,
            'accuracy_m' => 8,
        ])->assertCreated();

        $this->assertNotNull($order->fresh()->arrived_at);

        Sanctum::actingAs($customer);
        $this->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.arrived', true)
            ->assertJsonPath('data.order.arrived_at', fn ($value) => $value !== null);
    }

    public function test_customer_cannot_use_rider_endpoints(): void
    {
        $customer = User::factory()->create();

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/rider/assignments')->assertForbidden();
    }

    public function test_rider_cannot_be_assigned_to_order_that_is_not_ready(): void
    {
        $admin = User::factory()->admin()->create();
        $rider = DeliveryPartner::factory()->create();
        $order = Order::factory()->create(['fulfillment_status' => 'preparing']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/orders/{$order->id}/assign-rider", [
            'delivery_partner_id' => $rider->id,
        ])->assertStatus(422);

        $this->assertNull($order->fresh()->delivery_partner_id);
    }

    public function test_rider_cannot_be_assigned_to_unpaid_order(): void
    {
        $admin = User::factory()->admin()->create();
        $rider = DeliveryPartner::factory()->create();
        $order = Order::factory()->create(['status' => 'pending']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/orders/{$order->id}/assign-rider", [
            'delivery_partner_id' => $rider->id,
        ])->assertStatus(422);
    }

    public function test_picked_up_order_cannot_be_reassigned_or_unassigned(): void
    {
        $admin = User::factory()->admin()->create();
        $rider = DeliveryPartner::factory()->create();
        $otherRider = DeliveryPartner::factory()->create();
        $order = Order::factory()->pickedUp()->create(['delivery_partner_id' => $rider->id]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/orders/{$order->id}/assign-rider", [
            'delivery_partner_id' => $otherRider->id,
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/orders/{$order->id}/unassign-rider")->assertStatus(422);
        $this->assertSame($rider->id, $order->fresh()->delivery_partner_id);
    }

    public function test_simulation_command_moves_rider_and_delivers_order(): void
    {
        $rider = DeliveryPartner::factory()->create();
        $order = Order::factory()->create(['delivery_partner_id' => $rider->id]);

        $this->artisan('dev:simulate-delivery', [
            'order' => $order->id,
            '--steps' => 3,
            '--interval' => 0,
            '--deliver' => true,
        ])->assertSuccessful();

        $order->refresh();
        $this->assertSame('delivered', $order->fulfillment_status);
        $this->assertNotNull($order->picked_up_at);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame(3, $order->locations()->count());
        $this->assertNotNull($rider->fresh()->last_location_at);
    }

    public function test_demo_seeder_creates_trackable_order(): void
    {
        $this->seed(DeliveryDemoSeeder::class);

        $order = Order::query()->where('kitchen_note', 'Delivery demo order')->firstOrFail();

        $this->assertSame('paid', $order->status);
        $this->assertSame('ready', $order->fulfillment_status);
        $this->assertNotNull($order->delivery_partner_id);
        $this->assertNotNull($order->customer_lat);
        $this->assertGreaterThan(0, $order->distance_m);
    }

    public function test_offline_rider_cannot_be_assigned(): void
    {
        $admin = User::factory()->admin()->create();
        $rider = DeliveryPartner::factory()->create(['is_online' => false]);
        $order = Order::factory()->create();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/orders/{$order->id}/assign-rider", [
            'delivery_partner_id' => $rider->id,
        ])->assertStatus(422);

        $this->assertNull($order->fresh()->delivery_partner_id);
    }

    public function test_customer_is_notified_only_after_rider_accepts_with_rider_details(): void
    {
        Http::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Asha']);
        $riderUser = User::factory()->rider()->create(['name' => 'Ravi']);
        $rider = DeliveryPartner::factory()->create([
            'user_id' => $riderUser->id,
            'vehicle_type' => 'bike',
            'vehicle_number' => 'UK07 1234',
        ]);
        $order = Order::factory()->create(['user_id' => $customer->id]);

        PushToken::create(['user_id' => $customer->id, 'token' => 'ExponentPushToken[customer-asha]']);
        PushToken::create(['user_id' => $riderUser->id, 'token' => 'ExponentPushToken[rider-ravi]']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/orders/{$order->id}/assign-rider", [
            'delivery_partner_id' => $rider->id,
        ])->assertOk();

        // Assign hote hi sirf rider ko push — customer ko abhi nahi.
        Http::assertSent(fn ($request) => str_contains($request->body(), 'ExponentPushToken[rider-ravi]'));
        Http::assertNotSent(fn ($request) => str_contains($request->body(), 'ExponentPushToken[customer-asha]'));

        Sanctum::actingAs($riderUser);
        $this->postJson("/api/v1/rider/orders/{$order->id}/accept")->assertOk();

        // Accept ke baad customer ko rider ki details ke sath push.
        Http::assertSent(fn ($request) => str_contains($request->body(), 'ExponentPushToken[customer-asha]')
            && str_contains($request->body(), 'Ravi')
            && str_contains($request->body(), 'UK07 1234'));
    }
}
