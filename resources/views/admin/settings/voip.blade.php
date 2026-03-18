@extends('layouts.app')

@section('page_title', 'VOIP Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <div class="chart-header mb-4">
            <h3>Exotel Browser VOIP (SIP)</h3>
            <p class="text-muted mb-0">Configure SIP credentials so telecallers can make and receive calls directly in the browser — no mobile phone required.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.voip.update') }}">
            @csrf

            {{-- Enable toggle --}}
            <div class="mb-4 p-3 border rounded-3 d-flex align-items-center justify-content-between" style="background:#f8fafc;">
                <div>
                    <div class="fw-semibold">Enable Browser Calling</div>
                    <div class="text-muted small">When enabled, telecallers use the browser for calls instead of their mobile phone.</div>
                </div>
                <div class="form-check form-switch mb-0 ms-3">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="voip_enabled" id="voip_enabled" style="width:44px; height:22px; cursor:pointer;"
                           {{ \App\Models\Setting::get('voip_enabled', '0') === '1' ? 'checked' : '' }}>
                </div>
            </div>

            {{-- SIP Credentials --}}
            <div class="mb-4 p-4 border rounded-3 bg-light">
                <h5 class="fw-semibold mb-3">
                    <span class="material-icons align-middle me-1" style="font-size:18px;">settings_ethernet</span>
                    SIP / VOIP Credentials
                </h5>

                <div class="alert alert-info d-flex align-items-start gap-2 mb-3" style="font-size:13px;">
                    <span class="material-icons mt-1" style="font-size:16px;">info</span>
                    <div>
                        Obtain these values from your <strong>Exotel dashboard → VOIP / SIP settings</strong>.
                        The domain and proxy are account-specific — contact Exotel support if unsure.
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">VOIP Domain <span class="text-danger">*</span></label>
                        <input class="form-control" name="voip_domain"
                               value="{{ \App\Models\Setting::get('voip_domain', '') }}"
                               placeholder="e.g. insighthcm5m.voip.exotel.com">
                        <div class="form-text">SIP registrar domain for your Exotel account.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">VOIP Proxy (WebSocket) <span class="text-danger">*</span></label>
                        <input class="form-control" name="voip_proxy"
                               value="{{ \App\Models\Setting::get('voip_proxy', '') }}"
                               placeholder="e.g. voip.in1.exotel.com">
                        <div class="form-text">WebSocket proxy server — used by JsSIP to register. Connection: <code>wss://&lt;proxy&gt;</code></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">SIP Username <span class="text-danger">*</span></label>
                        <input class="form-control" name="voip_username"
                               value="{{ \App\Models\Setting::get('voip_username', '') }}"
                               placeholder="e.g. agent1">
                        <div class="form-text">SIP user identity provided by Exotel.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">SIP Password <span class="text-danger">*</span></label>
                        <input class="form-control" type="password" name="voip_password"
                               placeholder="Leave blank to keep existing password"
                               autocomplete="new-password">
                        <div class="form-text">Leave blank to keep the saved password unchanged.</div>
                    </div>
                </div>
            </div>

            {{-- Connection Preview --}}
            <div class="mb-4 p-3 border rounded-3" style="background:#fff;">
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

            {{-- Browser Requirements --}}
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" style="font-size:13px;">
                <span class="material-icons mt-1" style="font-size:16px;">warning</span>
                <div>
                    <strong>Browser requirements:</strong> HTTPS must be enabled, telecaller browser must grant microphone permission, and WebRTC must be supported (Chrome / Firefox / Edge).
                </div>
            </div>

            <button class="btn btn-primary">
                <span class="material-icons align-middle me-1" style="font-size:17px;">save</span>
                Save VOIP Settings
            </button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
// Live-update the preview as user types
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
</script>
@endpush
