<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rider guard - Sanctum auth ke baad lagta hai.
 * sirf is_rider=true + active delivery partner wale users aage jayenge.
 */
class EnsureRider
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (! $user->isRider()) {
            return response()->json(['success' => false, 'message' => 'Forbidden. Rider access required.'], 403);
        }

        $partner = $user->deliveryPartner;
        if ($partner === null || ! $partner->is_active) {
            return response()->json(['success' => false, 'message' => 'Rider profile is inactive. Contact the shop.'], 403);
        }

        return $next($request);
    }
}
