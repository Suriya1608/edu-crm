@extends('layouts.app')

@section('page_title', 'Real-Time Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card mb-3">
        <form method="POST" action="{{ route('admin.settings.realtime.update') }}">
            @csrf

            {{-- ── Driver Selection ──────────────────────────────────────── --}}
            <h6 class="fw-semibold mb-3" style="color:#0f172a;">Select Real-Time Driver</h6>
            <div class="row g-3 mb-4">

                {{-- Disabled --}}
                <div class="col-md-4">
                    <label class="d-block h-100">
                        <input type="radio" name="broadcast_driver" value="null" class="d-none driver-radio"
                               {{ $driver === 'null' ? 'checked' : '' }}>
                        <div class="driver-card border rounded-3 p-3 h-100" style="cursor:pointer;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="material-icons" style="color:#64748b;font-size:22px;">signal_wifi_off</span>
                                <span class="fw-semibold">Disabled</span>
                                <span class="badge bg-secondary ms-auto">Default</span>
                            </div>
                            <p class="text-muted mb-0" style="font-size:12px;">
                                Falls back to HTTP polling (7–30 s latency). Works on all hosting. No configuration needed.
                            </p>
                        </div>
                    </label>
                </div>

                {{-- Pusher --}}
                <div class="col-md-4">
                    <label class="d-block h-100">
                        <input type="radio" name="broadcast_driver" value="pusher" class="d-none driver-radio"
                               {{ $driver === 'pusher' ? 'checked' : '' }}>
                        <div class="driver-card border rounded-3 p-3 h-100" style="cursor:pointer;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="material-icons" style="color:#6366f1;font-size:22px;">cloud</span>
                                <span class="fw-semibold">Pusher</span>
                                <span class="badge bg-success ms-auto">Shared Hosting ✓</span>
                            </div>
                            <p class="text-muted mb-0" style="font-size:12px;">
                                External WebSocket service. Works on any hosting — shared or VPS. Free tier: 200k msg/day, 100 connections.
                            </p>
                        </div>
                    </label>
                </div>

                {{-- Reverb --}}
                <div class="col-md-4">
                    <label class="d-block h-100">
                        <input type="radio" name="broadcast_driver" value="reverb" class="d-none driver-radio"
                               {{ $driver === 'reverb' ? 'checked' : '' }}>
                        <div class="driver-card border rounded-3 p-3 h-100" style="cursor:pointer;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="material-icons" style="color:#10b981;font-size:22px;">dns</span>
                                <span class="fw-semibold">Reverb</span>
                                <span class="badge bg-warning text-dark ms-auto">VPS / Dedicated</span>
                            </div>
                            <p class="text-muted mb-0" style="font-size:12px;">
                                Self-hosted WebSocket server. Free, no external dependency. Requires a persistent process on the server.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- ── Pusher Credentials ────────────────────────────────────── --}}
            <div id="pusherFields" style="display:none;">
                <hr class="my-3">
                <h6 class="fw-semibold mb-3" style="color:#0f172a;">
                    <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;color:#6366f1;">vpn_key</span>
                    Pusher Credentials
                </h6>
                <div class="alert alert-info py-2 mb-3" style="font-size:13px;">
                    Get your credentials at <a href="https://dashboard.pusher.com" target="_blank" rel="noreferrer">dashboard.pusher.com</a>
                    → Create App → App Keys.
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">App ID</label>
                        <input type="text" name="pusher_app_id" class="form-control"
                               placeholder="1234567" value="{{ $pusherAppId }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">App Key</label>
                        <input type="text" name="pusher_app_key" class="form-control"
                               placeholder="abcdef1234567890" value="{{ $pusherKey }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">App Secret</label>
                        <input type="password" name="pusher_app_secret" class="form-control"
                               placeholder="Leave blank to keep existing" value="{{ $pusherSecret }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cluster</label>
                        <select name="pusher_app_cluster" class="form-select">
                            @foreach (['mt1','us2','us3','eu','ap1','ap2','ap3','ap4','sa1'] as $cl)
                                <option value="{{ $cl }}" {{ $pusherCluster === $cl ? 'selected' : '' }}>
                                    {{ strtoupper($cl) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- ── Reverb Credentials ────────────────────────────────────── --}}
            <div id="reverbFields" style="display:none;">
                <hr class="my-3">
                <h6 class="fw-semibold mb-3" style="color:#0f172a;">
                    <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;color:#10b981;">settings_input_antenna</span>
                    Reverb Server Configuration
                </h6>
                <div class="alert alert-warning py-2 mb-3" style="font-size:13px;">
                    <strong>Requirement:</strong> Run <code>php artisan reverb:start</code> as a persistent process on your server
                    (e.g. via Supervisor). Shared hosting does <strong>not</strong> support this.
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">App ID</label>
                        <input type="text" name="reverb_app_id" class="form-control"
                               placeholder="my-app" value="{{ $reverbAppId }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">App Key</label>
                        <input type="text" name="reverb_app_key" class="form-control"
                               placeholder="app-key" value="{{ $reverbKey }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">App Secret</label>
                        <input type="password" name="reverb_app_secret" class="form-control"
                               placeholder="Leave blank to keep existing" value="{{ $reverbSecret }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Host</label>
                        <input type="text" name="reverb_host" class="form-control"
                               placeholder="127.0.0.1 or yourdomain.com" value="{{ $reverbHost }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Port</label>
                        <input type="number" name="reverb_port" class="form-control"
                               placeholder="8080" value="{{ $reverbPort }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Scheme</label>
                        <select name="reverb_scheme" class="form-select">
                            <option value="http"  {{ $reverbScheme === 'http'  ? 'selected' : '' }}>HTTP (ws://)</option>
                            <option value="https" {{ $reverbScheme === 'https' ? 'selected' : '' }}>HTTPS (wss://)</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-secondary mt-3 py-2" style="font-size:12px;">
                    <strong>Generate credentials with:</strong><br>
                    <code>php artisan reverb:install</code> — then copy the generated
                    <code>REVERB_APP_ID</code>, <code>REVERB_APP_KEY</code>, and <code>REVERB_APP_SECRET</code>
                    from your <code>.env</code> file into the fields above.
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary">Save Real-Time Settings</button>
            </div>
        </form>
    </div>

    {{-- ── Current Status Card ──────────────────────────────────────────── --}}
    <div class="chart-card">
        <h6 class="fw-semibold mb-3">Current Status</h6>
        <div class="d-flex align-items-center gap-3">
            @if($driver === 'null')
                <span class="badge bg-secondary fs-6">Disabled — polling only</span>
                <span class="text-muted" style="font-size:13px;">WhatsApp messages refresh every 7–30 s</span>
            @elseif($driver === 'pusher')
                <span class="badge bg-success fs-6">Pusher — real-time active</span>
                <span class="text-muted" style="font-size:13px;">Cluster: {{ strtoupper($pusherCluster) }}</span>
            @elseif($driver === 'reverb')
                <span class="badge bg-info text-dark fs-6">Reverb — self-hosted</span>
                <span class="text-muted" style="font-size:13px;">{{ $reverbScheme }}://{{ $reverbHost }}:{{ $reverbPort }}</span>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    // Highlight selected driver card and show/hide credential sections
    function syncDriver() {
        const selected = document.querySelector('.driver-radio:checked')?.value || 'null';

        document.querySelectorAll('.driver-card').forEach(function (card) {
            const radio = card.closest('label').querySelector('.driver-radio');
            const active = radio && radio.value === selected;
            card.style.borderColor    = active ? '#6366f1' : '';
            card.style.background     = active ? '#f5f3ff' : '';
            card.style.boxShadow      = active ? '0 0 0 2px #6366f120' : '';
        });

        document.getElementById('pusherFields').style.display = selected === 'pusher' ? '' : 'none';
        document.getElementById('reverbFields').style.display = selected === 'reverb' ? '' : 'none';
    }

    document.querySelectorAll('.driver-radio').forEach(function (r) {
        r.addEventListener('change', syncDriver);
    });
    document.querySelectorAll('.driver-card').forEach(function (card) {
        card.addEventListener('click', function () {
            const radio = card.closest('label').querySelector('.driver-radio');
            if (radio) { radio.checked = true; syncDriver(); }
        });
    });

    syncDriver();
})();
</script>
@endpush
