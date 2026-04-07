@extends('layouts.app')

@section('page_title', 'TCN Settings')

@section('content')

{{-- Settings nav tabs --}}
<ul class="nav nav-tabs mb-4" id="settingsTab">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.settings.call') }}">Call Provider</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-semibold" href="{{ route('admin.settings.tcn') }}">TCN Global</a>
    </li>
</ul>

<div class="row g-4">

    {{-- ── Left: Global config form ─────────────────────────────── --}}
    <div class="col-lg-7">
        <div class="card p-4">
            <h6 class="fw-bold mb-1">
                <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">settings_phone</span>
                TCN Global Configuration
            </h6>
            <p class="text-muted small mb-4">
                These credentials are shared across all agents.
                The <strong>Client Secret</strong> is stored encrypted and never sent to the browser.
            </p>

            @if(session('success'))
                <div class="alert alert-success d-flex align-items-center gap-2 py-2">
                    <span class="material-icons" style="font-size:18px;">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                    <span class="material-icons" style="font-size:18px;">error</span>
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.tcn.update') }}">
                @csrf

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Client ID *</label>
                        <input type="text" name="client_id" class="form-control @error('client_id') is-invalid @enderror"
                               value="{{ old('client_id', $client_id) }}" required placeholder="e.g. my-tcn-client">
                        @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Client Secret
                            <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">Encrypted</span>
                        </label>
                        <div class="input-group">
                            <input type="password" name="client_secret" id="clientSecretInput"
                                   class="form-control @error('client_secret') is-invalid @enderror"
                                   placeholder="{{ $client_secret ? '••••••••  (leave blank to keep)' : 'Enter client secret' }}">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="toggleSecret()" title="Show / Hide">
                                <span class="material-icons" id="secretEyeIcon" style="font-size:18px;">visibility</span>
                            </button>
                        </div>
                        @error('client_secret')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <small class="text-muted">Leave blank to keep the existing secret.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Auth URL *</label>
                        <input type="url" name="auth_url" class="form-control @error('auth_url') is-invalid @enderror"
                               value="{{ old('auth_url', $auth_url) }}" required>
                        @error('auth_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">API Base URL *</label>
                        <input type="url" name="base_url" class="form-control @error('base_url') is-invalid @enderror"
                               value="{{ old('base_url', $base_url) }}" required>
                        @error('base_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Redirect URI (OAuth callback)</label>
                        <input type="url" name="redirect_uri" class="form-control @error('redirect_uri') is-invalid @enderror"
                               value="{{ old('redirect_uri', $redirect_uri) }}"
                               placeholder="{{ url('/tcn/auth/callback') }}">
                        @error('redirect_uri')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Register this in your TCN application: <code>{{ url('/tcn/auth/callback') }}</code></small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Outbound Caller ID</label>
                        <input type="text" name="caller_id" class="form-control @error('caller_id') is-invalid @enderror"
                               value="{{ old('caller_id', $caller_id) }}" placeholder="+91XXXXXXXXXX">
                        @error('caller_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                </div>

                <div class="mt-4 d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons me-1" style="font-size:18px;">save</span>
                        Save Settings
                    </button>
                    <a href="{{ route('tcn.auth.connect') }}" class="btn btn-outline-success">
                        <span class="material-icons me-1" style="font-size:18px;">link</span>
                        {{ $connected ? 'Reconnect TCN' : 'Connect TCN Account' }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Right: Status + info ─────────────────────────────────── --}}
    <div class="col-lg-5">

        {{-- Connection status --}}
        <div class="card p-4 mb-3">
            <h6 class="fw-bold mb-3">
                <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">cable</span>
                Global OAuth Status
            </h6>
            @if($connected)
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success px-3 py-2" style="font-size:13px;">
                        <span class="material-icons me-1" style="font-size:14px;vertical-align:middle;">check_circle</span>
                        Connected
                    </span>
                    <span class="text-muted small">Global refresh token stored</span>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    This global token is used when no per-user account is mapped.
                    For production use, assign individual refresh tokens to each agent.
                </p>
            @else
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary px-3 py-2" style="font-size:13px;">Not Connected</span>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    Click <strong>Connect TCN Account</strong> to complete OAuth and store the global refresh token.
                </p>
            @endif
        </div>

        {{-- Requirements --}}
        <div class="card p-4 mb-3 border-warning" style="border-left:4px solid #f59e0b!important;">
            <h6 class="fw-bold mb-2" style="color:#f59e0b;">
                <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;">warning</span>
                Requirements
            </h6>
            <ul class="mb-0 ps-3 small text-muted">
                <li>HTTPS is <strong>mandatory</strong> for WebRTC/SIP</li>
                <li>Microphone permission must be granted</li>
                <li>Chrome / Firefox / Edge (Safari limited)</li>
                <li>Per-user refresh tokens needed for multi-agent setup</li>
            </ul>
        </div>

        {{-- Test console --}}
        <div class="card p-4">
            <h6 class="fw-bold mb-2">
                <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">bug_report</span>
                Developer Tools
            </h6>
            <p class="text-muted small mb-3">
                Use the test console to validate credentials and diagnose connection issues before deploying to agents.
            </p>
            <a href="{{ route('tcn.test') }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                <span class="material-icons me-1" style="font-size:16px;">open_in_new</span>
                Open TCN Test Console
            </a>
        </div>

    </div>
</div>

@push('scripts')
<script>
function toggleSecret() {
    const input = document.getElementById('clientSecretInput');
    const icon  = document.getElementById('secretEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}
</script>
@endpush

@endsection
