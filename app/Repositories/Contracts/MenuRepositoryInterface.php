<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Support\Collection;

interface MenuRepositoryInterface
{
    /**
     * Full catalogue: categories with their available items, ordered.
     * Single eager-loaded query — no N+1.
     *
     * @return Collection<int, Category>
     */
    public function fullMenu(): Collection;
}
