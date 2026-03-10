@extends('layouts.app')

@section('page_title', 'SMTP Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card mb-3">
        <form method="POST" action="{{ route('admin.settings.smtp.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Mailer</label>
                    <select name="smtp_mailer" class="form-select">
                        @foreach (['smtp', 'log', 'array'] as $mailer)
                            <option value="{{ $mailer }}" {{ \App\Models\Setting::get('smtp_mailer', 'smtp') === $mailer ? 'selected' : '' }}>{{ strtoupper($mailer) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Host</label><input name="smtp_host" class="form-control" value="{{ \App\Models\Setting::get('smtp_host', env('MAIL_HOST')) }}"></div>
                <div class="col-md-2"><label class="form-label">Port</label><input name="smtp_port" type="number" class="form-control" value="{{ \App\Models\Setting::get('smtp_port', env('MAIL_PORT', 587)) }}"></div>
                <div class="col-md-2">
                    <label class="form-label">Encryption</label>
                    <select name="smtp_encryption" class="form-select">
                        <option value="">None</option>
                        <option value="tls" {{ \App\Models\Setting::get('smtp_encryption') === 'tls' ? 'selected' : '' }}>TLS</option>
                        <option value="ssl" {{ \App\Models\Setting::get('smtp_encryption') === 'ssl' ? 'selected' : '' }}>SSL</option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Username</label><input name="smtp_username" class="form-control" value="{{ \App\Models\Setting::getSecure('smtp_username', env('MAIL_USERNAME')) }}"></div>
                <div class="col-md-4"><label class="form-label">Password</label><input name="smtp_password" type="password" class="form-control" value="{{ \App\Models\Setting::getSecure('smtp_password', env('MAIL_PASSWORD')) }}"></div>
                <div class="col-md-4"><label class="form-label">From Address</label><input name="smtp_from_address" type="email" class="form-control" value="{{ \App\Models\Setting::get('smtp_from_address', env('MAIL_FROM_ADDRESS')) }}"></div>
                <div class="col-md-4"><label class="form-label">From Name</label><input name="smtp_from_name" class="form-control" value="{{ \App\Models\Setting::get('smtp_from_name', env('MAIL_FROM_NAME', 'CRM')) }}"></div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save SMTP Settings</button></div>
        </form>
    </div>

    <div class="chart-card">
        <form method="POST" action="{{ route('admin.settings.smtp.test') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label">Send Test Email To</label>
                <input type="email" name="test_email" class="form-control" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary">Send Test Email</button>
            </div>
        </form>
    </div>
@endsection
