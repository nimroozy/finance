<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Block authenticated routes when the user must change their password.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change) {
            $routeName = $request->route()?->getName();
            $allowed = [
                'api.v1.auth.change-password',
                'api.v1.auth.logout',
                'api.v1.auth.me',
            ];

            if (! in_array($routeName, $allowed, true)
                && ! $request->is('api/v1/auth/change-password')
                && ! $request->is('api/v1/auth/logout')
                && ! $request->is('api/v1/auth/me')) {
                return ApiResponse::error(
                    'Password change required before continuing.',
                    [],
                    403
                );
            }
        }

        return $next($request);
    }
}
