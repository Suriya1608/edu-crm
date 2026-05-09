@extends('layouts.app')

@section('page_title', 'Softphone Settings')

@section('content')

@include('admin.settings.partials.nav')

<div class="row g-4">

    {{-- ── Left: config form ──────────────────────────────────────── --}}
    <div class="col-lg-7">
        <div class="card p-4">
            <h6 class="fw-bold mb-1">
                <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">settings_phone</span>
                Softphone Configuration
            </h6>
            <p class="text-muted small mb-4">
                Softphone credentials. The <strong>Client Secret</strong> is stored encrypted and never sent to the browser.
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

                    {{-- Client ID --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Client ID *</label>
                        <input type="text" name="client_id" class="form-control @error('client_id') is-invalid @enderror"
                               value="{{ old('client_id', $client_id) }}" required placeholder="e.g. n37f65fou66o37ul">
                        @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Client Secret --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Client Secret
                            <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">Encrypted</span>
                        </label>
                        <div class="input-group">
                            <input type="password" name="client_secret" id="clientSecretInput"
                                   class="form-control @error('client_secret') is-invalid @enderror"
                                   placeholder="{{ $client_secret ? '••••••••  (leave blank to keep)' : 'Enter client secret' }}">
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleField('clientSecretInput','secretEyeIcon')">
                                <span class="material-icons" id="secretEyeIcon" style="font-size:18px;">visibility</span>
                            </button>
                        </div>
                        @error('client_secret')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    {{-- Refresh Token --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Refresh Token
                            <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">Encrypted</span>
                        </label>
                        <div class="input-group">
                            <input type="password" name="refresh_token" id="refreshTokenInput"
                                   class="form-control @error('refresh_token') is-invalid @enderror"
                                   placeholder="{{ $connected ? '••••••••  (leave blank to keep)' : 'Paste token or use Connect button below' }}">
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleField('refreshTokenInput','refreshEyeIcon')">
                                <span class="material-icons" id="refreshEyeIcon" style="font-size:18px;">visibility</span>
                            </button>
                        </div>
                        @error('refresh_token')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <small class="text-muted">Paste manually or use the <strong>Connect Softphone Account</strong> button to get it via OAuth.</small>
                    </div>

                    {{-- Auth URL / API Base --}}
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

                    {{-- Caller ID --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Outbound Caller ID</label>
                        <input type="text" name="caller_id" class="form-control @error('caller_id') is-invalid @enderror"
                               value="{{ old('caller_id', $caller_id) }}" placeholder="+91XXXXXXXXXX">
                        @error('caller_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Relay URL --}}
                    <div class="col-md-8">
                        <label class="form-label fw-semibold d-flex align-items-center gap-1">
                            <span class="material-icons" style="font-size:15px;color:#6366f1;">hub</span>
                            Central Relay URL
                            <span class="badge bg-primary ms-1" style="font-size:10px;">Multi-client</span>
                        </label>
                        <div class="input-group">
                            <input type="url" name="relay_url" id="relayUrlInput"
                                   class="form-control @error('relay_url') is-invalid @enderror"
                                   value="{{ old('relay_url', $relay_url) }}"
                                   placeholder="{{ route('tcn.auth.relay') }}">
                            <button type="button" class="btn btn-outline-secondary" onclick="copyRelayUrl()">
                                <span class="material-icons" id="relayUrlCopyIcon" style="font-size:18px;">content_copy</span>
                            </button>
                        </div>
                        @error('relay_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <small class="text-muted">
                            Register this URL once with your softphone provider.
                            <a href="{{ route('admin.tcn-relay-clients.index') }}" class="ms-1">Manage allowed clients →</a>
                        </small>
                    </div>

                    {{-- Legacy redirect URI --}}
                    <div class="col-md-8" style="display:none">
                        <input type="hidden" name="redirect_uri" value="{{ $redirect_uri }}">
                    </div>

                </div>

                <div class="mt-4 d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons me-1" style="font-size:18px;">save</span>
                        Save Settings
                    </button>
                    <a href="{{ route('tcn.auth.connect') }}" class="btn btn-outline-success"
                       onclick="return confirm('This will redirect you to the softphone login. Save your Client ID & Secret first.')">
                        <span class="material-icons me-1" style="font-size:18px;">link</span>
                        {{ $connected ? 'Reconnect Softphone' : 'Connect Softphone Account' }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Right: status + info ───────────────────────────────────── --}}
    <div class="col-lg-5">

        {{-- Connection status --}}
        <div class="card p-4 mb-3">
            <h6 class="fw-bold mb-3">
                <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">cable</span>
                OAuth Status
            </h6>
            @if($connected)
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success px-3 py-2" style="font-size:13px;">
                        <span class="material-icons me-1" style="font-size:14px;vertical-align:middle;">check_circle</span>
                        Connected
                    </span>
                    <span class="text-muted small">Refresh token stored securely</span>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    Global token used when no per-agent token is assigned. For production, assign individual tokens to each agent.
                </p>
            @else
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary px-3 py-2" style="font-size:13px;">Not Connected</span>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    Click <strong>Connect Softphone Account</strong> or paste the refresh token manually above.
                </p>
            @endif
        </div>

        {{-- Relay clients --}}
        <div class="card p-4 mb-3" style="border-left:4px solid #6366f1;">
            <h6 class="fw-bold mb-2">
                <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;color:#6366f1;">hub</span>
                Multi-Client Relay
            </h6>
            <p class="text-muted small mb-2">
                Register client domains allowed to use the central relay URL for OAuth.
            </p>
            <a href="{{ route('admin.tcn-relay-clients.index') }}" class="btn btn-sm btn-outline-primary">
                <span class="material-icons me-1" style="font-size:15px;">manage_accounts</span>
                Manage Relay Clients
            </a>
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
                Validate credentials and diagnose connection issues before deploying to agents.
            </p>
            <a href="{{ route('softphone') }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                <span class="material-icons me-1" style="font-size:16px;">open_in_new</span>
                Open Softphone Test Console
            </a>
        </div>

    </div>
</div>

@push('scripts')
<script>
function toggleField(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.textContent = input.type === 'password' ? 'visibility' : 'visibility_off';
}

function copyRelayUrl() {
    const val  = document.getElementById('relayUrlInput').value.trim();
    const icon = document.getElementById('relayUrlCopyIcon');
    navigator.clipboard.writeText(val).then(() => {
        icon.textContent = 'check';
        setTimeout(() => { icon.textContent = 'content_copy'; }, 1800);
    });
}
</script>
@endpush

@endsection
