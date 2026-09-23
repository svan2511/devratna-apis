<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Last-mile money safety: re-check Razorpay for orders stuck in
 * "pending" (app closed before verify, webhook missed).
 *
 * Usage: php artisan orders:reconcile [--minutes=15]
 * Run on a schedule for full automation:
 *   $schedule->command('orders:reconcile')->everyFifteenMinutes();
 */
class ReconcileOrders extends Command
{
    protected $signature = 'orders:reconcile {--minutes=15 : only check orders pending longer than this}';

    protected $description = 'Sync stuck pending orders with Razorpay payment status.';

    public function handle(): int
    {
        $keyId = (string) config('services.razorpay.key_id');
        $keySecret = (string) config('services.razorpay.key_secret');
        if ($keyId === '' || $keySecret === '') {
            $this->error('Razorpay credentials are not configured.');

            return self::FAILURE;
        }

        $minutes = max(1, (int) $this->option('minutes'));
        $stuck = Order::query()
            ->where('status', 'pending')
            ->whereNotNull('razorpay_order_id')
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->limit(50)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck pending orders.');

            return self::SUCCESS;
        }

        foreach ($stuck as $order) {
            try {
                $resp = Http::withBasicAuth($keyId, $keySecret)
                    ->timeout(15)
                    ->get("https://api.razorpay.com/v1/orders/{$order->razorpay_order_id}/payments");

                if (! $resp->successful()) {
                    Log::warning('DevRatna reconcile: could not fetch payments.', ['order_id' => $order->id]);

                    continue;
                }

                $captured = collect($resp->json('items', []))->firstWhere('status', 'captured');
                if ($captured) {
                    $order->update([
                        'status' => 'paid',
                        'failure_reason' => null,
                        'paid_at' => now(),
                        'razorpay_payment_id' => $captured['id'] ?? $order->razorpay_payment_id,
                    ]);
                    Log::info('DevRatna reconcile marked PAID.', ['order_id' => $order->id, 'source' => 'reconcile']);
                    $this->info("Order #{$order->id}: PAID (recovered).");
                } else {
                    $this->line("Order #{$order->id}: still unpaid at gateway.");
                }
            } catch (Throwable $e) {
                report($e);
                $this->warn("Order #{$order->id}: check failed, will retry next run.");
            }
        }

        return self::SUCCESS;
    }
}
