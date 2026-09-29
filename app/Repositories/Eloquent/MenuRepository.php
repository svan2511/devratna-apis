<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\MenuRepositoryInterface;
use Illuminate\Support\Collection;

class MenuRepository implements MenuRepositoryInterface
{
    /**
     * @return Collection<int, Category>
     */
    public function fullMenu(): Collection
    {
        // Saare items — available + unavailable dono. App unavailable ko
        // "Not available today" dikhata hai; order time pe store() block karta hai.
        return Category::query()
            ->ordered()
            ->with(['items' => fn ($query) => $query->ordered()])
            ->get();
    }
}
