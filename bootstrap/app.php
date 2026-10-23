<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\URL;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        // Corrective Module 6: the manuscript workspace save and tracked
        // change endpoints carry the author's content itself. The framework's
        // global string trimming erased run-boundary spaces there ("x" + "2"
        // + " and " collapsed into "x2and"), silently corrupting the text on
        // every save. Exempt those two payload-carrier endpoints; every other
        // endpoint keeps standard trimming.
        $middleware->trimStrings(except: [
            fn ($request) => $request->is('workspace/submissions/*/save'),
            fn ($request) => $request->is('workspace/submissions/*/changes'),
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paystack',
        ]);

        if (env('APP_ENV') === 'production') {
            $middleware->web(append: [
                \App\Http\Middleware\TrustProxies::class,
                \App\Http\Middleware\ValidateProductionHostMiddleware::class,
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booted(function () {
        if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }
    })
    ->create();
