<?php

use App\Http\Middleware\SanitizeInput;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        api: __DIR__ . '/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->append(HandleCors::class);
        // Apply security headers to every response
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [
            \App\Http\Middleware\UpdateLastSeen::class,
            // Sanitize POST/PUT/PATCH input — strips null bytes, trims whitespace
            SanitizeInput::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'twilio/voice',
            'twilio/status',
            'twilio/recording',
            'twilio/callback',
            'webhook/exotel',
            'crm-store-lead',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
