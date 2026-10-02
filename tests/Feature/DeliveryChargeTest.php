<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DeliveryCharge;
use Tests\TestCase;

/**
 * Delivery charge contract:
 * - fixed mode = hamesha flat charge.
 * - distance mode = free limit tak base, uske baad har 500m (ya hisse) pe extra.
 */
class DeliveryChargeTest extends TestCase
{
    private array $distanceShop = [
        'delivery_mode' => 'distance',
        'delivery_charge' => 40,
        'delivery_base' => 40,
        'delivery_free_m' => 1000,
        'delivery_per_500m' => 4,
    ];

    public function test_fixed_mode_ignores_distance(): void
    {
        $shop = ['delivery_mode' => 'fixed', 'delivery_charge' => 40];

        $this->assertSame(40, DeliveryCharge::for(200, $shop));
        $this->assertSame(40, DeliveryCharge::for(5000, $shop));
    }

    public function test_distance_slabs(): void
    {
        $cases = [
            0 => 40,
            800 => 40,
            1000 => 40,
            1001 => 44,
            1500 => 44,
            2000 => 48,
            2500 => 52,
            3000 => 56,
            3500 => 60,
            4000 => 64,
            4500 => 68,
            5000 => 72,
        ];

        foreach ($cases as $metres => $expected) {
            $this->assertSame(
                $expected,
                DeliveryCharge::for($metres, $this->distanceShop),
                "Failed at {$metres}m"
            );
        }
    }

    public function test_unknown_mode_falls_back_to_fixed(): void
    {
        $this->assertSame(40, DeliveryCharge::for(3000, ['delivery_charge' => 40]));
        $this->assertSame(0, DeliveryCharge::for(3000, []));
    }
}
