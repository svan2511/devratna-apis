<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offer manager — discount/free-item deals + live window.
 * Billing server-side (OfferEngine) turant lagu hota hai.
 */
class OfferController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $offers = Offer::query()->ordered()->with('freeItem:id,name')->get();

        return $this->success(
            ['offers' => $offers, 'target_types' => Offer::targetTypes(), 'discount_types' => Offer::discountTypes()],
            'Offers loaded.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $this->validateTarget($data);

        $offer = Offer::create($this->payload($data));

        return $this->success(['offer' => $offer->fresh('freeItem')], 'Offer created.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $offer = Offer::query()->findOrFail($id);
        $data = $this->validatePayload($request);
        $this->validateTarget($data);

        $offer->update($this->payload($data, $offer));

        return $this->success(['offer' => $offer->fresh('freeItem')], 'Offer updated.');
    }

    public function setActive(Request $request, int $id): JsonResponse
    {
        $offer = Offer::query()->findOrFail($id);
        $data = $request->validate(['is_active' => 'required|boolean']);

        $offer->update(['is_active' => (bool) $data['is_active']]);

        return $this->success(
            ['offer' => $offer->fresh()],
            $offer->is_active ? 'Offer is now live.' : 'Offer paused.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $offer = Offer::query()->findOrFail($id);
        $offer->delete();

        return $this->success(null, 'Offer deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'description' => 'nullable|string|max:200',
            'target_type' => 'required|string|in:all,category,item',
            'target_value' => 'nullable|string|max:50',
            'discount_type' => 'required|string|in:none,percent,flat',
            'discount_value' => 'nullable|integer|min:0|max:10000',
            'discount_scope' => 'nullable|string|in:order,item',
            'free_item_id' => 'nullable|integer|exists:menu_items,id',
            'min_order' => 'nullable|integer|min:0|max:10000',
            'min_qty' => 'nullable|integer|min:1|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            // "120 minute me expire" — save pe ends_at khud ban jayega.
            'duration_minutes' => 'nullable|integer|min:1|max:43200',
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        // Category/item target ho to value lazmi — warna offer ya to sab pe lagegi ya kahin nahi.
        if (in_array($data['target_type'], ['category', 'item'], true) && trim((string) ($data['target_value'] ?? '')) === '') {
            abort(response()->json(['success' => false, 'message' => 'Target chuno — kaunsi category ya dish pe offer hai.'], 422));
        }
        // Discount type ho to value lazmi.
        if (in_array($data['discount_type'], ['percent', 'flat'], true) && (int) ($data['discount_value'] ?? 0) <= 0) {
            abort(response()->json(['success' => false, 'message' => 'Discount value 0 se zyada rakho.'], 422));
        }

        return $data;
    }

    /**
     * Target ka matlab banta ho — category slug / item id asli hone chahiye.
     *
     * @param  array<string, mixed>  $data
     */
    private function validateTarget(array $data): void
    {
        if (($data['target_type'] ?? 'all') === 'category' && ! empty($data['target_value'])) {
            abort_unless(
                \App\Models\Category::query()->where('slug', $data['target_value'])->exists(),
                422,
                'Ye category slug maujood nahi hai.'
            );
        }
        if (($data['target_type'] ?? '') === 'item' && ! empty($data['target_value'])) {
            abort_unless(
                MenuItem::query()->where('id', (int) $data['target_value'])->exists(),
                422,
                'Ye dish id maujood nahi hai.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, ?Offer $existing = null): array
    {
        // Minutes diye ho to window khud banao: start (warna ab/purana) + minutes = end.
        // Edit pe same minutes dobara save ho to same end banta hai (drift nahi).
        // Khali string = null (form ab dates bhejta hi nahi).
        $minutes = (int) ($data['duration_minutes'] ?? 0);
        $rawStart = trim((string) ($data['starts_at'] ?? ''));
        $rawEnds = trim((string) ($data['ends_at'] ?? ''));
        if ($minutes > 0) {
            $start = $rawStart !== '' ? $rawStart : ($existing?->starts_at?->toDateTimeString() ?? now()->toDateTimeString());
            $ends = \Carbon\Carbon::parse($start)->addMinutes($minutes)->toDateTimeString();
        } else {
            $start = $rawStart !== '' ? $rawStart : null;
            $ends = $rawEnds !== '' ? $rawEnds : null;
        }

        return [
            'name' => trim($data['name']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'target_type' => $data['target_type'],
            'target_value' => isset($data['target_value']) && trim((string) $data['target_value']) !== '' ? trim((string) $data['target_value']) : null,
            'discount_type' => $data['discount_type'],
            'discount_value' => (int) ($data['discount_value'] ?? 0),
            'discount_scope' => $data['discount_scope'] ?? 'order',
            'free_item_id' => isset($data['free_item_id']) && trim((string) $data['free_item_id']) !== '' ? (int) $data['free_item_id'] : null,
            'min_order' => (int) ($data['min_order'] ?? 0),
            'min_qty' => max(1, (int) ($data['min_qty'] ?? 1)),
            'starts_at' => $start,
            'ends_at' => $ends,
            'duration_minutes' => $minutes > 0 ? $minutes : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
