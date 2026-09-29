<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-only backend — browsers/tools hitting /api/* without an
 * Accept: application/json header still get JSON (401/422/...) instead
 * of redirects to a `login` route that doesn't exist.
 */
class ForceJsonForApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
