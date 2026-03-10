<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorMail;
use App\Models\User;
use App\Services\LoginSessionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('2fa')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->session()->get('2fa');

        if (! $data) {
            return redirect()->route('login')->withErrors(['otp' => 'Session expired. Please login again.']);
        }

        if (now()->timestamp > $data['expires']) {
            $request->session()->forget('2fa');
            return redirect()->route('login')->withErrors(['email' => 'Verification code expired. Please login again.']);
        }

        $request->validate(['otp' => 'required|digits:6']);

        if (! Hash::check($request->otp, $data['code'])) {
            return back()->withErrors(['otp' => 'Invalid verification code. Please try again.']);
        }

        // OTP verified — clear 2FA session data
        $request->session()->forget('2fa');

        $user = User::findOrFail($data['user_id']);

        Auth::login($user, $data['remember'] ?? false);
        $request->session()->regenerate();

        // Create login session record with device info and location
        app(LoginSessionService::class)->createSession($user, $request);

        // Mark telecaller as online
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

    public function resend(Request $request): RedirectResponse
    {
        $data = $request->session()->get('2fa');

        if (! $data) {
            return redirect()->route('login');
        }

        $user = User::find($data['user_id']);
        if (! $user) {
            return redirect()->route('login');
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put('2fa', array_merge($data, [
            'code'    => bcrypt($otp),
            'expires' => now()->addMinutes(10)->timestamp,
        ]));

        try {
            Mail::to($user->email)->send(new TwoFactorMail($otp, $user->name));
        } catch (\Throwable $e) {
            Log::error('2FA resend failed: ' . $e->getMessage());
        }

        return back()->with('success', 'A new verification code has been sent to your email.');
    }
}
