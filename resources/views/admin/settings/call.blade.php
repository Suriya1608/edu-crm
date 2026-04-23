@extends('layouts.app')

@section('page_title', 'Call Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <div class="chart-header mb-4">
            <h3>Call Settings</h3>
            <p class="text-muted mb-0">Configure your TCN telephony credentials.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.call.update') }}">
            @csrf

            {{-- TCN Configuration --}}
            <div class="mb-4 p-4 border rounded-3 bg-light">
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
