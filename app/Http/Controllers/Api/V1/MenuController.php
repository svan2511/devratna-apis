<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Public menu catalogue — thin controller, data via MenuRepository.
 */
class MenuController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MenuRepositoryInterface $menu) {}

    public function index(): JsonResponse
    {
        try {
            $categories = $this->menu->fullMenu();

            return $this->success(CategoryResource::collection($categories), 'Menu loaded.');
        } catch (Throwable $e) {
            report($e);

            return $this->failure('Could not load the menu. Please try again later.', 500);
        }
    }
}
