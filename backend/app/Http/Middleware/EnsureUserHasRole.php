<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse-grained role gate for routes.
 *
 * Usage: Route::middleware('role:admin,dispatcher')->group(...)
 *
 * Must run after the auth:api middleware so $request->user() is resolved.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->role || ! in_array($user->role->name, $roles, true)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
