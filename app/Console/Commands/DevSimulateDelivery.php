<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderLocation;
use App\Services\DeliveryTracking;
use App\Services\ExpoPushService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('dev:simulate-delivery {order : Paid order id} {--interval=3 : Seconds between GPS points} {--steps=40 : Number of GPS points} {--deliver : Mark the order delivered at the end} {--force : Allow outside local env or a non-ready order}')]
#[Description('Simulate a rider moving from the shop to the customer for an assigned order.')]
class DevSimulateDelivery extends Command
{
    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to simulate deliveries in production. Pass --force if you really need to.');

            return self::FAILURE;
        }

        $orderId = (int) $this->argument('order');
        $steps = max(1, min(500, (int) $this->option('steps')));
        $interval = max(0, min(60, (int) $this->option('interval')));

        try {
            $order = Order::query()
                ->with(['deliveryPartner.user', 'user'])
                ->find($orderId);
        } catch (Throwable $e) {
            report($e);

            return self::FAILURE;
        }

        if ($order === null) {
            $this->error("Order #{$orderId} not found.");

            return self::FAILURE;
        }

        if ($order->status !== 'paid') {
            $this->error("Order #{$orderId} is not paid (status: {$order->status}).");

            return self::FAILURE;
        }

        if ($order->deliveryPartner === null) {
            $this->error("Order #{$orderId} has no rider. Assign one from the admin panel or run DeliveryDemoSeeder.");

            return self::FAILURE;
        }

        if (($order->fulfillment_status ?? 'new') !== 'ready' && ! $this->option('force')) {
            $this->error("Order #{$orderId} is not ready (status: {$order->fulfillment_status}). Use --force to simulate anyway.");

            return self::FAILURE;
        }

        if ($order->customer_lat === null || $order->customer_lng === null) {
            $this->error("Order #{$orderId} has no customer GPS pin.");

            return self::FAILURE;
        }

        $rider = $order->deliveryPartner;
        $shopLat = (float) config('services.shop.lat');
        $shopLng = (float) config('services.shop.lng');
        $endLat = (float) $order->customer_lat;
        $endLng = (float) $order->customer_lng;

        $route = $this->routeGeometry($shopLat, $shopLng, $endLat, $endLng);
        $routeDistance = $this->routeLength($route);

        $order->update([
            'rider_accepted_at' => $order->rider_accepted_at ?? now(),
            'picked_up_at' => $order->picked_up_at ?? now(),
            'fulfillment_status' => $order->fulfillment_status === 'delivered' ? 'delivered' : 'out_for_delivery',
        ]);

        if ($order->fulfillment_status !== 'delivered') {
            $push = ExpoPushService::forFulfillment($order->id, (int) $order->total, 'out_for_delivery');
            if ($push !== null && $order->user !== null) {
                app(ExpoPushService::class)->notifyUser($order->user, $push['title'], $push['body'], [
                    'type' => 'order_status',
                    'order_id' => $order->id,
                    'fulfillment_status' => 'out_for_delivery',
                ]);
            }
        }

        $this->info("Order #{$order->id}: rider {$rider->user?->name} starting from shop -> customer.");
        $this->line(sprintf('  Following road route (%.0f m).', $routeDistance));

        for ($i = 1; $i <= $steps; $i++) {
            $ratio = $i / $steps;
            [$lat, $lng] = $this->pointAlong($route, $routeDistance * $ratio);
            $now = now();

            DB::transaction(function () use ($order, $rider, $lat, $lng, $now): void {
                OrderLocation::create([
                    'order_id' => $order->id,
                    'delivery_partner_id' => $rider->id,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'accuracy_m' => 8,
                    'recorded_at' => $now,
                ]);

                $rider->update([
                    'last_lat' => $lat,
                    'last_lng' => $lng,
                    'last_location_at' => $now,
                    'is_online' => true,
                ]);
            });

            $this->line(sprintf('  %02d/%02d  %.7f, %.7f', $i, $steps, $lat, $lng));

            if ($interval > 0 && $i < $steps) {
                sleep($interval);
            }
        }

        if (DeliveryTracking::markArrivedIfNear($order, $lat, $lng)) {
            $this->line('  Rider marked arrived at the customer location.');

            if ($order->user !== null) {
                $push = ExpoPushService::forFulfillment($order->id, (int) $order->total, 'arrived');
                if ($push !== null) {
                    app(ExpoPushService::class)->notifyUser($order->user, $push['title'], $push['body'], [
                        'type' => 'order_status',
                        'order_id' => $order->id,
                        'fulfillment_status' => 'arrived',
                    ]);
                }
            }
        }

        if ($this->option('deliver')) {
            $order->update([
                'fulfillment_status' => 'delivered',
                'delivered_at' => now(),
            ]);

            if ($order->user !== null) {
                $push = ExpoPushService::forFulfillment($order->id, (int) $order->total, 'delivered');
                if ($push !== null) {
                    app(ExpoPushService::class)->notifyUser($order->user, $push['title'], $push['body'], [
                        'type' => 'order_status',
                        'order_id' => $order->id,
                        'fulfillment_status' => 'delivered',
                    ]);
                }
            }

            $this->info("Order #{$order->id} marked delivered.");
        }

        $this->info('Simulation complete.');

        return self::SUCCESS;
    }

    /**
     * Shop->customer shortest scooter route from the free Valhalla demo server.
     * Network fail ho to straight line fallback.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function routeGeometry(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->post('https://valhalla1.openstreetmap.de/route', [
                    'locations' => [
                        ['lat' => $fromLat, 'lon' => $fromLng],
                        ['lat' => $toLat, 'lon' => $toLng],
                    ],
                    'costing' => 'motor_scooter',
                ]);

            $shape = $response->json('trip.legs.0.shape');

            if (is_string($shape) && $shape !== '') {
                $points = $this->decodePolyline6($shape);

                if (count($points) >= 2) {
                    $first = $points[0];
                    if (DeliveryTracking::distanceMetres($first[0], $first[1], $fromLat, $fromLng) > 1) {
                        array_unshift($points, [$fromLat, $fromLng]);
                    }

                    $last = $points[count($points) - 1];
                    if (DeliveryTracking::distanceMetres($last[0], $last[1], $toLat, $toLng) > 1) {
                        $points[] = [$toLat, $toLng];
                    }

                    return $points;
                }
            }
        } catch (Throwable) {
            // Valhalla unavailable - fallback chalega.
        }

        return [[$fromLat, $fromLng], [$toLat, $toLng]];
    }

    /**
     * Valhalla polyline6 shape ko lat/lng points me decode karta hai.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function decodePolyline6(string $encoded): array
    {
        $points = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $length = strlen($encoded);

        while ($index < $length) {
            $shift = 0;
            $result = 0;

            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);

            $lat += ($result & 1) !== 0 ? ~($result >> 1) : ($result >> 1);

            $shift = 0;
            $result = 0;

            do {
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1F) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);

            $lng += ($result & 1) !== 0 ? ~($result >> 1) : ($result >> 1);

            $points[] = [$lat / 1_000_000, $lng / 1_000_000];
        }

        return $points;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $route
     */
    private function routeLength(array $route): float
    {
        $total = 0.0;

        for ($i = 1; $i < count($route); $i++) {
            $total += DeliveryTracking::distanceMetres(
                $route[$i - 1][0],
                $route[$i - 1][1],
                $route[$i][0],
                $route[$i][1],
            );
        }

        return $total;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $route
     * @return array{0: float, 1: float}
     */
    private function pointAlong(array $route, float $targetMetres): array
    {
        $last = $route[count($route) - 1];
        $travelled = 0.0;

        for ($i = 1; $i < count($route); $i++) {
            $segment = DeliveryTracking::distanceMetres(
                $route[$i - 1][0],
                $route[$i - 1][1],
                $route[$i][0],
                $route[$i][1],
            );

            if ($segment <= 0) {
                continue;
            }

            if ($travelled + $segment >= $targetMetres) {
                $ratio = ($targetMetres - $travelled) / $segment;

                return [
                    $route[$i - 1][0] + (($route[$i][0] - $route[$i - 1][0]) * $ratio),
                    $route[$i - 1][1] + (($route[$i][1] - $route[$i - 1][1]) * $ratio),
                ];
            }

            $travelled += $segment;
        }

        return [$last[0], $last[1]];
    }
}
