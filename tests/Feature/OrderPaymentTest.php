<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(User $user, string $rzpOrderId = 'order_Test123'): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'items' => [['id' => 1, 'name' => 'Dal Makhani', 'portion' => 'full', 'qty' => 3, 'unit' => 180]],
            'subtotal' => 540,
            'total' => 580,
            'status' => 'pending',
            'razorpay_order_id' => $rzpOrderId,
        ]);
    }

    private function signedWebhookCall(array $payload, string $secret = 'test-secret')
    {
        config()->set('services.razorpay.webhook_secret', 'test-secret');
        $raw = json_encode($payload);
        $sig = $secret === 'wrong' ? 'invalid' : hash_hmac('sha256', $raw, 'test-secret');

        // Note: call() bypasses withHeaders(), so the signature goes via $server vars.
        return $this->call(
            'POST', '/api/v1/webhooks/razorpay', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig], $raw
        );
    }

    public function test_webhook_payment_captured_marks_order_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);

        $response = $this->signedWebhookCall([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_Test123',
                'order_id' => 'order_Test123',
                'status' => 'captured',
            ]]],
        ]);

        $response->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('pay_Test123', $order->fresh()->razorpay_payment_id);
        $this->assertNotNull($order->fresh()->paid_at);

        // Duplicate webhook (Razorpay retries) must be harmless.
        $this->signedWebhookCall([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_Test123',
                'order_id' => 'order_Test123',
                'status' => 'captured',
            ]]],
        ])->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_webhook_payment_failed_records_reason(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);

        $response = $this->signedWebhookCall([
            'event' => 'payment.failed',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_Test456',
                'order_id' => 'order_Test123',
                'status' => 'failed',
                'error_code' => 'BAD_REQUEST_ERROR',
                'error_description' => 'Card declined by bank.',
            ]]],
        ]);

        $response->assertOk();
        $fresh = $order->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertStringContainsString('Card declined by bank.', $fresh->failure_reason);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);

        $this->signedWebhookCall(['event' => 'payment.captured'], 'wrong')->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_fail_endpoint_records_client_failure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->pendingOrder($user);

        $response = $this->postJson('/api/v1/orders/fail', [
            'order_id' => $order->id,
            'reason' => 'Card declined.',
            'code' => 'card_declined',
        ]);

        $response->assertOk();
        $fresh = $order->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertStringContainsString('card_declined', $fresh->failure_reason);
    }

    public function test_fail_endpoint_never_overwrites_paid_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $order = $this->pendingOrder($user);
        $order->update(['status' => 'paid', 'paid_at' => now()]);

        $this->postJson('/api/v1/orders/fail', [
            'order_id' => $order->id,
            'reason' => 'Late duplicate failure.',
        ])->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
    }
}
