@extends('layouts.app')

@section('page_title', 'Zoom Settings')

@section('content')

<div class="row g-4">

    {{-- ── Left: Credentials ────────────────────────────────────────── --}}
    <div class="col-lg-7">
        <div class="card p-4 mb-4">
            <h6 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <span class="material-icons" style="font-size:20px;vertical-align:middle;color:#2D8CFF;">videocam</span>
                Zoom Integration
            </h6>
            <p class="text-muted small mb-4">
                Connect your Zoom Server-to-Server OAuth app to enable automatic Zoom meeting creation for telecallers.
                Credentials are stored encrypted. Create an app at
                <a href="https://marketplace.zoom.us/develop/create" target="_blank" rel="noreferrer">
                    Zoom Marketplace
                </a> and select <strong>Server-to-Server OAuth</strong>.
            </p>

            @if(session('success'))
                <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
                    <span class="material-icons" style="font-size:18px;">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">
                    <span class="material-icons" style="font-size:18px;">error</span>
                    {{ session('error') }}
                </div>
            @endif

            {{-- Status banner --}}
            <div class="alert d-flex align-items-center gap-2 py-2 mb-4
                {{ $configured ? 'alert-success' : 'alert-warning' }}">
                <span class="material-icons" style="font-size:18px;">
                    {{ $configured ? 'check_circle' : 'warning' }}
                </span>
                @if($configured)
                    <span><strong>Configured.</strong> Zoom Server-to-Server credentials are saved.</span>
                    <div class="ms-auto d-flex gap-2">
                        <form method="POST" action="{{ route('admin.settings.zoom.test') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary" style="border-radius:8px;">Test Connection</button>
                        </form>
                        <form method="POST" action="{{ route('admin.settings.zoom.disconnect') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger" style="border-radius:8px;">Remove</button>
                        </form>
                    </div>
                @else
                    <span><strong>Not configured.</strong> Enter your Zoom app credentials below.</span>
                @endif
            </div>

            {{-- Credentials form --}}
            <form method="POST" action="{{ route('admin.settings.zoom.credentials') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">Account ID *</label>
                    <input type="text" name="account_id" class="form-control" style="border-radius:10px;font-size:13px;"
                        value="{{ old('account_id', $account_id) }}"
                        placeholder="xxxxxxxxxxxxxxxxxxxxxxxx" required>
                    @error('account_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">Client ID *</label>
                    <input type="text" name="client_id" class="form-control" style="border-radius:10px;font-size:13px;"
                        value="{{ old('client_id', $client_id) }}"
                        placeholder="xxxxxxxxxxxxxxxxxxxxxxxx" required>
                    @error('client_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">Client Secret *</label>
                    <input type="password" name="client_secret" class="form-control" style="border-radius:10px;font-size:13px;"
                        placeholder="{{ $has_creds ? '(already set — enter to change)' : 'xxxxxxxxxxxxxxxxxxxxxxxx' }}" required>
                    @error('client_secret')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold" style="font-size:13px;">Secret Token <span class="text-muted fw-normal">(optional — for webhook verification)</span></label>
                    <input type="password" name="secret_token" class="form-control" style="border-radius:10px;font-size:13px;"
                        placeholder="{{ $has_creds ? '(already set — enter to change)' : 'xxxxxxxxxxxxxxxxxxxxxxxx' }}">
                </div>

                <button type="submit" class="btn btn-dark" style="border-radius:8px;font-size:13px;">
                    <span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">save</span>
                    Save Credentials
                </button>
            </form>
        </div>
    </div>

    {{-- ── Right: Setup guide ─────────────────────────────────────── --}}
    <div class="col-lg-5">
        <div class="card p-4">
            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <span class="material-icons" style="font-size:18px;color:#6366f1;">help_outline</span>
                Setup Guide
            </h6>
            <ol class="small text-muted ps-3" style="line-height:2;margin:0;">
                <li>Go to <a href="https://marketplace.zoom.us/develop/create" target="_blank">Zoom Marketplace → Build App</a></li>
                <li>Choose <strong>Server-to-Server OAuth</strong></li>
                <li>Name the app (e.g. <em>Insight CRM</em>) and click <strong>Create</strong></li>
                <li>Copy <strong>Account ID</strong>, <strong>Client ID</strong>, <strong>Client Secret</strong> from the App Credentials tab</li>
                <li>Go to <strong>Scopes</strong> tab → add: <code style="font-size:11px;">meeting:write:admin</code> and <code style="font-size:11px;">meeting:write</code></li>
                <li>Activate the app (top-right toggle)</li>
                <li>Paste credentials above and click <strong>Save Credentials</strong></li>
                <li>Click <strong>Test Connection</strong> to verify</li>
            </ol>

            <hr class="my-3">

            <h6 class="fw-bold mb-2" style="font-size:13px;">How telecallers use it</h6>
            <ul class="small text-muted ps-3" style="line-height:2;margin:0;">
                <li><strong>Start Zoom</strong> — instantly creates a Zoom meeting link and opens it</li>
                <li><strong>Schedule Zoom</strong> — pick date/time/duration with optional notes</li>
                <li>WhatsApp notification sent automatically (if 24h session active)</li>
                <li>All meetings visible in Meeting History on every Lead Profile</li>
            </ul>

            <hr class="my-3">

            <h6 class="fw-bold mb-2" style="font-size:13px;">Required Zoom Scopes</h6>
            <div class="d-flex flex-column gap-1">
                <code style="font-size:11.5px;background:#f1f5f9;padding:3px 8px;border-radius:6px;display:inline-block;">meeting:write:admin</code>
                <code style="font-size:11.5px;background:#f1f5f9;padding:3px 8px;border-radius:6px;display:inline-block;">meeting:write</code>
            </div>
        </div>

        @if($configured)
        <div class="card p-4 mt-4" style="border-left:4px solid #2D8CFF;">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="material-icons" style="font-size:18px;color:#2D8CFF;">check_circle</span>
                <span class="fw-bold" style="font-size:13px;color:#2D8CFF;">Zoom is ready</span>
            </div>
            <p class="text-muted small mb-0">Telecallers can now create Zoom meetings directly from any Lead Profile page.</p>
        </div>
        @endif
    </div>

</div>

@endsection
