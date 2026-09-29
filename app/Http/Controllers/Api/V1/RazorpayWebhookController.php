<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay webhook — server-side source of truth for payment status.
 *
 * The mobile app confirms instantly via POST /orders/verify, but this
 * webhook is what guarantees correctness: if the user pays and the app
 * closes (or network drops) before verify fires, Razorpay retries this
 * endpoint until the order is marked paid. No stuck money, no silent loss.
 *
 * Setup (Razorpay Dashboard → Settings → Webhooks):
 *   URL: https://<your-domain>/api/v1/webhooks/razorpay
 *   Events: payment.captured, payment.failed
 *   Secret → RAZORPAY_WEBHOOK_SECRET in .env
 */
class RazorpayWebhookController extends Controller
{
    use ApiResponse;

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        if ($secret === '') {
            Log::critical('DevRatna webhook rejected: RAZORPAY_WEBHOOK_SECRET is not configured.');

            return $this->failure('Webhook secret is not configured.', 503);
        }

        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            Log::warning('DevRatna webhook rejected: invalid signature.');

            return $this->failure('Invalid webhook signature.', 400);
        }

        $event = (string) $request->input('event', '');
        /** @var array<string, mixed> $payment */
        $payment = $request->input('payload.payment.entity', []) ?? [];
        $razorpayOrderId = (string) ($payment['order_id'] ?? '');
        $razorpayPaymentId = (string) ($payment['id'] ?? '');

        Log::info('DevRatna webhook received', [
            'event' => $event,
            'razorpay_order_id' => $razorpayOrderId,
            'razorpay_payment_id' => $razorpayPaymentId,
        ]);

        if ($razorpayOrderId === '') {
            return $this->success(null, 'Acknowledged (no order reference).');
        }

        $order = Order::query()->where('razorpay_order_id', $razorpayOrderId)->first();
        if (! $order) {
            Log::warning('DevRatna webhook for unknown order.', ['razorpay_order_id' => $razorpayOrderId]);

            return $this->success(null, 'Acknowledged (unknown order).');
        }

        match ($event) {
            'payment.captured' => $this->markPaid($order, $razorpayPaymentId, 'webhook'),
            'payment.failed' => $this->markFailed($order, $this->failureReason($payment), 'webhook'),
            default => Log::info('DevRatna webhook ignored (unhandled event).', ['event' => $event]),
        };

        return $this->success(null, 'Acknowledged.');
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function failureReason(array $payment): string
    {
        $code = (string) ($payment['error_code'] ?? '');
        $description = (string) ($payment['error_description'] ?? 'Payment failed at gateway.');
        $reason = $code !== '' ? "[{$code}] {$description}" : $description;

        return mb_substr($reason, 0, 500);
    }

    private function markPaid(Order $order, string $razorpayPaymentId, string $source): void
    {
        if ($order->isPaid()) {
            Log::info('DevRatna order already paid — duplicate confirmation ignored.', [
                'order_id' => $order->id,
                'source' => $source,
            ]);

            return;
        }

        $order->update([
            'status' => 'paid',
            'failure_reason' => null,
            'paid_at' => now(),
            'razorpay_payment_id' => $razorpayPaymentId !== '' ? $razorpayPaymentId : $order->razorpay_payment_id,
        ]);

        Log::info('DevRatna order marked PAID.', [
            'order_id' => $order->id,
            'total' => $order->total,
            'source' => $source,
        ]);

        // Sirf transition pe — verify() pehle kar chuka ho to dobara nahi.
        app(\App\Services\ExpoPushService::class)->notifyUser(
            $order->user,
            'Order confirmed! 🎉',
            "Payment successful. Order #{$order->id} • ₹{$order->total} — khana ban raha hai!",
            ['type' => 'order_confirmed', 'order_id' => $order->id],
        );
    }

    private function markFailed(Order $order, string $reason, string $source): void
    {
        if ($order->isPaid()) {
            Log::warning('DevRatna failed-event for an already-paid order — paid status kept.', [
                'order_id' => $order->id,
                'source' => $source,
            ]);

            return;
        }

        $order->update(['status' => 'failed', 'failure_reason' => $reason]);

        Log::info('DevRatna order marked FAILED.', [
            'order_id' => $order->id,
            'reason' => $reason,
            'source' => $source,
        ]);
    }
}
