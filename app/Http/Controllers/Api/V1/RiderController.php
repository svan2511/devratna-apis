<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\OrderLocation;
use App\Services\DeliveryTracking;
use App\Services\ExpoPushService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rider app endpoints: dashboard, accept/pickup/deliver and GPS breadcrumbs.
 * One rider can carry one active order at a time.
 */
class RiderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        try {
            $partner = $this->partner($request);

            $active = Order::query()
                ->with(['user:id,name,phone', 'latestLocation'])
                ->where('delivery_partner_id', $partner->id)
                ->where('status', 'paid')
                ->whereIn('fulfillment_status', ['ready', 'out_for_delivery'])
                ->latest()
                ->first();

            $history = Order::query()
                ->with(['user:id,name,phone', 'latestLocation'])
                ->where('delivery_partner_id', $partner->id)
                ->where(function ($q) {
                    $q->whereIn('fulfillment_status', ['delivered', 'cancelled'])
                        ->orWhereNotNull('delivered_at');
                })
                ->latest()
                ->limit(20)
                ->get();

            return $this->success([
                'rider' => $this->riderPayload($partner),
                'shop' => [
                    'lat' => (float) config('services.shop.lat', 30.2710150),
                    'lng' => (float) config('services.shop.lng', 77.9926317),
                ],
                'active_order' => $active !== null ? $this->orderPayload($active) : null,
                'history' => $history->map(fn (Order $order) => $this->orderPayload($order, true))->all(),
            ], 'Rider assignments loaded.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not load rider assignments.', 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $partner = $this->partner($request);
        $order = $this->assignedOrder($partner, $id);

        return $this->success(['order' => $this->orderPayload($order)], 'Order loaded.');
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_online' => 'required|boolean',
        ]);

        $partner = $this->partner($request);
        $partner->update(['is_online' => (bool) $data['is_online']]);

        return $this->success(['rider' => $this->riderPayload($partner->fresh())], 'Availability updated.');
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $partner = $this->partner($request);
        $order = $this->assignedOrder($partner, $id);

        if ($order->picked_up_at !== null || $order->fulfillment_status === 'out_for_delivery') {
            return $this->failure('This order is already picked up.', 422);
        }

        if (! in_array($order->fulfillment_status, ['ready', 'new', 'preparing'], true)) {
            return $this->failure('This order cannot be accepted right now.', 422);
        }

        $isFirstAccept = $order->rider_accepted_at === null;

        if ($isFirstAccept) {
            $order->update(['rider_accepted_at' => now()]);
        }
        if (! $partner->is_online) {
            $partner->update(['is_online' => true]);
        }

        $order = $order->fresh(['user:id,name,phone', 'latestLocation']);

        // Customer ko rider ki details tabhi bhejo jab rider ne accept kiya ho —
        // assign hote hi nahi, taaki user ko sirf pakka rider ki info mile.
        if ($isFirstAccept) {
            $this->notifyCustomerRiderAccepted($order, $partner);
        }

        return $this->success(['order' => $this->orderPayload($order)], 'Order accepted.');
    }

    public function pickup(Request $request, int $id): JsonResponse
    {
        $partner = $this->partner($request);
        $order = $this->assignedOrder($partner, $id);

        if ($order->fulfillment_status === 'delivered') {
            return $this->failure('This order is already delivered.', 422);
        }

        if ($order->fulfillment_status !== 'out_for_delivery') {
            $order->update([
                'fulfillment_status' => 'out_for_delivery',
                'rider_accepted_at' => $order->rider_accepted_at ?? now(),
                'picked_up_at' => $order->picked_up_at ?? now(),
            ]);
            $this->notifyCustomer($order, 'out_for_delivery');
        }

        $order = $order->fresh(['user:id,name,phone', 'latestLocation']);

        return $this->success(['order' => $this->orderPayload($order)], 'Order picked up.');
    }

    public function deliver(Request $request, int $id): JsonResponse
    {
        $partner = $this->partner($request);
        $order = $this->assignedOrder($partner, $id);

        if ($order->fulfillment_status !== 'delivered') {
            $order->update([
                'fulfillment_status' => 'delivered',
                'rider_accepted_at' => $order->rider_accepted_at ?? now(),
                'picked_up_at' => $order->picked_up_at ?? now(),
                'delivered_at' => now(),
            ]);
            $this->notifyCustomer($order, 'delivered');
        }

        $order = $order->fresh(['user:id,name,phone', 'latestLocation']);

        return $this->success(['order' => $this->orderPayload($order, true)], 'Order delivered.');
    }

    public function location(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy_m' => 'nullable|integer|min:0|max:10000',
        ]);

        $partner = $this->partner($request);
        $order = $this->assignedOrder($partner, $id);

        if (in_array($order->fulfillment_status, ['delivered', 'cancelled'], true)) {
            return $this->failure('This order is no longer active.', 422);
        }

        $now = now();
        $location = DB::transaction(function () use ($data, $order, $partner, $now): OrderLocation {
            $location = OrderLocation::create([
                'order_id' => $order->id,
                'delivery_partner_id' => $partner->id,
                'latitude' => (float) $data['latitude'],
                'longitude' => (float) $data['longitude'],
                'accuracy_m' => $data['accuracy_m'] !== null ? (int) $data['accuracy_m'] : null,
                'recorded_at' => $now,
            ]);

            $partner->update([
                'last_lat' => (float) $data['latitude'],
                'last_lng' => (float) $data['longitude'],
                'last_location_at' => $now,
            ]);

            return $location;
        });

        if (DeliveryTracking::markArrivedIfNear($order, (float) $data['latitude'], (float) $data['longitude'])) {
            $this->notifyCustomer($order, 'arrived');
        }

        return $this->success(['location' => DeliveryTracking::locationPayload($location)], 'Location saved.', 201);
    }

    private function partner(Request $request): DeliveryPartner
    {
        $partner = $request->user()?->deliveryPartner;
        abort_if($partner === null, 403, 'Rider profile missing.');
        abort_if(! $partner->is_active, 403, 'Rider profile is inactive.');

        return $partner;
    }

    private function assignedOrder(DeliveryPartner $partner, int $id): Order
    {
        return Order::query()
            ->with(['user:id,name,phone', 'latestLocation'])
            ->where('delivery_partner_id', $partner->id)
            ->findOrFail($id);
    }

    private function notifyCustomer(Order $order, string $status): void
    {
        if ($order->user === null) {
            return;
        }

        $push = ExpoPushService::forFulfillment($order->id, (int) $order->total, $status);
        if ($push === null) {
            return;
        }

        app(ExpoPushService::class)->notifyUser(
            $order->user,
            $push['title'],
            $push['body'],
            ['type' => 'order_status', 'order_id' => $order->id, 'fulfillment_status' => $status],
        );
    }

    /**
     * Rider accept karte hi customer ko rider ki poori details push karo.
     */
    private function notifyCustomerRiderAccepted(Order $order, DeliveryPartner $partner): void
    {
        if ($order->user === null) {
            return;
        }

        $partner->loadMissing('user');
        $riderName = (string) ($partner->user?->name ?? 'Delivery partner');
        $push = ExpoPushService::forCustomerRiderAccepted(
            $riderName,
            (string) $partner->vehicle_type,
            $partner->vehicle_number,
            $partner->user?->phone,
        );

        app(ExpoPushService::class)->notifyUser(
            $order->user,
            $push['title'],
            $push['body'],
            [
                'type' => 'order_status',
                'order_id' => $order->id,
                'fulfillment_status' => $order->fulfillment_status ?? 'ready',
                'rider_accepted' => true,
                'rider' => [
                    'name' => $riderName,
                    'phone' => $partner->user?->phone,
                    'vehicle_type' => $partner->vehicle_type,
                    'vehicle_number' => $partner->vehicle_number,
                ],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function riderPayload(DeliveryPartner $partner): array
    {
        return [
            'id' => $partner->id,
            'user_id' => $partner->user_id,
            'name' => $partner->user?->name,
            'phone' => $partner->user?->phone,
            'vehicle_type' => $partner->vehicle_type,
            'vehicle_number' => $partner->vehicle_number,
            'is_active' => $partner->is_active,
            'is_online' => $partner->is_online,
            'last_location' => $partner->last_lat !== null && $partner->last_lng !== null
                ? DeliveryTracking::locationPayload(new OrderLocation([
                    'latitude' => $partner->last_lat,
                    'longitude' => $partner->last_lng,
                    'recorded_at' => $partner->last_location_at ?? now(),
                ]))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order, bool $history = false): array
    {
        $location = $order->latestLocation;
        $customer = $order->user;

        return [
            'id' => $order->id,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status ?? 'new',
            'items' => $order->items,
            'total' => $order->total,
            'delivery_address' => $order->delivery_address,
            'kitchen_note' => $order->kitchen_note,
            'customer' => $customer !== null ? [
                'name' => $customer->name,
                'phone' => $history ? null : $customer->phone,
            ] : null,
            'customer_lat' => $order->customer_lat !== null ? (float) $order->customer_lat : null,
            'customer_lng' => $order->customer_lng !== null ? (float) $order->customer_lng : null,
            'distance_m' => $order->distance_m !== null ? (int) $order->distance_m : null,
            'maps_url' => $order->customer_lat !== null && $order->customer_lng !== null
                ? 'https://www.google.com/maps/dir/?api=1&destination='.$order->customer_lat.','.$order->customer_lng
                : null,
            'rider_assigned_at' => $order->rider_assigned_at?->toIso8601String(),
            'rider_accepted_at' => $order->rider_accepted_at?->toIso8601String(),
            'picked_up_at' => $order->picked_up_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'rider_location' => DeliveryTracking::locationPayload($location),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
