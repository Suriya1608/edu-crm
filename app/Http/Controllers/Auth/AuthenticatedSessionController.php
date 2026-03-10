<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\UserSession;
use App\Models\User;
use Carbon\Carbon;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        UserSession::create([
            'user_id' => $user->id,
            'login_at' => Carbon::now('Asia/Kolkata')
        ]);

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'manager') {
            return redirect()->route('manager.dashboard');
        }

        if ($user->role === 'telecaller') {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_online') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'last_seen_at')) {
                User::where('id', $user->id)->update([
                    'is_online' => true,
                    'last_seen_at' => Carbon::now('Asia/Kolkata')
                ]);
            }
            return redirect()->route('telecaller.dashboard');
        }

        return redirect('/');
    }




    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user) {

            $session = UserSession::where('user_id', $user->id)
                ->whereNull('logout_at')
                ->latest()
                ->first();

            if ($session) {
                $logoutTime = Carbon::now('Asia/Kolkata');

                $session->update([
                    'logout_at' => $logoutTime,
                    'duration_minutes' => $logoutTime->diffInMinutes($session->login_at)
                ]);
            }

            if ($user->role === 'telecaller') {
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_online') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'last_seen_at')) {
                    User::where('id', $user->id)->update([
                        'is_online' => false,
                        'last_seen_at' => Carbon::now('Asia/Kolkata')
                    ]);
                }
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
