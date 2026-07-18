<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates acceptance-only verification endpoints.
 * Never available when APP_ENV=production.
 */
class EnsureAcceptanceEnvironment
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('acceptance', 'testing', 'local')) {
            abort(404);
        }

        if (app()->environment('production')) {
            abort(404);
        }

        return $next($request);
    }
}
