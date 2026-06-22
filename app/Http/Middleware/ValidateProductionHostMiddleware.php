<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateProductionHostMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->environment('production')) {
            return $next($request);
        }

        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $configuredHost = is_string($configuredHost) ? strtolower($configuredHost) : null;
        $requestHost = strtolower($request->getHost());

        $allowedHosts = array_filter(array_unique([
            $configuredHost,
            $configuredHost ? 'www.' . preg_replace('/^www\./i', '', $configuredHost) : null,
        ]));

        if (!in_array($requestHost, $allowedHosts, true)) {
            abort(400, 'Invalid host header.');
        }

        return $next($request);
    }
}
