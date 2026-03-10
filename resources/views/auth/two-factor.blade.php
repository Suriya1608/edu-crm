<x-guest-layout>

    <h2 class="auth-card-title">Two-Factor Verification</h2>
    <p class="auth-card-subtitle">Enter the 6-digit code we sent to your email address.</p>

    {{-- Success Message --}}
    @if (session('success'))
        <div class="auth-alert auth-alert-success mb-3">
            {{ session('success') }}
        </div>
    @endif

    {{-- Errors --}}
    @if ($errors->any())
        <div class="auth-alert mb-3" style="background:#fee2e2; color:#991b1b;">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf

        <div class="mb-4">
            <label for="otp" class="form-label">Verification Code</label>
            <input
                id="otp"
                type="text"
                name="otp"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                class="form-control text-center @error('otp') is-invalid @enderror"
                placeholder="— — — — — —"
                autofocus
                autocomplete="one-time-code"
                style="font-size: 24px; letter-spacing: 8px; font-weight: 700;"
            >
            @error('otp')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text text-muted mt-1">
                <span class="material-icons" style="font-size:13px; vertical-align: middle;">schedule</span>
                Code expires in 10 minutes.
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-3" style="height:44px; font-weight:600;">
            Verify &amp; Sign In
        </button>
    </form>

    <div class="text-center" style="font-size:13px; color:#64748b;">
        Didn't receive the code?
        <form method="POST" action="{{ route('two-factor.resend') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-link p-0" style="font-size:13px; vertical-align:baseline;">
                Resend
            </button>
        </form>
        &nbsp;|&nbsp;
        <a href="{{ route('login') }}" style="color:#64748b;">Back to Login</a>
    </div>

</x-guest-layout>
