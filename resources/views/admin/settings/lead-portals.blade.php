@extends('layouts.app')

@section('page_title', 'Lead Portal Credentials')

@section('content')
    @include('admin.settings.partials.nav')

    {{-- Siksha --}}
    <div class="chart-card mb-4">
        <div class="chart-header mb-3">
            <div>
                <h3>Siksha API Credentials</h3>
                <p class="text-muted mb-0" style="font-size:13px;">
                    Configure your Siksha.com integration to receive leads automatically via webhook.
                </p>
            </div>
            @php $sikshaConfigured = !empty($sikshaApiKey); @endphp
            <span class="badge {{ $sikshaConfigured ? 'bg-success' : 'bg-secondary' }}">
                {{ $sikshaConfigured ? 'Configured' : 'Not Configured' }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.settings.lead-portals.update') }}">
            @csrf
            {{-- hidden fields for other portals to avoid wiping them --}}
            <input type="hidden" name="college_dunia_api_key"      value="{{ $cduniaApiKey }}">
            <input type="hidden" name="college_dunia_verify_token" value="{{ $cduniaVerifyToken }}">
            <input type="hidden" name="college_dekho_api_key"      value="{{ $cdekhoApiKey }}">
            <input type="hidden" name="college_dekho_verify_token" value="{{ $cdekhoVerifyToken }}">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">API Key</label>
                    <input type="text" class="form-control font-monospace"
                           name="siksha_api_key"
                           value="{{ $sikshaApiKey }}"
                           placeholder="Your Siksha API key">
                    <small class="text-muted">Provided by Siksha.com in your account dashboard → API Access.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">API Secret</label>
                    <input type="password" class="form-control font-monospace"
                           name="siksha_api_secret"
                           value="{{ $sikshaApiSecret }}"
                           placeholder="Leave blank to keep existing">
                    <small class="text-muted">Secret key used to sign and verify incoming webhook requests.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Webhook Verify Token</label>
                    <input type="text" class="form-control"
                           name="siksha_verify_token"
                           value="{{ $sikshaVerifyToken }}"
                           placeholder="e.g. crm_siksha_token">
                    <small class="text-muted">A secret string you choose. Enter the same value in your Siksha webhook configuration.</small>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0" style="font-size:13px;">
                        <strong>Webhook URL — register this in your Siksha account:</strong><br>
                        <code>{{ url('/webhook/lead-portals/siksha') }}</code>
                        <hr class="my-2">
                        Go to Siksha.com → Account Settings → Lead Distribution → Webhook and paste the URL above along with your Verify Token.
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:16px;">save</span>
                    Save Siksha Settings
                </button>
            </div>
        </form>
    </div>

    {{-- CollegeDunia --}}
    <div class="chart-card mb-4">
        <div class="chart-header mb-3">
            <div>
                <h3>CollegeDunia API Credentials</h3>
                <p class="text-muted mb-0" style="font-size:13px;">
                    Configure your CollegeDunia integration to receive leads automatically via webhook.
                </p>
            </div>
            @php $cduniaConfigured = !empty($cduniaApiKey); @endphp
            <span class="badge {{ $cduniaConfigured ? 'bg-success' : 'bg-secondary' }}">
                {{ $cduniaConfigured ? 'Configured' : 'Not Configured' }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.settings.lead-portals.update') }}">
            @csrf
            {{-- hidden fields for other portals --}}
            <input type="hidden" name="siksha_api_key"      value="{{ $sikshaApiKey }}">
            <input type="hidden" name="siksha_verify_token" value="{{ $sikshaVerifyToken }}">
            <input type="hidden" name="college_dekho_api_key"      value="{{ $cdekhoApiKey }}">
            <input type="hidden" name="college_dekho_verify_token" value="{{ $cdekhoVerifyToken }}">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">API Key</label>
                    <input type="text" class="form-control font-monospace"
                           name="college_dunia_api_key"
                           value="{{ $cduniaApiKey }}"
                           placeholder="Your CollegeDunia API key">
                    <small class="text-muted">Provided by CollegeDunia in your partner dashboard → API Settings.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">API Secret</label>
                    <input type="password" class="form-control font-monospace"
                           name="college_dunia_api_secret"
                           value="{{ $cduniaApiSecret }}"
                           placeholder="Leave blank to keep existing">
                    <small class="text-muted">Secret key used to verify incoming webhook payload signatures.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Webhook Verify Token</label>
                    <input type="text" class="form-control"
                           name="college_dunia_verify_token"
                           value="{{ $cduniaVerifyToken }}"
                           placeholder="e.g. crm_cdunia_token">
                    <small class="text-muted">A secret string you choose. Enter the same value in your CollegeDunia webhook settings.</small>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0" style="font-size:13px;">
                        <strong>Webhook URL — register this in your CollegeDunia partner account:</strong><br>
                        <code>{{ url('/webhook/lead-portals/college-dunia') }}</code>
                        <hr class="my-2">
                        Go to CollegeDunia Partner Portal → Settings → Lead Delivery → Webhook URL and paste the URL above.
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:16px;">save</span>
                    Save CollegeDunia Settings
                </button>
            </div>
        </form>
    </div>

    {{-- CollegeDekho --}}
    <div class="chart-card mb-4">
        <div class="chart-header mb-3">
            <div>
                <h3>CollegeDekho API Credentials</h3>
                <p class="text-muted mb-0" style="font-size:13px;">
                    Configure your CollegeDekho integration to receive leads automatically via webhook.
                </p>
            </div>
            @php $cdekhoConfigured = !empty($cdekhoApiKey); @endphp
            <span class="badge {{ $cdekhoConfigured ? 'bg-success' : 'bg-secondary' }}">
                {{ $cdekhoConfigured ? 'Configured' : 'Not Configured' }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.settings.lead-portals.update') }}">
            @csrf
            {{-- hidden fields for other portals --}}
            <input type="hidden" name="siksha_api_key"           value="{{ $sikshaApiKey }}">
            <input type="hidden" name="siksha_verify_token"      value="{{ $sikshaVerifyToken }}">
            <input type="hidden" name="college_dunia_api_key"    value="{{ $cduniaApiKey }}">
            <input type="hidden" name="college_dunia_verify_token" value="{{ $cduniaVerifyToken }}">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">API Key</label>
                    <input type="text" class="form-control font-monospace"
                           name="college_dekho_api_key"
                           value="{{ $cdekhoApiKey }}"
                           placeholder="Your CollegeDekho API key">
                    <small class="text-muted">Provided by CollegeDekho in your partner dashboard → Integrations → API.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">API Secret</label>
                    <input type="password" class="form-control font-monospace"
                           name="college_dekho_api_secret"
                           value="{{ $cdekhoApiSecret }}"
                           placeholder="Leave blank to keep existing">
                    <small class="text-muted">Secret key used to verify incoming webhook payload signatures.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Webhook Verify Token</label>
                    <input type="text" class="form-control"
                           name="college_dekho_verify_token"
                           value="{{ $cdekhoVerifyToken }}"
                           placeholder="e.g. crm_cdekho_token">
                    <small class="text-muted">A secret string you choose. Enter the same value in your CollegeDekho webhook configuration.</small>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0" style="font-size:13px;">
                        <strong>Webhook URL — register this in your CollegeDekho partner account:</strong><br>
                        <code>{{ url('/webhook/lead-portals/college-dekho') }}</code>
                        <hr class="my-2">
                        Go to CollegeDekho Partner Portal → Lead Management → Webhook Settings and paste the URL above.
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:16px;">save</span>
                    Save CollegeDekho Settings
                </button>
            </div>
        </form>
    </div>
@endsection
