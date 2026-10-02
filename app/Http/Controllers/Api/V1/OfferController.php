<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use App\Models\Offer;
use Throwable;

/**
 * Public live offers — app checkout strip + bill preview ke liye.
 * Billing ka final hisaab OrderController (OfferEngine) karta hai.
 */
class OfferController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        try {
            $offers = Offer::query()->live()->ordered()->with('freeItem:id,name')->get()->map(
                fn (Offer $o) => $this->publicOffer($o)
            )->values();

            return $this->success($offers, 'Offers loaded.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not load offers. Please try again later.', 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function publicOffer(Offer $offer): array
    {
        return [
            'id' => $offer->id,
            'name' => $offer->name,
            'description' => $offer->description,
            'target_type' => $offer->target_type,
            'target_value' => $offer->target_value,
            'discount_type' => $offer->discount_type,
            'discount_value' => $offer->discount_value,
            'discount_scope' => $offer->discount_scope ?? 'order',
            'free_item_id' => $offer->free_item_id,
            'free_item_name' => $offer->freeItem?->name,
            // Ranking preview ke liye (server billing me half_price proxy use hota hai).
            'free_item_price' => (int) ($offer->freeItem?->half_price ?? $offer->freeItem?->price_value ?? 0),
            'min_order' => $offer->min_order,
            'min_qty' => $offer->min_qty ?? 1,
            'starts_at' => $offer->starts_at?->toIso8601String(),
            'ends_at' => $offer->ends_at?->toIso8601String(),
        ];
    }
}
