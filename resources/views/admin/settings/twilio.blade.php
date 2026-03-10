@extends('layouts.app')

@section('page_title', 'Twilio Voice Credentials')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <div class="chart-header mb-3">
            <h3>Twilio Voice Credentials</h3>
            <p>Used for outbound/inbound voice calls. WhatsApp is handled separately via the Meta Cloud API.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.twilio.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Account SID</label><input class="form-control" name="twilio_account_sid" value="{{ \App\Models\Setting::getSecure('twilio_account_sid', env('TWILIO_ACCOUNT_SID')) }}" required></div>
                <div class="col-md-4"><label class="form-label">Auth Token</label><input class="form-control" name="twilio_auth_token" value="{{ \App\Models\Setting::getSecure('twilio_auth_token', env('TWILIO_AUTH_TOKEN')) }}" required></div>
                <div class="col-md-4"><label class="form-label">API Key</label><input class="form-control" name="twilio_api_key" value="{{ \App\Models\Setting::getSecure('twilio_api_key', env('TWILIO_API_KEY')) }}"></div>
                <div class="col-md-4"><label class="form-label">API Secret</label><input class="form-control" name="twilio_api_secret" value="{{ \App\Models\Setting::getSecure('twilio_api_secret', env('TWILIO_API_SECRET')) }}"></div>
                <div class="col-md-4"><label class="form-label">App SID (TwiML App)</label><input class="form-control" name="twilio_app_sid" value="{{ \App\Models\Setting::get('twilio_app_sid', env('TWILIO_APP_SID')) }}"></div>
                <div class="col-md-4"><label class="form-label">Voice From Number</label><input class="form-control" name="twilio_from_number" value="{{ \App\Models\Setting::get('twilio_from_number', env('TWILIO_FROM')) }}" placeholder="+91XXXXXXXXXX"></div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">Validate &amp; Save Twilio Credentials</button>
            </div>
        </form>
    </div>
@endsection
