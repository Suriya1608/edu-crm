@extends('layouts.app')

@section('page_title', 'Security Settings')

@section('content')
    @include('admin.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card">
        <div class="chart-header mb-4">
            <h3>Security Settings</h3>
            <p class="text-muted small mb-0">Configure login security policies for all users.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.security.update') }}">
            @csrf

            {{-- Login Attempt Limit --}}
            <div class="mb-4">
                <h5 class="fw-semibold mb-1" style="font-size:15px;">
                    <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;color:#137fec;">block</span>
                    Account Lockout Policy
                </h5>
                <p class="text-muted small mb-3">
                    After the specified number of consecutive failed login attempts, the account will be locked for 24 hours.
                    The admin can unlock the account manually from the User Management page.
                </p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Failed Attempts Before Lockout</label>
                        <div class="input-group">
                            <input
                                type="number"
                                name="login_attempt_limit"
                                class="form-control @error('login_attempt_limit') is-invalid @enderror"
                                value="{{ old('login_attempt_limit', \App\Models\Setting::get('login_attempt_limit', 5)) }}"
                                min="3"
                                max="20"
                                required
                            >
                            <span class="input-group-text">attempts</span>
                        </div>
                        @error('login_attempt_limit')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted">Min: 3 &nbsp;|&nbsp; Max: 20 &nbsp;|&nbsp; Default: 5</div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            {{-- 2FA Per Role --}}
            <div class="mb-4">
                <h5 class="fw-semibold mb-1" style="font-size:15px;">
                    <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;color:#10b981;">verified_user</span>
                    Two-Factor Authentication (2FA)
                </h5>
                <p class="text-muted small mb-3">
                    When enabled for a role, users must verify a 6-digit OTP sent to their email after entering their password.
                    The code expires in <strong>10 minutes</strong>.
                </p>

                <div class="row g-3">
                    @php
                        $roles = [
                            'admin'      => ['label' => 'Admin',      'icon' => 'admin_panel_settings', 'color' => '#137fec'],
                            'manager'    => ['label' => 'Manager',    'icon' => 'manage_accounts',      'color' => '#8b5cf6'],
                            'telecaller' => ['label' => 'Telecaller', 'icon' => 'headset_mic',          'color' => '#10b981'],
                        ];
                    @endphp

                    @foreach ($roles as $roleKey => $roleInfo)
                        @php
                            $enabled = \App\Models\Setting::get('2fa_' . $roleKey, '1') === '1';
                        @endphp
                        <div class="col-md-4">
                            <div class="border rounded p-3" style="border-color:#e2e8f0 !important;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="material-icons" style="font-size:20px;color:{{ $roleInfo['color'] }};">{{ $roleInfo['icon'] }}</span>
                                        <span class="fw-semibold" style="font-size:14px;">{{ $roleInfo['label'] }}</span>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="2fa_{{ $roleKey }}"
                                            id="2fa_{{ $roleKey }}"
                                            role="switch"
                                            value="1"
                                            {{ $enabled ? 'checked' : '' }}
                                            style="width:2.5em;height:1.3em;cursor:pointer;"
                                        >
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge {{ $enabled ? 'bg-success' : 'bg-secondary' }} small" id="badge_{{ $roleKey }}">
                                        {{ $enabled ? '2FA Enabled' : '2FA Disabled' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        ['admin', 'manager', 'telecaller'].forEach(function (role) {
                            const toggle = document.getElementById('2fa_' + role);
                            const badge  = document.getElementById('badge_' + role);
                            if (!toggle || !badge) return;
                            toggle.addEventListener('change', function () {
                                if (this.checked) {
                                    badge.textContent = '2FA Enabled';
                                    badge.className = 'badge bg-success small';
                                } else {
                                    badge.textContent = '2FA Disabled';
                                    badge.className = 'badge bg-secondary small';
                                }
                            });
                        });
                    });
                </script>
            </div>

            <hr class="my-4">

            {{-- HTTP Security Headers Info --}}
            <div class="mb-4">
                <h5 class="fw-semibold mb-1" style="font-size:15px;">
                    <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;color:#137fec;">security</span>
                    HTTP Security Headers
                </h5>
                <div class="alert alert-info d-flex align-items-start gap-2 mb-0" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;">
                    <span class="material-icons mt-1" style="font-size:18px;">check_circle</span>
                    <div>
                        <strong>Security headers are active on all responses.</strong>
                        <div class="mt-1 small" style="line-height:1.7;">
                            <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;">X-Frame-Options: SAMEORIGIN</code> &nbsp;
                            <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;">X-Content-Type-Options: nosniff</code> &nbsp;
                            <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;">Referrer-Policy</code> &nbsp;
                            <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;">Permissions-Policy</code> &nbsp;
                            <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;">Content-Security-Policy</code> &nbsp;
                            <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;">HSTS</code> (HTTPS only)
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            {{-- SQL Injection Prevention Info --}}
            <div class="mb-4">
                <h5 class="fw-semibold mb-1" style="font-size:15px;">
                    <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;color:#8b5cf6;">storage</span>
                    SQL Injection Prevention
                </h5>
                <div class="alert alert-info d-flex align-items-start gap-2 mb-0" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;">
                    <span class="material-icons mt-1" style="font-size:18px;">check_circle</span>
                    <div>
                        <strong>All database queries use PDO prepared statements.</strong>
                        Input is sanitized on every POST/PUT/PATCH request — null bytes are stripped, whitespace is trimmed,
                        and all dynamic values are passed as bound parameters through Laravel's Eloquent ORM.
                    </div>
                </div>
            </div>

            <hr class="my-4">

            {{-- Device Tracking Info --}}
            <div class="mb-4">
                <h5 class="fw-semibold mb-1" style="font-size:15px;">
                    <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;color:#8b5cf6;">devices</span>
                    Login Device &amp; Location Tracking
                </h5>
                <div class="alert alert-info d-flex align-items-start gap-2 mb-0" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;">
                    <span class="material-icons mt-1" style="font-size:18px;">check_circle</span>
                    <div>
                        <strong>Device and location tracking is active.</strong> Each login records the user's IP address,
                        browser, platform, device type, and geolocation (city, state, country) via ip-api.com.
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:16px;">save</span>
                    Save Security Settings
                </button>
            </div>
        </form>
    </div>
@endsection
