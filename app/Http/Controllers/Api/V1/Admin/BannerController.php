<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Home banner manager — CRUD + active toggle + order.
 * Public app cache ko koi alag invalidation nahi chahiye (direct DB read).
 */
class BannerController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $banners = Banner::query()->ordered()->get();

        return $this->success(
            ['banners' => $banners, 'themes' => Banner::themes()],
            'Banners loaded.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $banner = Banner::create([
            'title' => trim($data['title']),
            'subtitle' => isset($data['subtitle']) ? trim((string) $data['subtitle']) : null,
            'pill_text' => isset($data['pill_text']) ? trim((string) $data['pill_text']) : null,
            'target' => isset($data['target']) && trim((string) $data['target']) !== '' ? trim((string) $data['target']) : null,
            'offer_id' => isset($data['offer_id']) && trim((string) $data['offer_id']) !== '' ? (int) $data['offer_id'] : null,
            'theme' => $data['theme'] ?? 'espresso',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $this->success(['banner' => $banner->fresh()], 'Banner created.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $banner = Banner::query()->findOrFail($id);
        $data = $this->validatePayload($request);

        $banner->update([
            'title' => trim($data['title']),
            'subtitle' => isset($data['subtitle']) ? trim((string) $data['subtitle']) : null,
            'pill_text' => isset($data['pill_text']) ? trim((string) $data['pill_text']) : null,
            'target' => isset($data['target']) && trim((string) $data['target']) !== '' ? trim((string) $data['target']) : null,
            'offer_id' => isset($data['offer_id']) && trim((string) $data['offer_id']) !== '' ? (int) $data['offer_id'] : null,
            'theme' => $data['theme'] ?? $banner->theme,
            'sort_order' => (int) ($data['sort_order'] ?? $banner->sort_order),
            'is_active' => (bool) ($data['is_active'] ?? $banner->is_active),
        ]);

        return $this->success(['banner' => $banner->fresh()], 'Banner updated.');
    }

    public function setActive(Request $request, int $id): JsonResponse
    {
        $banner = Banner::query()->findOrFail($id);
        $data = $request->validate(['is_active' => 'required|boolean']);

        $banner->update(['is_active' => (bool) $data['is_active']]);

        return $this->success(
            ['banner' => $banner->fresh()],
            $banner->is_active ? 'Banner is now live.' : 'Banner hidden from app.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $banner = Banner::query()->findOrFail($id);
        $banner->delete();

        return $this->success(null, 'Banner deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|min:2|max:100',
            'subtitle' => 'nullable|string|max:150',
            'pill_text' => 'nullable|string|max:40',
            'target' => 'nullable|string|max:50',
            'offer_id' => 'nullable|integer|exists:offers,id',
            'theme' => 'nullable|string|in:espresso,terracotta,gold,cream,forest',
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
