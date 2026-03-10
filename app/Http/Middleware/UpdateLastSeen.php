<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
            User::where('id', Auth::id())->update([
                'is_online'    => true,
                'last_seen_at' => now(),
            ]);
        }

        return $next($request);
    }
}
