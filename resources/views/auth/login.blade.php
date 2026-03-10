<x-guest-layout>

    <h2 class="auth-card-title">Welcome back</h2>
    <p class="auth-card-subtitle">Sign in to your account to continue</p>

    {{-- Session Status --}}
    @if (session('status'))
        <div class="auth-alert auth-alert-success mb-3">
            {{ session('status') }}
        </div>
    @endif

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="auth-alert mb-3" style="background:#fee2e2; color:#991b1b;">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="you@example.com"
                required
                autofocus
                autocomplete="username"
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
                <span class="input-group-text" onclick="togglePassword()" title="Show/hide password">
                    <span class="material-icons" id="passToggleIcon" style="font-size:18px;">visibility_off</span>
                </span>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        {{-- Remember Me + Forgot Password --}}
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="remember_me" name="remember">
                <label class="form-check-label" for="remember_me">Remember me</label>
            </div>

            @if (Route::has('password.request'))
                <a class="forgot-link" href="{{ route('password.request') }}">
                    Forgot password?
                </a>
            @endif
        </div>

        {{-- Submit --}}
        <button type="submit" class="btn-primary-crm">
            Sign In
        </button>
    </form>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('passToggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility_off';
            }
        }
    </script>

</x-guest-layout>
