<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Services\DeliveryTracking;
use App\Services\ExpoPushService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kitchen + order history — payment alag, fulfillment alag.
 */
class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'payment_status' => 'nullable|in:paid,pending,failed',
            'fulfillment_status' => 'nullable|in:new,preparing,ready,out_for_delivery,delivered,cancelled',
            'scope' => 'nullable|in:kitchen,all',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        try {
            $limit = (int) ($data['limit'] ?? 30);

            $query = Order::query()
                ->with(['user:id,name,phone', 'deliveryPartner.user:id,name,phone', 'latestLocation'])
                ->latest();

            if (($data['scope'] ?? '') === 'kitchen') {
                $query->where('status', 'paid')->whereIn('fulfillment_status', ['new', 'preparing', 'ready', 'out_for_delivery']);
            }
            if (! empty($data['payment_status'])) {
                $query->where('status', $data['payment_status']);
            }
            if (! empty($data['fulfillment_status'])) {
                $query->where('fulfillment_status', $data['fulfillment_status']);
            }
            if (! empty($data['q'])) {
                $q = trim($data['q']);
                if (ctype_digit($q)) {
                    $query->where('id', (int) $q);
                } else {
                    $query->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
                }
            }

            $paginator = $query->paginate($limit);

            $orders = collect($paginator->items())->map(fn (Order $o) => $this->adminOrder($o));

            return $this->success(
                ['orders' => $orders],
                'Orders loaded.',
                200,
                ['meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()]]
            );
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not load orders.', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::query()
            ->with(['user:id,name,phone', 'deliveryPartner.user:id,name,phone', 'latestLocation'])
            ->findOrFail($id);

        return $this->success(['order' => $this->adminOrder($order)], 'Order detail.');
    }

    /**
     * Assign a rider to a paid, kitchen-ready order. Reassignment is allowed
     * until the rider has picked the order up.
     */
    public function assignRider(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'delivery_partner_id' => 'required|integer|exists:delivery_partners,id',
        ]);

        $order = Order::query()->with('user')->findOrFail($id);
        $rider = DeliveryPartner::query()->with('user')->findOrFail((int) $data['delivery_partner_id']);

        if ($order->status !== 'paid') {
            return $this->failure('Only paid orders can be assigned to a rider.', 422);
        }

        if (($order->fulfillment_status ?? 'new') !== 'ready') {
            return $this->failure('Order must be marked ready before assigning a rider.', 422);
        }

        if ($order->picked_up_at !== null) {
            return $this->failure('This order is already picked up and cannot be reassigned.', 422);
        }

        if (! $rider->is_active) {
            return $this->failure('This rider is inactive.', 422);
        }

        // Sirf online rider ko assign karo — offline rider ko notification
        // tab tak nahi jayegi jab tak wo rider app me online na aaye.
        if (! $rider->is_online) {
            return $this->failure('This rider is offline. Only online riders can be assigned.', 422);
        }

        if ($order->delivery_partner_id !== null && $order->delivery_partner_id !== $rider->id && $order->rider_accepted_at !== null) {
            return $this->failure('Rider has already accepted this order. Unassign first to change.', 422);
        }

        $riderBusy = $rider->orders()
            ->whereKeyNot($order->id)
            ->where('status', 'paid')
            ->whereIn('fulfillment_status', ['ready', 'out_for_delivery'])
            ->exists();

        if ($riderBusy) {
            return $this->failure('This rider already has an active delivery.', 422);
        }

        $alreadyAssigned = $order->delivery_partner_id === $rider->id;

        $order->update([
            'delivery_partner_id' => $rider->id,
            'rider_assigned_at' => $order->rider_assigned_at ?? now(),
            'rider_accepted_at' => null,
            'arrived_at' => null,
        ]);

        if (! $alreadyAssigned) {
            // Rider ko turant notification — customer ko sirf tab jab rider
            // accept karega (RiderController::accept), taaki user ko pakka
            // rider ki details milein.
            app(ExpoPushService::class)->notifyUser(
                $rider->user,
                'New delivery assigned',
                ExpoPushService::forRiderAssignment($order->id, (string) ($order->user?->name ?? 'Customer'), $order->delivery_address)['body'],
                ['type' => 'rider_assignment', 'order_id' => $order->id, 'fulfillment_status' => 'ready'],
            );
        }

        $order = $order->fresh(['user:id,name,phone', 'deliveryPartner.user:id,name,phone', 'latestLocation']);

        return $this->success(['order' => $this->adminOrder($order)], $alreadyAssigned ? 'Rider already assigned.' : 'Rider assigned.');
    }

    public function unassignRider(int $id): JsonResponse
    {
        $order = Order::query()->with(['user', 'deliveryPartner.user'])->findOrFail($id);

        if ($order->picked_up_at !== null) {
            return $this->failure('Picked-up orders cannot be unassigned.', 422);
        }

        if ($order->delivery_partner_id === null) {
            return $this->success(['order' => $this->adminOrder($order)], 'No rider assigned.');
        }

        $order->update([
            'delivery_partner_id' => null,
            'rider_assigned_at' => null,
            'rider_accepted_at' => null,
            'arrived_at' => null,
        ]);

        if ($order->user !== null) {
            app(ExpoPushService::class)->notifyUser(
                $order->user,
                'Rider update',
                'Your rider is being changed. A new rider will be assigned shortly.',
                ['type' => 'order_status', 'order_id' => $order->id, 'fulfillment_status' => $order->fulfillment_status, 'rider_unassigned' => true],
            );
        }

        $order = $order->fresh(['user:id,name,phone', 'deliveryPartner.user:id,name,phone', 'latestLocation']);

        return $this->success(['order' => $this->adminOrder($order)], 'Rider unassigned.');
    }

    /**
     * Kitchen status update — paid order ka fulfillment aage badhao.
     * Failed/pending payment wale orders ka kitchen status nahi badal sakta.
     */
    public function updateFulfillment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'fulfillment_status' => 'required|in:new,preparing,ready,out_for_delivery,delivered,cancelled',
            'note' => 'nullable|string|max:500',
        ]);

        $order = Order::query()->findOrFail($id);

        if ($order->status !== 'paid' && $data['fulfillment_status'] !== 'cancelled') {
            return $this->failure('Only paid orders can enter the kitchen flow.', 422);
        }

        $updates = [
            'fulfillment_status' => $data['fulfillment_status'],
            'kitchen_note' => $data['note'] ?? $order->kitchen_note,
        ];
        if ($data['fulfillment_status'] === 'ready') {
            $updates['ready_at'] = now();
        }
        if ($data['fulfillment_status'] === 'out_for_delivery') {
            $updates['picked_up_at'] = $order->picked_up_at ?? now();
        }
        if ($data['fulfillment_status'] === 'delivered') {
            $updates['delivered_at'] = now();
        }

        $order->update($updates);
        Log::info('DevRatna kitchen status updated.', ['order_id' => $order->id, 'to' => $data['fulfillment_status'], 'by' => $request->user()->id]);

        // Har kitchen status change pe user ko push — app khuli ho ya band.
        $push = ExpoPushService::forFulfillment($order->id, (int) $order->total, $data['fulfillment_status']);
        if ($push !== null) {
            app(ExpoPushService::class)->notifyUser(
                $order->user,
                $push['title'],
                $push['body'],
                ['type' => 'order_status', 'order_id' => $order->id, 'fulfillment_status' => $data['fulfillment_status']],
            );
        }

        return $this->success(['order' => $this->adminOrder($order->fresh())], 'Kitchen status updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function adminOrder(Order $order): array
    {
        $location = $order->latestLocation;

        return [
            'id' => $order->id,
            'customer' => $order->user ? ['name' => $order->user->name, 'phone' => $order->user->phone] : null,
            'items' => $order->items,
            'subtotal' => $order->subtotal,
            'discount' => $order->discount ?? 0,
            'offer_name' => $order->offer_name,
            'total' => $order->total,
            'payment_status' => $order->status,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status ?? 'new',
            'kitchen_note' => $order->kitchen_note,
            'delivery_address' => $order->delivery_address,
            // Exact GPS pin + shop se doori - delivery ke liye admin ko chahiye.
            'customer_lat' => $order->customer_lat !== null ? (float) $order->customer_lat : null,
            'customer_lng' => $order->customer_lng !== null ? (float) $order->customer_lng : null,
            'distance_m' => $order->distance_m !== null ? (int) $order->distance_m : null,
            'rider' => $order->deliveryPartner?->user ? [
                'id' => $order->deliveryPartner->id,
                'user_id' => $order->deliveryPartner->user_id,
                'name' => $order->deliveryPartner->user->name,
                'phone' => $order->deliveryPartner->user->phone,
                'vehicle_type' => $order->deliveryPartner->vehicle_type,
                'vehicle_number' => $order->deliveryPartner->vehicle_number,
                'is_online' => $order->deliveryPartner->is_online,
                'is_active' => $order->deliveryPartner->is_active,
            ] : null,
            'rider_assigned_at' => $order->rider_assigned_at?->toIso8601String(),
            'rider_accepted_at' => $order->rider_accepted_at?->toIso8601String(),
            'picked_up_at' => $order->picked_up_at?->toIso8601String(),
            'rider_location' => DeliveryTracking::locationPayload($location),
            'failure_reason' => $order->failure_reason,
            'created_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
        ];
    }
}
