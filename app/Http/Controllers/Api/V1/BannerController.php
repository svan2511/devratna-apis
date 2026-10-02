<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Public home banners — thin controller, direct DB read.
 * Koi active banner nahi = app bundled brand slides dikhati hai.
 */
class BannerController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        try {
            // Juda offer expire/pause ho to banner bhi app me nahi dikhega.
            // Bina offer wala banner hamesha dikhta hai.
            $banners = Banner::query()->active()->ordered()
                ->where(function ($q) {
                    $q->whereNull('offer_id')
                        ->orWhereHas('offer', fn ($oq) => $oq->live());
                })
                ->with('offer:id,name,ends_at')->get([
                    'id', 'title', 'subtitle', 'pill_text', 'target', 'offer_id', 'theme', 'sort_order',
                ])->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'pill_text' => $b->pill_text,
                'target' => $b->target,
                'theme' => $b->theme,
                'sort_order' => $b->sort_order,
                // Juda offer ki expiry — app "Ends in 2h" countdown dikhayegi.
                'offer_name' => $b->offer?->name,
                'offer_ends_at' => $b->offer?->ends_at?->toIso8601String(),
            ])->values();

            return $this->success($banners, 'Banners loaded.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not load banners. Please try again later.', 500);
        }
    }
}
