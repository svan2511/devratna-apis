<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\ExpoPushService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prepaid ordering — 1 km geofence + ₹500 minimum food bill + ₹40 delivery, Razorpay checkout.
 * Totals are ALWAYS recomputed from DB prices; client numbers are ignored.
 */
class OrderController extends Controller
{
    use ApiResponse;

    /**
     * Logged-in user's order history, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Order $order) => $this->publicOrder($order));

        return $this->success($orders, 'Order history.');
    }

    /**
     * Validate cart + location, create a local pending order and a
     * Razorpay order (REST, no SDK needed).
     */
    public function store(Request $request): JsonResponse
    {
        // --- Condition 0: shop open hai ya nahi (admin dashboard switch) ---
        if (! (bool) cache('shop_open', true)) {
            return $this->failure('Shop is closed right now. Please try again when we are open (7:30 AM – 11:00 PM).', 422);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1|max:50',
            'items.*.id' => 'required|integer|exists:menu_items,id',
            'items.*.portion' => 'required|in:quarter,half,medium,full,single',
            'items.*.qty' => 'required|integer|min:1|max:20',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|min:10|max:500',
        ]);

        // --- Server-side pricing from DB (never trust the client) ---
        /** @var array<int, MenuItem> $menu */
        $menu = MenuItem::query()
            ->whereIn('id', collect($data['items'])->pluck('id'))
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        $lines = [];
        $total = 0;
        foreach ($data['items'] as $line) {
            $dish = $menu[$line['id']] ?? null;
            if (! $dish) {
                return $this->failure('An item in your cart is no longer available.', 422);
            }
            // Portion → price column. 3-tier dishes (mid_price set):
            // quarter = smallest, half = middle, full = largest.
            // 2-tier/single: half/quarter/single = half_price.
            // 'medium' purane app versions ke liye rakha hai (= middle tier).
            $unit = match (true) {
                $line['portion'] === 'full' => $dish->full_price,
                $line['portion'] === 'half' && $dish->mid_price !== null => $dish->mid_price,
                $line['portion'] === 'medium' => $dish->mid_price,
                default => $dish->half_price,
            };
            if ($unit === null || $unit <= 0) {
                return $this->failure("{$dish->name}: this portion is not available.", 422);
            }
            $lines[] = [
                'id' => $dish->id,
                'name' => $dish->name,
                'portion' => $line['portion'],
                'qty' => $line['qty'],
                'unit' => $unit,
            ];
            $total += $unit * $line['qty'];
        }

        // --- Condition 2: minimum food bill (delivery is extra) ---
        $minOrder = (int) config('services.shop.min_order', 500);
        $delivery = (int) config('services.shop.delivery_charge', 40);
        if ($total < $minOrder) {
            return $this->failure("Minimum food order is ₹{$minOrder} (+ ₹{$delivery} delivery). Add food worth ₹".($minOrder - $total).' more.', 422);
        }

        // --- Condition 1: geofence around the shop ---
        $distance = self::distanceMetres(
            (float) $data['lat'],
            (float) $data['lng'],
            (float) config('services.shop.lat'),
            (float) config('services.shop.lng'),
        );
        $radius = (int) config('services.shop.radius_m', 1000);
        if ($distance > $radius) {
            $radiusLabel = $radius >= 1000 && $radius % 1000 === 0
                ? ($radius / 1000).' km'
                : ($radius >= 1000 ? round($radius / 1000, 1).' km' : $radius.'m');

            return $this->failure("We deliver within {$radiusLabel} of Dev Ratna Diner only. You seem to be outside the delivery area.", 422);
        }

        // --- Flat delivery on every order ---
        $payable = $total + $delivery;

        $order = Order::create([
            'user_id' => $request->user()->id,
            'items' => $lines,
            'delivery_address' => $data['address'] ?? null,
            'subtotal' => $total,
            'total' => $payable,
            'status' => 'pending',
            'customer_lat' => $data['lat'],
            'customer_lng' => $data['lng'],
            'distance_m' => (int) round($distance),
        ]);

        // --- Razorpay order (amount in paise) ---
        $keyId = (string) config('services.razorpay.key_id');
        $keySecret = (string) config('services.razorpay.key_secret');
        if ($keyId === '' || $keySecret === '') {
            $order->update(['status' => 'failed']);

            return $this->failure('Online payments are not configured yet. Please try again later.', 503);
        }

