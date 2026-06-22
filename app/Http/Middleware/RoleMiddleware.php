<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            abort(403, 'Unauthorized');
        }

        $normalizedRoles = collect($roles)
            ->flatMap(fn (string $roleGroup) => preg_split('/[|,]/', $roleGroup) ?: [])
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();

        if ($normalizedRoles !== [] && $request->user()->hasAnyRole($normalizedRoles)) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this area.');
    }
}
