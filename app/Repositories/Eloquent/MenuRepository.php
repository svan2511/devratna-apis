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
        return Category::query()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->ordered()])
            ->get();
    }
}
