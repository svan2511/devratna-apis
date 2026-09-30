<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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

            $query = Order::query()->with('user:id,name,phone')->latest();

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
        $order = Order::query()->with('user:id,name,phone')->findOrFail($id);

        return $this->success(['order' => $this->adminOrder($order)], 'Order detail.');
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
        if ($data['fulfillment_status'] === 'delivered') {
            $updates['delivered_at'] = now();
        }

        $order->update($updates);
        Log::info('DevRatna kitchen status updated.', ['order_id' => $order->id, 'to' => $data['fulfillment_status'], 'by' => $request->user()->id]);

        // Har kitchen status change pe user ko push — app khuli ho ya band.
        $push = \App\Services\ExpoPushService::forFulfillment($order->id, (int) $order->total, $data['fulfillment_status']);
        if ($push !== null) {
            app(\App\Services\ExpoPushService::class)->notifyUser(
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
        return [
            'id' => $order->id,
            'customer' => $order->user ? ['name' => $order->user->name, 'phone' => $order->user->phone] : null,
            'items' => $order->items,
            'subtotal' => $order->subtotal,
            'total' => $order->total,
            'payment_status' => $order->status,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status ?? 'new',
            'kitchen_note' => $order->kitchen_note,
            'delivery_address' => $order->delivery_address,
            // Exact GPS pin + shop se doori — delivery ke liye admin ko chahiye.
            'customer_lat' => $order->customer_lat !== null ? (float) $order->customer_lat : null,
            'customer_lng' => $order->customer_lng !== null ? (float) $order->customer_lng : null,
            'distance_m' => $order->distance_m !== null ? (int) $order->distance_m : null,
            'failure_reason' => $order->failure_reason,
            'created_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
        ];
    }
}
