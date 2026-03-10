<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Fields that must NOT be sanitized.
     * Passwords, tokens, OTPs, and free-text content fields are excluded.
     */
    private array $except = [
        'password',
        'password_confirmation',
        'current_password',
        '_token',
        'otp',
        'message',
        'content',
        'notes',
        'body',
        'description',
        'remarks',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $input = $request->all();
        $this->sanitize($input);
        $request->merge($input);

        return $next($request);
    }

    private function sanitize(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (in_array($key, $this->except, true)) {
                continue;
            }

            if (is_array($value)) {
                $this->sanitize($value);
            } elseif (is_string($value)) {
                // Trim whitespace
                $value = trim($value);
                // Strip null bytes — primary SQL/binary injection vector
                $value = str_replace("\0", '', $value);
                // Normalize line endings
                $value = str_replace(["\r\n", "\r"], "\n", $value);
            }
        }
    }
}
