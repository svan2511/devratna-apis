<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'items' => [
                ['id' => 1, 'name' => 'Special Veg Thali', 'portion' => 'full', 'qty' => 2, 'unit' => 250],
            ],
            'delivery_address' => 'H.No 12, Lane 3, Clement Town, Dehradun',
            'subtotal' => 500,
            'discount' => 0,
            'total' => 540,
            'status' => 'paid',
            'fulfillment_status' => 'ready',
            'paid_at' => now(),
            'ready_at' => now(),
            'customer_lat' => 30.2735000,
            'customer_lng' => 77.9955000,
            'distance_m' => 350,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => 'paid',
            'fulfillment_status' => 'ready',
            'paid_at' => now(),
            'ready_at' => now(),
        ]);
    }

    public function assignedTo(DeliveryPartner $rider): static
    {
        return $this->state(fn (): array => [
            'delivery_partner_id' => $rider->id,
            'rider_assigned_at' => now(),
        ]);
    }

    public function pickedUp(): static
    {
        return $this->state(fn (): array => [
            'fulfillment_status' => 'out_for_delivery',
            'rider_accepted_at' => now(),
            'picked_up_at' => now(),
        ]);
    }
}
