@extends('layouts.app')

@section('page_title', 'SMS Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <form method="POST" action="{{ route('admin.settings.sms.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="sms_enabled" value="1"
                            {{ \App\Models\Setting::get('sms_enabled', '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">Enable SMS Module</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Provider</label>
                    <select name="sms_provider" class="form-select">
                        @foreach (['msg91', 'textlocal', 'custom'] as $provider)
                            <option value="{{ $provider }}" {{ \App\Models\Setting::get('sms_provider', 'msg91') === $provider ? 'selected' : '' }}>
                                {{ strtoupper($provider) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Sender ID</label><input class="form-control" name="sms_sender_id" value="{{ \App\Models\Setting::get('sms_sender_id') }}"></div>
                <div class="col-md-3"><label class="form-label">API Key</label><input class="form-control" name="sms_api_key" value="{{ \App\Models\Setting::getSecure('sms_api_key') }}"></div>
                <div class="col-md-3"><label class="form-label">API Secret</label><input class="form-control" name="sms_api_secret" value="{{ \App\Models\Setting::getSecure('sms_api_secret') }}"></div>
                <div class="col-md-3">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="sms_notifications_enabled" value="1"
                            {{ \App\Models\Setting::get('sms_notifications_enabled', '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">Enable SMS Notifications</label>
                    </div>
                </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save SMS Settings</button></div>
        </form>
    </div>
@endsection

