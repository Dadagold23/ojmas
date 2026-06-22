<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->hasAnyRole(['Super Admin', 'Managing Editor', 'Editor'])) {
            if (!$request->user()->two_factor_secret) {
                return redirect()->route('profile.edit')
                    ->with('error', 'Please enable two-factor authentication for this account.');
            }
        }

        return $next($request);
    }
}
