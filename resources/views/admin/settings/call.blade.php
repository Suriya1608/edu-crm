@extends('layouts.app')

@section('page_title', 'Call Settings')

@section('content')
    @include('admin.settings.partials.nav')

    @php
        $currentProvider = \App\Models\Setting::get('primary_call_provider', 'twilio');
        $voipEnabled     = \App\Models\Setting::get('voip_enabled', '0') === '1';
    @endphp

    <div class="chart-card">
        <div class="chart-header mb-4">
            <h3>Call Settings</h3>
            <p class="text-muted mb-0">Configure your telephony provider, credentials, and browser VOIP — all in one place.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.call.update') }}">
            @csrf

            {{-- ── A. Provider Selection ──────────────────────────────────────── --}}
            <div class="mb-4">
                <label class="form-label fw-semibold">Primary Call Provider</label>
                <div class="d-flex gap-3 flex-wrap">
                    <div class="form-check border rounded-3 px-4 py-3 flex-fill provider-card"
                         id="card-twilio" onclick="selectProvider('twilio')" style="cursor:pointer; min-width:200px;">
                        <input class="form-check-input" type="radio" name="primary_call_provider"
                               id="provider_twilio" value="twilio"
                               {{ $currentProvider === 'twilio' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold ms-1" for="provider_twilio" style="cursor:pointer;">
                            <span class="material-icons align-middle me-1" style="font-size:18px;color:#137fec;">call</span>
                            Twilio
                        </label>
                        <div class="text-muted small mt-1">Browser-based WebRTC calling via Twilio Device SDK.</div>
                    </div>

                    <div class="form-check border rounded-3 px-4 py-3 flex-fill provider-card"
                         id="card-exotel" onclick="selectProvider('exotel')" style="cursor:pointer; min-width:200px;">
                        <input class="form-check-input" type="radio" name="primary_call_provider"
                               id="provider_exotel" value="exotel"
                               {{ $currentProvider === 'exotel' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold ms-1" for="provider_exotel" style="cursor:pointer;">
                            <span class="material-icons align-middle me-1" style="font-size:18px;color:#137fec;">phone_in_talk</span>
                            Exotel
                        </label>
                        <div class="text-muted small mt-1">Server-side calling — Exotel dials telecaller's mobile, then connects to lead.</div>
                    </div>

                    <div class="form-check border rounded-3 px-4 py-3 flex-fill provider-card"
                         id="card-tcn" onclick="selectProvider('tcn')" style="cursor:pointer; min-width:200px;">
                        <input class="form-check-input" type="radio" name="primary_call_provider"
                               id="provider_tcn" value="tcn"
                               {{ $currentProvider === 'tcn' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold ms-1" for="provider_tcn" style="cursor:pointer;">
                            <span class="material-icons align-middle me-1" style="font-size:18px;color:#137fec;">headset_mic</span>
                            TCN
                        </label>
                        <div class="text-muted small mt-1">Browser WebRTC softphone — SIP.js + TCN cloud contact center.</div>
                    </div>
                </div>
            </div>

            {{-- ── B. Twilio Configuration ────────────────────────────────────── --}}
            <div id="section-twilio" class="mb-4 p-4 border rounded-3 bg-light"
                 style="display:{{ $currentProvider === 'twilio' ? 'block' : 'none' }};">
                <h5 class="fw-semibold mb-3">
                    <span class="material-icons align-middle me-1" style="font-size:18px;">settings</span>
                    Twilio Configuration
                </h5>
                <div class="alert alert-info d-flex align-items-start gap-2 mb-3" style="font-size:13px;">
                    <span class="material-icons mt-1" style="font-size:16px;">info</span>
                    <div>
                        Browser-based WebRTC calling — telecallers make calls directly in the browser.
                        Obtain credentials from your <strong>Twilio Console → Voice → TwiML Apps</strong>.
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Account SID <span class="text-danger">*</span></label>
                        <input class="form-control" name="twilio_account_sid"
                               value="{{ \App\Models\Setting::getSecure('twilio_account_sid', env('TWILIO_ACCOUNT_SID')) }}"
                               placeholder="ACxxxxxxxxxxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Auth Token <span class="text-danger">*</span></label>
                        <input class="form-control" type="password" name="twilio_auth_token"
                               value="{{ \App\Models\Setting::getSecure('twilio_auth_token', env('TWILIO_AUTH_TOKEN')) }}"
                               placeholder="Leave blank to keep existing">
                        <div class="form-text">Leave blank to keep the saved token unchanged.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Key</label>
                        <input class="form-control" name="twilio_api_key"
                               value="{{ \App\Models\Setting::getSecure('twilio_api_key', env('TWILIO_API_KEY')) }}"
                               placeholder="SKxxxxxxxxxxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Secret</label>
                        <input class="form-control" type="password" name="twilio_api_secret"
                               value="{{ \App\Models\Setting::getSecure('twilio_api_secret', env('TWILIO_API_SECRET')) }}"
                               placeholder="Leave blank to keep existing">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">TwiML App SID</label>
                        <input class="form-control" name="twilio_app_sid"
                               value="{{ \App\Models\Setting::get('twilio_app_sid', env('TWILIO_APP_SID')) }}"
                               placeholder="APxxxxxxxxxxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Voice From Number</label>
                        <input class="form-control" name="twilio_from_number"
                               value="{{ \App\Models\Setting::get('twilio_from_number', env('TWILIO_FROM')) }}"
                               placeholder="+91XXXXXXXXXX">
                    </div>
                </div>
            </div>

            {{-- ── C. Exotel Configuration ────────────────────────────────────── --}}
            <div id="section-exotel" class="mb-4"
                 style="display:{{ $currentProvider === 'exotel' ? 'block' : 'none' }};">

                {{-- C1. Exotel API Credentials --}}
                <div class="p-4 border rounded-3 bg-light mb-3">
                    <h5 class="fw-semibold mb-3">
                        <span class="material-icons align-middle me-1" style="font-size:18px;">settings</span>
                        Exotel API Credentials
                    </h5>
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" style="font-size:13px;">
                        <span class="material-icons mt-1" style="font-size:16px;">warning</span>
                        <div>
                            Exotel calls the telecaller's <strong>mobile phone</strong> first, then bridges to the lead.
                            Each telecaller must have a phone number set in their profile.
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">API Key <span class="text-danger">*</span></label>
                            <input class="form-control" name="exotel_api_key"
                                   value="{{ \App\Models\Setting::getSecure('exotel_api_key', env('EXOTEL_API_KEY')) }}"
                                   placeholder="e.g. exotelKey123">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API Token <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" name="exotel_api_token"
                                   value="{{ \App\Models\Setting::getSecure('exotel_api_token', env('EXOTEL_TOKEN')) }}"
                                   placeholder="Leave blank to keep existing">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account SID <span class="text-danger">*</span></label>
                            <input class="form-control" name="exotel_sid"
                                   value="{{ \App\Models\Setting::get('exotel_sid', env('EXOTEL_SID')) }}"
                                   placeholder="e.g. insighthcm1m">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Caller ID (Exotel Number) <span class="text-danger">*</span></label>
                            <input class="form-control" name="exotel_caller_id"
                                   value="{{ \App\Models\Setting::get('exotel_caller_id', env('EXOTEL_FROM')) }}"
                                   placeholder="e.g. 09513886363">
                            <div class="form-text">Number displayed to the lead when they receive the call.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">API Subdomain</label>
                            <input class="form-control" name="exotel_subdomain"
                                   value="{{ \App\Models\Setting::get('exotel_subdomain', env('EXOTEL_SUBDOMAIN', 'api.in.exotel.com')) }}"
                                   placeholder="api.in.exotel.com">
                            <div class="form-text">Use <code>api.in.exotel.com</code> for India accounts.</div>
                        </div>
                    </div>
                    <div class="mt-3 p-3 border rounded-2" style="background:#fff;">
                        <div class="fw-semibold small mb-1">
                            <span class="material-icons align-middle" style="font-size:15px;">webhook</span>
                            Status Webhook URL
                        </div>
                        <code class="small text-break">{{ route('exotel.webhook') }}</code>
                        <div class="text-muted small mt-1">Set this in your Exotel dashboard under App → Passthru App → Status Callback.</div>
                    </div>
                </div>

                {{-- C2. Browser VOIP --}}
                <div class="p-4 border rounded-3 bg-light">
                    <h5 class="fw-semibold mb-3">
                        <span class="material-icons align-middle me-1" style="font-size:18px;">settings_ethernet</span>
                        Browser VOIP (Exotel SIP / WebRTC)
                    </h5>
                    <p class="text-muted small mb-3">
                        When enabled, telecallers make and receive calls <strong>directly in the browser</strong> via SIP/WebRTC — no mobile phone required.
                        Obtain SIP credentials from your <strong>Exotel dashboard → VOIP / SIP settings</strong>.
                    </p>

                    {{-- Enable toggle --}}
                    <div class="mb-4 p-3 border rounded-3 d-flex align-items-center justify-content-between" style="background:#fff;">
                        <div>
                            <div class="fw-semibold">Enable Browser Calling</div>
                            <div class="text-muted small">When enabled, telecallers use the browser instead of their mobile phone.</div>
                        </div>
                        <div class="form-check form-switch mb-0 ms-3">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="voip_enabled" id="voip_enabled"
                                   style="width:44px; height:22px; cursor:pointer;"
                                   {{ $voipEnabled ? 'checked' : '' }}
                                   onchange="toggleVoipFields()">
                        </div>
                    </div>

                    {{-- SIP Fields --}}
                    <div id="voip-fields" style="display:{{ $voipEnabled ? 'block' : 'none' }};">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">VOIP Domain <span class="text-danger">*</span></label>
                                <input class="form-control" name="voip_domain" id="voip_domain"
                                       value="{{ \App\Models\Setting::get('voip_domain', '') }}"
                                       placeholder="e.g. insighthcm5m.voip.exotel.com">
                                <div class="form-text">SIP registrar domain for your Exotel account.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">VOIP Proxy (WebSocket) <span class="text-danger">*</span></label>
                                <input class="form-control" name="voip_proxy" id="voip_proxy"
                                       value="{{ \App\Models\Setting::get('voip_proxy', '') }}"
                                       placeholder="e.g. voip.in1.exotel.com">
                                <div class="form-text">WebSocket proxy — connection: <code>wss://&lt;proxy&gt;</code></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SIP Username <span class="text-danger">*</span></label>
                                <input class="form-control" name="voip_username" id="voip_username"
                                       value="{{ \App\Models\Setting::get('voip_username', '') }}"
                                       placeholder="e.g. agent1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SIP Password <span class="text-danger">*</span></label>
                                <input class="form-control" type="password" name="voip_password"
                                       placeholder="Leave blank to keep existing password"
                                       autocomplete="new-password">
                                <div class="form-text">Leave blank to keep the saved password unchanged.</div>
                            </div>
                        </div>

                        {{-- JsSIP Preview --}}
                        <div class="mt-4 p-3 border rounded-3" style="background:#fff;">
                            <div class="fw-semibold small mb-2">
                                <span class="material-icons align-middle" style="font-size:15px;">preview</span>
                                JsSIP Configuration Preview
                            </div>
                            <pre class="small mb-0" style="background:#f1f5f9; padding:10px; border-radius:6px; overflow-x:auto;">const socket = new JsSIP.WebSocketInterface('wss://<span id="prev-proxy">{{ \App\Models\Setting::get('voip_proxy', 'voip.in1.exotel.com') }}</span>');
const ua = new JsSIP.UA({
  sockets  : [socket],
  uri      : 'sip:<span id="prev-user">{{ \App\Models\Setting::get('voip_username', 'agent1') }}</span>@<span id="prev-domain">{{ \App\Models\Setting::get('voip_domain', 'youraccid.voip.exotel.com') }}</span>',
  password : '••••••••',
});</pre>
                        </div>

                        <div class="alert alert-warning d-flex align-items-start gap-2 mt-3 mb-0" style="font-size:13px;">
                            <span class="material-icons mt-1" style="font-size:16px;">warning</span>
                            <div>
                                <strong>Browser requirements:</strong> HTTPS must be enabled, telecaller browser must grant microphone permission, and WebRTC must be supported (Chrome / Firefox / Edge).
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── D. TCN Configuration ────────────────────────────────────────── --}}
            <div id="section-tcn" class="mb-4 p-4 border rounded-3 bg-light"
                 style="display:{{ $currentProvider === 'tcn' ? 'block' : 'none' }};">
                <h5 class="fw-semibold mb-3">
                    <span class="material-icons align-middle me-1" style="font-size:18px;">headset_mic</span>
                    TCN Softphone Configuration
                </h5>
                <div class="alert alert-info d-flex align-items-start gap-2 mb-3" style="font-size:13px;">
                    <span class="material-icons mt-1" style="font-size:16px;">info</span>
                    <div>
                        TCN is a browser-based WebRTC softphone. Telecallers log in and make calls directly from the browser using SIP.js.
                        Credentials are provided by TCN — <strong>client_secret is never sent to the browser</strong>.
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Client ID <span class="text-danger">*</span></label>
                        <input class="form-control" name="tcn_client_id"
                               value="{{ \App\Models\Setting::getSecure('tcn_client_id', env('TCN_CLIENT_ID')) }}"
                               placeholder="e.g. n37f65fou66o37ul">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client Secret <span class="text-danger">*</span></label>
                        <input class="form-control" type="password" name="tcn_client_secret"
                               placeholder="Leave blank to keep existing">
                        <div class="form-text">Never exposed to the browser — used only server-side to generate tokens.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Refresh Token <span class="text-danger">*</span></label>
                        <input class="form-control" type="password" name="tcn_refresh_token"
                               placeholder="Leave blank to keep existing">
                        <div class="form-text">Long-lived token from TCN — used to generate short-lived access tokens.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Redirect URI</label>
                        <input class="form-control" name="tcn_redirect_uri"
                               value="{{ \App\Models\Setting::get('tcn_redirect_uri', env('TCN_REDIRECT_URI')) }}"
                               placeholder="https://yourdomain.com/tcn/auth/callback">
                        <div class="form-text">Must match the URI registered with TCN when your client credentials were created.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Outbound Caller ID</label>
                        <input class="form-control" name="tcn_caller_id"
                               value="{{ \App\Models\Setting::get('tcn_caller_id', env('TCN_CALLER_ID', '')) }}"
                               placeholder="e.g. 8634134466">
                        <div class="form-text">10-digit number shown to recipients. Used when TCN huntgroup settings API is unavailable.</div>
                    </div>
                </div>

                <div class="mt-3 p-3 border rounded-2" style="background:#fff;">
                    <div class="fw-semibold small mb-1">
                        <span class="material-icons align-middle" style="font-size:15px;">webhook</span>
                        Registered Redirect URI
                    </div>
                    <code class="small text-break">{{ route('tcn.auth.callback') }}</code>
                    <div class="text-muted small mt-1">This is the URL you provided to TCN when generating your client credentials.</div>
                </div>

                {{-- Connect TCN Account --}}
                @php
                    $tcnConnected = !empty(\App\Models\Setting::getSecure('tcn_refresh_token', env('TCN_REFRESH_TOKEN')));
                @endphp
                <div class="mt-3 p-3 border rounded-2 d-flex align-items-center justify-content-between" style="background:#fff;">
                    <div>
                        <div class="fw-semibold small mb-1">
                            <span class="material-icons align-middle" style="font-size:15px;">{{ $tcnConnected ? 'check_circle' : 'link' }}</span>
                            TCN Account
                        </div>
                        <div class="text-muted small">
                            @if($tcnConnected)
                                <span class="text-success fw-semibold">Connected</span> — refresh token stored. You can reconnect below to refresh it.
                            @else
                                <span class="text-danger fw-semibold">Not connected.</span> Save your Client ID &amp; Secret first, then click Connect.
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('tcn.auth.connect') }}"
                       class="btn btn-sm {{ $tcnConnected ? 'btn-outline-success' : 'btn-primary' }}"
                       onclick="return confirm('This will redirect you to TCN login. Make sure you have saved your Client ID and Secret first.')">
                        <span class="material-icons align-middle me-1" style="font-size:16px;">{{ $tcnConnected ? 'refresh' : 'login' }}</span>
                        {{ $tcnConnected ? 'Reconnect TCN' : 'Connect TCN Account' }}
                    </a>
                </div>

                <div class="alert alert-warning d-flex align-items-start gap-2 mt-3 mb-0" style="font-size:13px;">
                    <span class="material-icons mt-1" style="font-size:16px;">warning</span>
                    <div>
                        <strong>Requirements:</strong> HTTPS is mandatory for WebRTC (use ngrok locally).
                        Telecallers must grant microphone permission when prompted by the browser.
                        Supported browsers: Chrome, Firefox, Edge.
                    </div>
                </div>
            </div>

            <button class="btn btn-primary">
                <span class="material-icons align-middle me-1" style="font-size:17px;">save</span>
                Save Call Settings
            </button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
function selectProvider(provider) {
    document.getElementById('provider_' + provider).checked = true;
    document.getElementById('section-twilio').style.display = provider === 'twilio' ? 'block' : 'none';
    document.getElementById('section-exotel').style.display = provider === 'exotel' ? 'block' : 'none';
    document.getElementById('section-tcn').style.display    = provider === 'tcn'    ? 'block' : 'none';

    ['twilio', 'exotel', 'tcn'].forEach(function (p) {
        var card = document.getElementById('card-' + p);
        if (p === provider) {
            card.style.borderColor = '#137fec';
            card.style.background  = '#eff6ff';
        } else {
            card.style.borderColor = '';
            card.style.background  = '';
        }
    });
}

function toggleVoipFields() {
    var enabled = document.getElementById('voip_enabled').checked;
    document.getElementById('voip-fields').style.display = enabled ? 'block' : 'none';
}

// Live-update the JsSIP preview
(function () {
    var map = {
        'voip_proxy'   : 'prev-proxy',
        'voip_username': 'prev-user',
        'voip_domain'  : 'prev-domain',
    };
    Object.keys(map).forEach(function (name) {
        var input = document.querySelector('[name="' + name + '"]');
        var span  = document.getElementById(map[name]);
        if (!input || !span) return;
        input.addEventListener('input', function () {
            span.textContent = input.value || input.placeholder.replace('e.g. ', '');
        });
    });
})();

// Init on page load
(function () {
    var checked = document.querySelector('input[name="primary_call_provider"]:checked');
    if (checked) selectProvider(checked.value);
    document.querySelectorAll('input[name="primary_call_provider"]').forEach(function (r) {
        r.addEventListener('change', function () { selectProvider(this.value); });
    });
})();
</script>
@endpush
