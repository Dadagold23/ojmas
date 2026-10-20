<?php

namespace Tests\Feature\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit fix S6 — the CSP used to ship with no default-src and no
 * script-src at all. The policy is now explicit. These assertions pin the
 * directives that keep the application working so that a future careless
 * edit cannot silently remove them:
 *
 *  - default-src 'self'         (the whole point of the finding)
 *  - script-src 'self' + the two CDNs the Blade templates actually load
 *    (cdn.jsdelivr.net, unpkg.com)
 *  - frame-src 'self'           (article.blade.php embeds its own PDF)
 *  - form-action allowing checkout.paystack.com (pre-existing payment flow)
 *
 * The header content in this environment is the production policy because
 * the middleware only relaxes it under APP_ENV=local.
 */
class CspHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_carries_the_explicit_csp_policy(): void
    {
        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($policy, 'The CSP header must be present.');

        foreach ([
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://fonts.bunny.net",
            "img-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self' https://checkout.paystack.com",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ] as $directive) {
            $this->assertStringContainsString($directive, $policy, "Missing CSP directive: {$directive}");
        }
    }
}
