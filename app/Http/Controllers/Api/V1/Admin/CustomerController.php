<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Repeat customers — order count + total spend ke sath.
 */
class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $limit = (int) ($data['limit'] ?? 50);

            $query = User::query()
                ->where('is_admin', false)
                ->withCount(['orders as orders_count' => fn ($q) => $q->where('status', 'paid')])
                ->withSum(['orders as total_spent' => fn ($q) => $q->where('status', 'paid')], 'total')
                ->withMax('orders as last_order_at', 'created_at')
                ->orderByDesc('total_spent');

            if (! empty($data['q'])) {
                $q = trim($data['q']);
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
            }

            $rows = $query->paginate($limit);

            $customers = collect($rows->items())->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'orders_count' => (int) ($u->orders_count ?? 0),
                'total_spent' => (int) ($u->total_spent ?? 0),
                // withMax returns a raw string, not Carbon — parse first.
                'last_order_at' => $u->last_order_at ? \Carbon\Carbon::parse($u->last_order_at)->diffForHumans() : null,
            ]);

            return $this->success(['customers' => $customers], 'Customers loaded.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not load customers.', 500);
        }
    }
}
