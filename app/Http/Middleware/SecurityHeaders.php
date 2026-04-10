<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $cspNonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $cspNonce);

        if ($this->isHtmlResponse($response)) {
            $content = (string) $response->getContent();
            if ($content !== '') {
                $content = preg_replace('/<script\b(?![^>]*\bnonce=)([^>]*)>/i', '<script nonce="' . $cspNonce . '"$1>', $content) ?? $content;
                $content = preg_replace('/<style\b(?![^>]*\bnonce=)([^>]*)>/i', '<style nonce="' . $cspNonce . '"$1>', $content) ?? $content;
                $response->setContent($content);
            }
        }

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Legacy XSS browser protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict browser features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(), payment=(), usb=()');

        // HSTS — only sent over HTTPS
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Content Security Policy
        // Inline scripts are nonce-protected. Inline styles are still temporarily allowed
        // because the current UI uses style attributes extensively across Blade templates.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$cspNonce}' cdn.jsdelivr.net cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' 'nonce-{$cspNonce}' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com",
            "font-src 'self' fonts.gstatic.com fonts.googleapis.com cdnjs.cloudflare.com data:",
            "img-src 'self' data: blob: https://placehold.co https://cdn-icons-png.flaticon.com",
            "connect-src 'self' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com fonts.gstatic.com wss: https://api.bom.tcn.com https://auth.tcn.com https://api.tcn.com",
            "frame-src 'self' blob:",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Remove server fingerprinting headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        return str_contains($contentType, 'text/html');
    }
}
