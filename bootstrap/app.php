<?php

use App\Http\Middleware\SanitizeInput;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        api: __DIR__ . '/../routes/api.php',
        then: function () {
            // Superadmin routes FIRST — domain-constrained routes must be registered
            // before the unconstrained web routes, otherwise web.php /login matches first.
            $domain  = env('APP_DOMAIN', 'insighttechnology.in');
            $central = env('CENTRAL_DOMAIN', 'educrm');
            Route::middleware('web')
                ->domain($central . '.' . $domain)
                ->prefix('super-admin')
                ->group(base_path('routes/superadmin.php'));

            // Regular tenant web routes (no domain constraint — matches everything else)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        },
    )
    ->withProviders([
        App\Providers\HorizonServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->append(HandleCors::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->web(prepend: [
            TenantMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\UpdateLastSeen::class,
            SanitizeInput::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Sanctum stateful middleware for API routes (SPA / cookie-based auth)
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'crm-store-lead',
            'webhooks/meta/*',
            'broadcasting/auth',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        // On CSRF token mismatch (419 Page Expired), clear the session and either:
        // - Return a 419 JSON response for AJAX/fetch requests (so the client-side
        //   interceptor can trigger a full page redirect to login), or
        // - Redirect directly to login for regular full-page form submissions.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message'  => 'Session expired. Please log in again.',
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()->route('login', [], 303)
                ->withErrors(['session_expired' => 'Your session has expired. Please sign in again.']);
        });
    })->create();
