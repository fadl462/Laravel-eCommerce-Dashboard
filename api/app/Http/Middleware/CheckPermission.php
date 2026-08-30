<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission guard. Usage in routes/api.php:
 *   Route::put('/products/{product}', [...])->middleware('permission:products.edit');
 * Backed by User::hasPermission(), which resolves through the user's Role and
 * its assigned Permission rows — matches the granular keys the dashboard's
 * Roles & Permissions screen displays (products.view, orders.cancel, etc.).
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(403, 'Account is not active.');
        }

        if (! $user->hasPermission($permission)) {
            abort(403, "Missing required permission: {$permission}");
        }

        return $next($request);
    }
}
