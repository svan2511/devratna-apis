<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dashboard stats — aaj ki sale, kitchen load, top dishes.
 */
class DashboardController extends Controller
{
    use ApiResponse;

    public function stats(): JsonResponse
    {
        try {
            $todayRevenue = (int) Order::query()->where('status', 'paid')->whereDate('paid_at', today())->sum('total');
            $todayOrders = (int) Order::query()->where('status', 'paid')->whereDate('created_at', today())->count();
            $pendingKitchen = (int) Order::query()
                ->where('status', 'paid')
                ->whereIn('fulfillment_status', ['new', 'preparing'])
                ->count();
            $avg = $todayOrders > 0 ? (int) round($todayRevenue / $todayOrders) : 0;

            // Top dishes (last 7 days, paid orders) — items JSON se aggregate.
            $recent = Order::query()
                ->where('status', 'paid')
                ->where('paid_at', '>=', now()->subDays(7))
                ->pluck('items');
            $agg = [];
            foreach ($recent as $lines) {
                foreach ((array) $lines as $l) {
                    $name = (string) ($l['name'] ?? 'Unknown');
                    $qty = (int) ($l['qty'] ?? 0);
                    $rev = (int) (($l['unit'] ?? 0) * $qty);
                    if (! isset($agg[$name])) {
                        $agg[$name] = ['name' => $name, 'qty' => 0, 'revenue' => 0];
                    }
                    $agg[$name]['qty'] += $qty;
                    $agg[$name]['revenue'] += $rev;
                }
            }
            usort($agg, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
            $top = array_slice(array_values($agg), 0, 5);

            $recentOrders = Order::query()
                ->with('user:id,name,phone')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'customer' => trim(($o->user?->name ?? 'Guest').' • +91 '.($o->user?->phone ?? '')),
                    'total' => $o->total,
                    'payment_status' => $o->status,
                    'fulfillment_status' => $o->fulfillment_status ?? 'new',
                    'created_at' => $o->created_at?->diffForHumans(),
                ]);

            // Last 7 days revenue for mini chart.
            $week = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = today()->subDays($i);
                $week[] = (int) Order::query()->where('status', 'paid')->whereDate('paid_at', $day)->sum('total');
            }

            return $this->success([
                'today' => ['revenue' => $todayRevenue, 'orders' => $todayOrders, 'pending_kitchen' => $pendingKitchen, 'avg_order' => $avg],
                'week_revenue' => $week,
                'top_items' => $top,
                'recent_orders' => $recentOrders,
            ], 'Dashboard stats.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not load dashboard stats.', 500);
        }
    }
}
