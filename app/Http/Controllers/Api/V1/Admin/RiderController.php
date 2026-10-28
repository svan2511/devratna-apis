<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Admin rider directory. A rider is a normal OTP user promoted with
 * is_rider=true and given a delivery_partners profile.
 */
class RiderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ]);

        try {
            $query = DeliveryPartner::query()
                ->with('user:id,name,phone,is_rider,is_admin')
                ->withCount([
                    'orders as active_orders_count' => fn ($q) => $q
                        ->where('status', 'paid')
                        ->whereIn('fulfillment_status', ['ready', 'out_for_delivery']),
                ])
                ->orderByDesc('is_active')
                ->orderBy('id');

            if (array_key_exists('active', $data)) {
                $query->where('is_active', (bool) $data['active']);
            }

            if (! empty($data['q'])) {
                $q = trim($data['q']);
                $query->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%"));
            }

            $riders = $query->get()->map(fn (DeliveryPartner $rider) => $this->riderPayload($rider));

            return $this->success(['riders' => $riders], 'Riders loaded.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not load riders.', 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'phone' => 'required|string|max:20',
            'vehicle_type' => 'nullable|in:bike,scooter,cycle,car',
            'vehicle_number' => 'nullable|string|max:30',
        ]);

        $phone = $this->normalizePhone((string) $data['phone']);
        if (! preg_match('/^[6-9]\d{9}$/', $phone)) {
            return $this->failure('Please enter a valid 10-digit Indian mobile number.', 422);
        }

        try {
            $rider = DB::transaction(function () use ($data, $phone): DeliveryPartner {
                $user = User::query()->where('phone', $phone)->first();

                if ($user === null) {
                    $user = User::create([
                        'name' => trim((string) $data['name']),
                        'phone' => $phone,
                        'phone_verified_at' => now(),
                        'is_rider' => true,
                    ]);
                } else {
                    $updates = ['is_rider' => true];
                    if (($user->name === null || trim((string) $user->name) === '') && trim((string) $data['name']) !== '') {
                        $updates['name'] = trim((string) $data['name']);
                    }
                    $user->update($updates);
                }

                $partner = DeliveryPartner::query()->firstOrNew(['user_id' => $user->id]);
                $partner->fill([
                    'vehicle_type' => $data['vehicle_type'] ?? $partner->vehicle_type ?? 'bike',
                    'vehicle_number' => $data['vehicle_number'] ?? $partner->vehicle_number,
                    'is_active' => true,
                    'is_online' => (bool) ($partner->is_online ?? false),
                ]);
                $partner->save();

                return $partner->fresh(['user']);
            });

            return $this->success(['rider' => $this->riderPayload($rider)], 'Rider saved.', 201);
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not save rider.', 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'vehicle_type' => 'sometimes|in:bike,scooter,cycle,car',
            'vehicle_number' => 'nullable|string|max:30',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $rider = DeliveryPartner::query()->with('user')->findOrFail($id);

            if (array_key_exists('is_active', $data) && $data['is_active'] === false && $rider->hasActiveOrder()) {
                return $this->failure('This rider has an active delivery. Complete or unassign it first.', 422);
            }

            if (array_key_exists('name', $data) && $rider->user !== null) {
                $rider->user->update(['name' => trim((string) $data['name'])]);
            }

            $rider->fill([
                'vehicle_type' => $data['vehicle_type'] ?? $rider->vehicle_type,
                'vehicle_number' => array_key_exists('vehicle_number', $data) ? $data['vehicle_number'] : $rider->vehicle_number,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $rider->is_active,
            ]);
            $rider->save();

            return $this->success(['rider' => $this->riderPayload($rider->fresh(['user']))], 'Rider updated.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not update rider.', 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function riderPayload(DeliveryPartner $rider): array
    {
        return [
            'id' => $rider->id,
            'user_id' => $rider->user_id,
            'name' => $rider->user?->name,
            'phone' => $rider->user?->phone,
            'vehicle_type' => $rider->vehicle_type,
            'vehicle_number' => $rider->vehicle_number,
            'is_active' => $rider->is_active,
            'is_online' => $rider->is_online,
            'last_lat' => $rider->last_lat !== null ? (float) $rider->last_lat : null,
            'last_lng' => $rider->last_lng !== null ? (float) $rider->last_lng : null,
            'last_location_at' => $rider->last_location_at?->toIso8601String(),
            'active_orders_count' => (int) ($rider->active_orders_count ?? 0),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return substr($digits, -10);
    }
}
