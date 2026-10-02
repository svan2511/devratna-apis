<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Offer;

/**
 * Offer engine — billing ka single source of truth.
 * Client jo bhejta hai wo sirf items hain; discount/free lines hamesha
 * yahi decide karta hai (server prices + live window se).
 *
 * Ek order pe best ek offer lagti hai (sabse bada fayda).
 */
final class OfferEngine
{
    /**
     * Priced paid lines (unit/qty final) + food subtotal se best offer nikalo.
     *
     * @param  array<int, array{id: int, name: string, portion: string, qty: int, unit: int, category: string}>  $lines
     * @return array{offer: ?Offer, discount: int, freeLines: array<int, array{id: int, name: string, portion: string, qty: int, unit: int, free: bool}>}
     */
    public static function bestFor(array $lines, int $subtotal): array
    {
        $best = ['offer' => null, 'discount' => 0, 'freeLines' => [], 'freeValue' => 0];

        if ($lines === [] || $subtotal <= 0) {
            return $best;
        }

        $offers = Offer::query()->live()->ordered()->with('freeItem')->get();
        foreach ($offers as $offer) {
            $result = self::evaluate($offer, $lines, $subtotal);
            if ($result === null) {
                continue;
            }
            // Zyada fayda = winner. Barabar ho to pehle wala (sort_order) jeetega.
            $benefit = $result['discount'] + $result['freeValue'];
            $bestBenefit = $best['discount'] + $best['freeValue'];
            if ($benefit > $bestBenefit) {
                $best = $result;
            }
        }

        unset($best['freeValue']);

        return $best;
    }

    /**
     * @param  array<int, array{id: int, name: string, portion: string, qty: int, unit: int, category: string}>  $lines
     * @return array{offer: Offer, discount: int, freeLines: array<int, array{id: int, name: string, portion: string, qty: int, unit: int, free: bool}>, freeValue: int}|null
     */
    private static function evaluate(Offer $offer, array $lines, int $subtotal): ?array
    {
        if ($subtotal < (int) $offer->min_order) {
            return null;
        }

        $eligible = array_values(array_filter(
            $lines,
            fn (array $l) => self::matches($offer, $l)
        ));
        if ($eligible === []) {
            return null;
        }

        $eligibleSum = array_sum(array_map(fn (array $l) => $l['unit'] * $l['qty'], $eligible));
        $eligibleQty = array_sum(array_map(fn (array $l) => $l['qty'], $eligible));

        // "Buy 2 Kadhai Paneer" jaisi shart — eligible dishes ki qty kam ho to offer nahi.
        if ($eligibleQty < max(1, (int) $offer->min_qty)) {
            return null;
        }

        $discount = match ($offer->discount_type) {
            // Percent hamesha eligible total pe (qty ke hisab se khud badhta hai).
            'percent' => (int) floor($eligibleSum * min(90, max(0, (int) $offer->discount_value)) / 100),
            // Flat: scope=item matlab HAR eligible dish pe (5 thali = 5x), scope=order matlab ek baar.
            'flat' => $offer->discount_scope === 'item'
                ? min((int) $offer->discount_value * $eligibleQty, $eligibleSum)
                : min((int) $offer->discount_value, $eligibleSum),
            default => 0,
        };

        // Free item — sirf available ho to (warna discount part phir bhi lagega).
        $freeLines = [];
        $freeValue = 0;
        $freeItem = $offer->freeItem;
        if ($freeItem instanceof MenuItem && (bool) $freeItem->is_available) {
            $freeLines[] = [
                'id' => $freeItem->id,
                'name' => $freeItem->name,
                'portion' => 'single',
                'qty' => 1,
                'unit' => 0,
                'free' => true,
            ];
            $freeValue = (int) ($freeItem->half_price ?? $freeItem->price_value ?? 0);
        }

        if ($discount <= 0 && $freeLines === []) {
            return null;
        }

        return ['offer' => $offer, 'discount' => $discount, 'freeLines' => $freeLines, 'freeValue' => $freeValue];
    }

    /**
     * @param  array{id: int, category: string}  $line
     */
    private static function matches(Offer $offer, array $line): bool
    {
        return match ($offer->target_type) {
            'category' => $line['category'] === (string) $offer->target_value,
            'item' => (int) $line['id'] === (int) $offer->target_value,
            default => true,
        };
    }
}
