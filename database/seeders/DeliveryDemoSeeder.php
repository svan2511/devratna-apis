<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryTracking;
use Illuminate\Database\Seeder;

/**
 * One customer + one rider + one paid ready order near the shop.
 * Safe to run repeatedly; the demo order is reset each time.
 */
class DeliveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->updateOrCreate(
            ['phone' => '9000000001'],
            [
                'name' => 'Demo Customer',
                'phone_verified_at' => now(),
                'is_rider' => false,
            ],
        );

        $riderUser = User::query()->updateOrCreate(
            ['phone' => '9000000002'],
            [
                'name' => 'Demo Rider',
                'phone_verified_at' => now(),
                'is_rider' => true,
            ],
        );

        $rider = DeliveryPartner::query()->updateOrCreate(
            ['user_id' => $riderUser->id],
            [
                'vehicle_type' => 'bike',
                'vehicle_number' => 'UK07 DEMO',
                'is_active' => true,
                'is_online' => true,
                'last_lat' => config('services.shop.lat'),
                'last_lng' => config('services.shop.lng'),
                'last_location_at' => now(),
            ],
        );

        $shopLat = (float) config('services.shop.lat');
        $shopLng = (float) config('services.shop.lng');
        $customerLat = $shopLat + 0.0022;
        $customerLng = $shopLng + 0.0016;
        $distance = (int) round(DeliveryTracking::distanceMetres($shopLat, $shopLng, $customerLat, $customerLng));

        $order = Order::query()->firstOrNew([
            'user_id' => $customer->id,
            'kitchen_note' => 'Delivery demo order',
        ]);

        $order->fill([
            'delivery_partner_id' => $rider->id,
            'items' => [
                ['id' => 1, 'name' => 'Special Veg Thali', 'portion' => 'full', 'qty' => 2, 'unit' => 250],
            ],
            'delivery_address' => 'Demo House, Lane 3, Clement Town, Dehradun',
            'subtotal' => 500,
            'discount' => 0,
            'total' => 540,
            'status' => 'paid',
            'fulfillment_status' => 'ready',
            'paid_at' => now(),
            'ready_at' => now(),
            'rider_assigned_at' => now(),
            'rider_accepted_at' => null,
            'picked_up_at' => null,
            'arrived_at' => null,
            'delivered_at' => null,
            'customer_lat' => $customerLat,
            'customer_lng' => $customerLng,
            'distance_m' => $distance,
        ]);
        $order->save();

        $order->locations()->delete();

        $this->command?->info("Demo customer: {$customer->phone} / Demo rider: {$riderUser->phone}");
        $this->command?->info("Demo order: #{$order->id} (paid, ready, assigned) - {$distance}m from shop");
    }
}
