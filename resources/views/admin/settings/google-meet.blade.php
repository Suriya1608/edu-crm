@extends('layouts.app')

@section('page_title', 'Google Meet Settings')

@section('content')

<div class="row g-4">

    {{-- ── Left: Credentials + Connect ──────────────────────────────── --}}
    <div class="col-lg-7">
        <div class="card p-4 mb-4">
            <h6 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <span class="material-icons" style="font-size:20px;vertical-align:middle;color:#6366f1;">videocam</span>
                Google Meet Integration
            </h6>
            <p class="text-muted small mb-4">
                Connect a Google account to enable automatic Meet link generation for telecallers.
                Credentials are stored encrypted. Create a project at
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noreferrer">
                    Google Cloud Console
                </a> and enable the <strong>Google Calendar API</strong>.
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
                {{ $connected ? 'alert-success' : 'alert-warning' }}">
                <span class="material-icons" style="font-size:18px;">
                    {{ $connected ? 'check_circle' : 'warning' }}
                </span>
                @if($connected)
                    <span><strong>Connected.</strong> Google account authorised — Meet links will be generated automatically.</span>
                    <form method="POST" action="{{ route('admin.settings.google-meet.disconnect') }}" class="ms-auto">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger" style="border-radius:8px;">Disconnect</button>
                    </form>
                @else
                    <span><strong>Not connected.</strong> Save credentials below, then click <em>Connect Google Account</em>.</span>
                @endif
            </div>

            {{-- Credentials form --}}
            <form method="POST" action="{{ route('admin.settings.google-meet.credentials') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">OAuth Client ID *</label>
                    <input type="text" name="client_id" class="form-control" style="border-radius:10px;font-size:13px;"
                        value="{{ old('client_id', $client_id) }}"
                        placeholder="xxxxxxxxxx.apps.googleusercontent.com" required>
                    @error('client_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold" style="font-size:13px;">OAuth Client Secret *</label>
                    <input type="password" name="client_secret" class="form-control" style="border-radius:10px;font-size:13px;"
                        placeholder="{{ $has_client ? '(already set — enter to change)' : 'GOCSPX-xxxxxxxxxxxxxxx' }}" required>
                    @error('client_secret')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-dark" style="border-radius:8px;font-size:13px;">
                    <span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">save</span>
                    Save Credentials
                </button>
            </form>
        </div>

        {{-- Connect button --}}
        @if($has_client && !$connected)
            <div class="card p-4">
                <h6 class="fw-bold mb-1">Step 2: Authorise Google Account</h6>
                <p class="text-muted small mb-3">
                    Click below to open Google's authorisation page. You must grant
                    <strong>Calendar Events</strong> permission. The redirect URI to register in
                    Google Console is:<br>
                    <code style="font-size:11.5px;">{{ route('admin.google.callback') }}</code>
                </p>
                <a href="{{ route('admin.google.redirect') }}"
                   class="btn btn-primary" style="border-radius:8px;">
                    <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;">login</span>
                    Connect Google Account
                </a>
            </div>
        @endif
    </div>

    {{-- ── Right: Setup guide ─────────────────────────────────────── --}}
    <div class="col-lg-5">
        <div class="card p-4">
            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <span class="material-icons" style="font-size:18px;color:#6366f1;">help_outline</span>
                Setup Guide
            </h6>
            <ol class="small text-muted ps-3" style="line-height:2;margin:0;">
                <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                <li>Create a new project (e.g. <em>Insight CRM</em>)</li>
                <li>Enable <strong>Google Calendar API</strong></li>
                <li>Go to <strong>Credentials → Create Credentials → OAuth 2.0 Client ID</strong></li>
                <li>Application type: <strong>Web application</strong></li>
                <li>Add Authorised redirect URI:<br>
                    <code style="font-size:11px;background:#f1f5f9;padding:2px 6px;border-radius:4px;">{{ route('admin.google.callback') }}</code>
                </li>
                <li>Copy <strong>Client ID</strong> and <strong>Client Secret</strong>, paste above</li>
                <li>Click <strong>Save Credentials</strong>, then <strong>Connect Google Account</strong></li>
                <li>Approve the Calendar permission on Google's screen</li>
            </ol>

            <hr class="my-3">

            <h6 class="fw-bold mb-2" style="font-size:13px;">How telecallers use it</h6>
            <ul class="small text-muted ps-3" style="line-height:2;margin:0;">
                <li><strong>Start Meet</strong> — instantly creates a link, opens it, and sends it via WhatsApp</li>
                <li><strong>Schedule Meet</strong> — pick date/time/duration, optionally send WhatsApp reminder</li>
                <li>Meeting history is visible on every Lead Profile page</li>
            </ul>
        </div>
    </div>

</div>

@endsection
