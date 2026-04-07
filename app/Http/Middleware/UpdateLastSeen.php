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
            $cacheKey = 'last_seen_update_' . Auth::id();

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
