<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeliveryPartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryPartner>
 */
class DeliveryPartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->rider(),
            'vehicle_type' => 'bike',
            'vehicle_number' => 'UK07 '.fake()->numerify('####'),
            'is_active' => true,
            'is_online' => true,
            'last_lat' => 30.2710150,
            'last_lng' => 77.9926317,
            'last_location_at' => now(),
        ];
    }
}
