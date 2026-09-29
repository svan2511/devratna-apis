<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin guard — Sanctum auth ke baad lagta hai.
 * Sirf is_admin=true wale users ko aage jaane deta hai.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (! (bool) ($user->is_admin ?? false)) {
            return response()->json(['success' => false, 'message' => 'Forbidden. Admin access required.'], 403);
        }

        return $next($request);
    }
}
