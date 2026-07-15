<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->isActive() || $user->isLocked())) {
            $user->currentAccessToken()?->delete();

            $message = $user->isLocked()
                ? 'Account is temporarily locked.'
                : 'Account is disabled.';

            return ApiResponse::error($message, [], 403);
        }

        return $next($request);
    }
}
