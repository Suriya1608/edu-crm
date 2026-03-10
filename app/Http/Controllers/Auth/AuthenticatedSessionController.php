<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\TwoFactorMail;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSession;
use App\Services\LoginSessionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();

        // Check if 2FA is enabled for this user's role (default: enabled)
        $twoFaEnabled = (bool) Setting::get('2fa_' . $user->role, '1');

        if (! $twoFaEnabled) {
            // 2FA disabled for this role — complete login directly
            app(LoginSessionService::class)->createSession($user, $request);

            if ($user->role === 'telecaller') {
                $user->update([
                    'is_online'    => true,
                    'last_seen_at' => Carbon::now('Asia/Kolkata'),
                ]);
            }

            return match ($user->role) {
                'admin'      => redirect()->route('admin.dashboard'),
                'manager'    => redirect()->route('manager.dashboard'),
                'telecaller' => redirect()->route('telecaller.dashboard'),
                default      => redirect('/'),
            };
        }

        // 2FA enabled — generate OTP and redirect to verification page
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store 2FA pending state in session BEFORE logging out
        $request->session()->put('2fa', [
            'user_id'  => $user->id,
            'code'     => bcrypt($otp),
            'expires'  => now()->addMinutes(10)->timestamp,
            'remember' => $request->boolean('remember'),
        ]);

        // Log out the user (session remains intact for 2FA pending state)
        Auth::guard('web')->logout();

        // Send OTP email
        try {
            Mail::to($user->email)->send(new TwoFactorMail($otp, $user->name));
        } catch (\Throwable $e) {
            Log::error('2FA OTP email failed: ' . $e->getMessage());
        }

        return redirect()->route('two-factor.show');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->closeSession(auth()->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function idleLogout(Request $request): RedirectResponse
    {
        $this->closeSession(auth()->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You were logged out due to inactivity.');
    }

    private function closeSession(?User $user): void
    {
        if (! $user) {
            return;
        }

        $session = UserSession::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->latest()
            ->first();

        if ($session) {
            $logoutTime = Carbon::now('Asia/Kolkata');
            try {
                $loginAt  = Carbon::createFromFormat('Y-m-d H:i:s', $session->login_at, 'Asia/Kolkata');
                $duration = max(0, (int) $loginAt->diffInMinutes($logoutTime));
            } catch (\Throwable) {
                $duration = 0;
            }
            $session->update([
                'logout_at'        => $logoutTime,
                'duration_minutes' => $duration,
            ]);
        }

        if ($user->role === 'telecaller') {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_online') &&
                \Illuminate\Support\Facades\Schema::hasColumn('users', 'last_seen_at')) {
                User::where('id', $user->id)->update([
                    'is_online'    => false,
                    'last_seen_at' => Carbon::now('Asia/Kolkata'),
                ]);
            }
        }
    }
}
