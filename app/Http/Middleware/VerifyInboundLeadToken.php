<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyInboundLeadToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) env('LEAD_CAPTURE_SHARED_SECRET', ''));

        // Fail closed in non-local environments when secret is missing.
        if ($expected === '' && !app()->environment(['local', 'testing'])) {
            Log::error('Lead capture rejected: LEAD_CAPTURE_SHARED_SECRET is not configured.');

            return response()->json([
                'success' => false,
                'message' => 'Lead intake is not configured.',
            ], 503);
        }

        // Allow local/testing without secret to simplify development.
        if ($expected === '') {
            return $next($request);
        }

        $provided = trim((string) (
            $request->header('X-Lead-Capture-Token')
            ?: $request->bearerToken()
            ?: $request->input('capture_token', '')
        ));

        if ($provided === '' || !hash_equals($expected, $provided)) {
            Log::warning('Lead capture token verification failed', [
                'ip' => $request->ip(),
                'origin' => $request->headers->get('origin'),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized lead capture request.',
            ], 401);
        }

        return $next($request);
    }
}

