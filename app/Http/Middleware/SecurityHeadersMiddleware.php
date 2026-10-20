<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * External hosts the app actually references from Blade templates
     * (checked 2026-10-20): cdn.jsdelivr.net and unpkg.com (Bootstrap,
     * Bootstrap Icons, AOS) and fonts.bunny.net (Breeze font).
     *
     * 'unsafe-inline' is present in script-src and style-src because the
     * Blade templates ship 10 inline <script> blocks, 15 inline event
     * handlers and 84 inline style attributes. Removing those is the
     * next tightening step; until then the policy is explicit about
     * what remains unrestricted rather than silently breaking pages.
     */
    private const POLICY = "default-src 'self'; ".
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; ".
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://fonts.bunny.net; ".
        "font-src 'self' https://cdn.jsdelivr.net https://fonts.bunny.net; ".
        "img-src 'self' data:; ".
        "connect-src 'self'; ".
        "base-uri 'self'; frame-ancestors 'self'; frame-src 'self'; form-action 'self' https://checkout.paystack.com; ".
        "object-src 'none'; upgrade-insecure-requests";

    /** Relaxed for `npm run dev` (Vite dev server, port 5173, HMR websockets). */
    private const LOCAL_POLICY = "default-src 'self'; ".
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com http://localhost:5173; ".
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://fonts.bunny.net; ".
        "font-src 'self' https://cdn.jsdelivr.net https://fonts.bunny.net; ".
        "img-src 'self' data: blob:; ".
        "connect-src 'self' http://localhost:5173 ws://localhost:5173; ".
        "base-uri 'self'; frame-ancestors 'self'; frame-src 'self'; form-action 'self' https://checkout.paystack.com; ".
        "object-src 'none'; upgrade-insecure-requests";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Origin-Agent-Cluster', '?1');
        $response->headers->set(
            'Content-Security-Policy',
            app()->environment('local') ? self::LOCAL_POLICY : self::POLICY
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