        try {
            $resp = Http::withBasicAuth($keyId, $keySecret)
                ->timeout(15)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $payable * 100,
                    'currency' => 'INR',
                    'receipt' => 'devratna_'.$order->id,
                    'notes' => ['order_id' => (string) $order->id],
                ]);
        } catch (Throwable $e) {
            report($e);
            $order->update(['status' => 'failed']);

            return $this->failure('Could not reach the payment gateway. Please try again.', 502);
        }

        if (! $resp->successful()) {
            $order->update(['status' => 'failed']);

            return $this->failure('Could not start the payment. Please try again.', 502);
        }

        $order->update(['razorpay_order_id' => $resp->json('id')]);

        return $this->success([
            'order' => [
                'id' => $order->id,
                'total' => $order->total,
                'status' => $order->status,
                'items' => $lines,
            ],
            'razorpay' => [
                'key_id' => $keyId,
                'order_id' => $resp->json('id'),
                'amount' => $total * 100,
                'currency' => 'INR',
            ],
        ], 'Order created. Complete the payment.');
    }

    /**
     * Verify Razorpay payment signature (HMAC-SHA256) and mark paid.
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'razorpay_order_id' => 'required|string|max:100',
            'razorpay_payment_id' => 'required|string|max:100',
            'razorpay_signature' => 'required|string|max:255',
        ]);

        $order = Order::query()
            ->where('id', $data['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->isPaid()) {
            return $this->success(['order' => $this->publicOrder($order)], 'Payment already confirmed.');
        }

        if ($order->razorpay_order_id !== $data['razorpay_order_id']) {
            return $this->failure('Payment details do not match this order.', 422);
        }

        $expected = hash_hmac(
            'sha256',
            $data['razorpay_order_id'].'|'.$data['razorpay_payment_id'],
            (string) config('services.razorpay.key_secret'),
        );

        if (! hash_equals($expected, $data['razorpay_signature'])) {
            $order->update([
                'status' => 'failed',
                'failure_reason' => 'Payment signature mismatch at verification.',
            ]);
            Log::warning('DevRatna order failed signature verification.', ['order_id' => $order->id]);

            return $this->failure('Payment verification failed. If money was deducted it will be refunded.', 422);
        }

        $order->update([
            'status' => 'paid',
            'failure_reason' => null,
            'paid_at' => now(),
            'razorpay_payment_id' => $data['razorpay_payment_id'],
        ]);
        Log::info('DevRatna order marked PAID.', [
            'order_id' => $order->id,
            'total' => $order->total,
            'source' => 'verify',
        ]);
        app(ExpoPushService::class)->notifyUser(
            $order->user,
            'Payment ho gaya! 🎉',
            "Aapka payment safal raha (₹{$order->total}) — khana abhi ban raha hai!",
            ['type' => 'order_confirmed', 'order_id' => $order->id],
        );

        return $this->success(['order' => $this->publicOrder($order)], 'Payment successful! Your order is confirmed.');
    }

    /**
     * Record a client-side payment failure (declined card, cancelled,
     * dismissed sheet). Never overwrites an already-paid order.
     */
    public function fail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'reason' => 'required|string|min:3|max:500',
            'code' => 'nullable|string|max:100',
        ]);

        $order = Order::query()
            ->where('id', $data['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->isPaid()) {
            return $this->success(['order' => $this->publicOrder($order)], 'Order is already paid.');
        }

        $reason = $data['code'] ? "[{$data['code']}] {$data['reason']}" : $data['reason'];
        $order->update(['status' => 'failed', 'failure_reason' => $reason]);
        Log::info('DevRatna order marked FAILED.', [
            'order_id' => $order->id,
            'reason' => $reason,
            'source' => 'app',
        ]);

        return $this->success(['order' => $this->publicOrder($order)], 'Failure recorded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function publicOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'subtotal' => $order->subtotal,
            'total' => $order->total,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status ?? 'new',
            'kitchen_note' => $order->kitchen_note,
            'failure_reason' => $order->failure_reason,
            'items' => $order->items,
            'delivery_address' => $order->delivery_address,
            'created_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'ready_at' => $order->ready_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
        ];
    }

    private static function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }
}
