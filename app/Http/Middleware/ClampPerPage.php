<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Clamp the `per_page` pagination param globally so a single request can't dump a
 * whole table (e.g. ?per_page=100000). 1000 keeps the app's bulk per_page=500
 * loads working while killing the DoS/memory vector. Applied to the API group, so
 * no per-controller edits are needed.
 */
class ClampPerPage
{
    private const MAX = 1000;

    public function handle(Request $request, Closure $next)
    {
        if ($request->has('per_page')) {
            $pp = (int) $request->input('per_page');
            $clamped = max(1, min($pp, self::MAX));
            if ($clamped !== $pp) {
                $request->merge(['per_page' => $clamped]);
            }
        }

        return $next($request);
    }
}
