<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $tenantId = (app()->bound('tenant') && app('tenant')) ? app('tenant')->id : 0;
            $cacheKey = 'last_seen_' . $tenantId . '_' . Auth::id();

            if (!Cache::has($cacheKey)) {
                User::where('id', Auth::id())->update([
                    'is_online'    => true,
                    'last_seen_at' => now(),
                ]);
                Cache::put($cacheKey, true, 25);
            }
        }

        return $next($request);
    }
}
