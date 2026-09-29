<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menu manager — categories + dishes CRUD + availability toggle.
 * Public menu cache ko koi alag invalidation nahi chahiye (direct DB read).
 */
class MenuController extends Controller
{
    use ApiResponse;

    // ---------- categories ----------

    public function categories(): JsonResponse
    {
        $cats = Category::query()->ordered()->get(['id', 'name', 'slug', 'sort_order']);

        return $this->success(['categories' => $cats], 'Categories loaded.');
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);

        $slug = Str::slug($data['name']);
        if (Category::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $cat = Category::create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return $this->success(['category' => $cat], 'Category created.', 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);

        $cat = Category::query()->findOrFail($id);
        $cat->update(['name' => trim($data['name']), 'sort_order' => (int) ($data['sort_order'] ?? $cat->sort_order)]);

        return $this->success(['category' => $cat->fresh()], 'Category updated.');
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $cat = Category::query()->findOrFail($id);
        if ($cat->items()->exists()) {
            return $this->failure('Is category me dishes hain — pehle unhe move/delete karo.', 422);
        }
        $cat->delete();

        return $this->success(null, 'Category deleted.');
    }

    // ---------- items ----------

    public function items(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:categories,id',
            'limit' => 'nullable|integer|min:1|max:300',
        ]);

        $query = MenuItem::query()->with('category:id,name')->ordered();
        if (! empty($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }
        if (! empty($data['q'])) {
            $query->where('name', 'like', '%'.trim($data['q']).'%');
        }

        $items = $query->limit((int) ($data['limit'] ?? 200))->get();

        return $this->success(['items' => $items], 'Menu items loaded.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateItem($request);

        try {
            $item = MenuItem::create($data);

            return $this->success(['item' => $item->fresh()], 'Dish created.', 201);
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not create the dish.', 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->validateItem($request);
        $item = MenuItem::query()->findOrFail($id);
        $item->update($data);

        return $this->success(['item' => $item->fresh()], 'Dish updated.');
    }

    public function setAvailability(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['is_available' => 'required|boolean']);
        $item = MenuItem::query()->findOrFail($id);
        $item->update(['is_available' => (bool) $data['is_available']]);

        return $this->success(['item' => $item->fresh()], $item->is_available ? 'Dish is now visible.' : 'Dish hidden from menu.');
    }

    public function destroy(int $id): JsonResponse
    {
        MenuItem::query()->findOrFail($id)->delete();

        return $this->success(null, 'Dish deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request): array
    {
        $data = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|min:2|max:150',
            'description' => 'nullable|string|max:1000',
            'price_label' => 'required|string|min:1|max:50',
            'price_value' => 'required|integer|min:0|max:100000',
            'half_price' => 'required|integer|min:0|max:100000',
            'mid_price' => 'nullable|integer|min:0|max:100000',
            'full_price' => 'nullable|integer|min:0|max:100000',
            'is_veg' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'image_key' => 'nullable|string|max:100',
            'is_available' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:10000',
        ]);

        return [
            'category_id' => (int) $data['category_id'],
            'name' => trim($data['name']),
            'description' => isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            'price_label' => trim($data['price_label']),
            'price_value' => (int) $data['price_value'],
            'half_price' => (int) $data['half_price'],
            'mid_price' => $data['mid_price'] ?? null,
            'full_price' => $data['full_price'] ?? null,
            'is_veg' => (bool) ($data['is_veg'] ?? true),
            'is_bestseller' => (bool) ($data['is_bestseller'] ?? false),
            'image_key' => isset($data['image_key']) && trim((string) $data['image_key']) !== '' ? trim((string) $data['image_key']) : null,
            'is_available' => (bool) ($data['is_available'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
