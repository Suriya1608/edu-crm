@extends('super-admin.layout')
@section('title', 'Edit — ' . $tenant->name)
@section('page-title', $tenant->name . ' — Settings')

@section('content')
<style>
    .nav-tabs .nav-link { color: #64748b; font-weight: 500; font-size: .875rem; border: none; border-bottom: 2px solid transparent; padding: .6rem 1.2rem; }
    .nav-tabs .nav-link.active { color: #6366f1; border-bottom-color: #6366f1; background: none; }
    .nav-tabs { border-bottom: 2px solid #e2e8f0; }
    .tab-content { padding-top: 1.5rem; }
    .section-header { font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; font-weight: 600; margin-bottom: 1rem; padding-bottom: .5rem; border-bottom: 1px solid #f1f5f9; }
    .form-label { font-size: .8rem; font-weight: 600; color: #374151; }
    .credential-note { font-size: .75rem; color: #94a3b8; margin-top: .25rem; }
    .save-bar { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 1rem 1.5rem; margin: 1.5rem -1.5rem -1.5rem; border-radius: 0 0 12px 12px; }
</style>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="{{ route('superadmin.tenants.index') }}" class="text-muted text-decoration-none">&larr; All Tenants</a>
    <span class="text-muted">/</span>
    <span class="fw-700" style="color:#0f172a">{{ $tenant->name }}</span>
    <span class="badge {{ $tenant->is_active ? 'badge-active' : 'badge-inactive' }} px-2 py-1 rounded-pill ms-1">{{ $tenant->is_active ? 'Active' : 'Inactive' }}</span>
    <span class="text-muted small ms-auto"><code>{{ $tenant->subdomain }}.{{ env('APP_DOMAIN','insighttechnology.in') }}</code></span>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2"><small>{{ session('success') }}</small><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card border-0 shadow-sm" style="border-radius:12px">
    <div class="card-body p-0">

        {{-- Tab Nav --}}
        <div class="px-4 pt-3">
            <ul class="nav nav-tabs" id="settingsTabs">
                @php
                    $tabs = [
                        'site'      => ['🏢', 'Site Details'],
                        'smtp'      => ['📧', 'SMTP / Email'],
                        'tcn'       => ['📞', 'TCN Softphone'],
                        'whatsapp'  => ['💬', 'WhatsApp'],
                        'zoom'      => ['📹', 'Zoom'],
                        'google'    => ['🔵', 'Google Meet'],
                        'broadcast' => ['⚡', 'Real-time'],
                    ];
                    $activeTab = request()->query('tab', session('_old_input.tab', 'site'));
                @endphp
                @foreach($tabs as $key => [$icon, $label])
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === $key ? 'active' : '' }}"
                           href="#tab-{{ $key }}" data-bs-toggle="tab">{{ $icon }} {{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="tab-content px-4 pb-0">

            {{-- ── SITE DETAILS ──────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $activeTab === 'site' ? 'show active' : '' }}" id="tab-site">
                <form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="site">

                    <p class="section-header">Site Details</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Site Name</label>
                            <input type="text" name="site[name]" class="form-control form-control-sm" value="{{ old('site.name', $tenant->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Site URL</label>
                            <input type="url" name="site[url]" class="form-control form-control-sm" value="{{ old('site.url', $tenant->site_url ?? '') }}" placeholder="https://client.edu">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Timezone</label>
                            <select name="site[timezone]" class="form-select form-select-sm">
                                @foreach(timezone_identifiers_list() as $tz)
                                    <option value="{{ $tz }}" {{ ($tenant->site_timezone ?? 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Logo URL</label>
                            <input type="text" name="site[logo]" class="form-control form-control-sm" value="{{ old('site.logo', $tenant->site_logo ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Favicon URL</label>
                            <input type="text" name="site[favicon]" class="form-control form-control-sm" value="{{ old('site.favicon', $tenant->site_favicon ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Telephony Provider</label>
                            <select name="site[telephony_provider]" class="form-select form-select-sm">
                                <option value="tcn"    {{ ($tenant->site_telephony_provider ?? 'tcn') === 'tcn'    ? 'selected' : '' }}>TCN</option>
                                <option value="exotel" {{ ($tenant->site_telephony_provider ?? '') === 'exotel'    ? 'selected' : '' }}>Exotel</option>
                                <option value="twilio" {{ ($tenant->site_telephony_provider ?? '') === 'twilio'    ? 'selected' : '' }}>Twilio</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plan</label>
                            <select name="site[plan]" class="form-select form-select-sm">
                                <option value="">— None —</option>
                                @foreach(['standard','professional','enterprise'] as $p)
                                    <option value="{{ $p }}" {{ ($tenant->plan ?? '') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="save-bar"><button class="btn btn-primary btn-sm px-4">Save Site Details</button></div>
                </form>
            </div>

            {{-- ── SMTP ───────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $activeTab === 'smtp' ? 'show active' : '' }}" id="tab-smtp">
                <form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="smtp">

                    <p class="section-header">SMTP / Email</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Mailer</label>
                            <select name="smtp[mailer]" class="form-select form-select-sm">
                                <option value="smtp"     {{ ($tenant->smtp_mailer ?? 'smtp') === 'smtp'     ? 'selected' : '' }}>SMTP</option>
                                <option value="sendmail" {{ ($tenant->smtp_mailer ?? '') === 'sendmail'      ? 'selected' : '' }}>Sendmail</option>
                                <option value="log"      {{ ($tenant->smtp_mailer ?? '') === 'log'           ? 'selected' : '' }}>Log (testing)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp[host]" class="form-control form-control-sm" value="{{ $tenant->smtp_host ?? '' }}" placeholder="smtp.hostinger.com" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Port</label>
                            <input type="number" name="smtp[port]" class="form-control form-control-sm" value="{{ $tenant->smtp_port ?? 465 }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Encryption</label>
                            <select name="smtp[encryption]" class="form-select form-select-sm">
                                <option value="ssl" {{ ($tenant->smtp_encryption ?? 'ssl') === 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value="tls" {{ ($tenant->smtp_encryption ?? '') === 'tls'   ? 'selected' : '' }}>TLS</option>
                                <option value=""    {{ ($tenant->smtp_encryption ?? '') === ''       ? 'selected' : '' }}>None</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" name="smtp[username]" class="form-control form-control-sm" value="{{ $tenant->smtp_username ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="smtp[password]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->smtp_password) ? '● Password is set' : 'Not set' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From Address</label>
                            <input type="email" name="smtp[from_address]" class="form-control form-control-sm" value="{{ $tenant->smtp_from_address ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From Name</label>
                            <input type="text" name="smtp[from_name]" class="form-control form-control-sm" value="{{ $tenant->smtp_from_name ?? '' }}">
                        </div>
                    </div>
                    <div class="save-bar"><button class="btn btn-primary btn-sm px-4">Save SMTP Settings</button></div>
                </form>
            </div>

            {{-- ── TCN SOFTPHONE ──────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $activeTab === 'tcn' ? 'show active' : '' }}" id="tab-tcn">
                <form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="tcn">

                    <p class="section-header">TCN Softphone</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Client ID</label>
                            <input type="text" name="tcn[client_id]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->tcn_client_id) ? '● Set' : 'Not set' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Secret</label>
                            <input type="password" name="tcn[client_secret]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->tcn_client_secret) ? '● Set' : 'Not set' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Refresh Token</label>
                            <input type="password" name="tcn[refresh_token]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->tcn_refresh_token) ? '● Set' : 'Not set' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Client SID</label>
                            <input type="text" name="tcn[client_sid]" class="form-control form-control-sm" value="{{ $tenant->tcn_client_sid ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Caller ID</label>
                            <input type="text" name="tcn[caller_id]" class="form-control form-control-sm" value="{{ $tenant->tcn_caller_id ?? '' }}" placeholder="+91XXXXXXXXXX">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Redirect URI</label>
                            <input type="url" name="tcn[redirect_uri]" class="form-control form-control-sm" value="{{ $tenant->tcn_redirect_uri ?? '' }}" placeholder="https://client1.insighttechnology.in/tcn/auth/callback">
                        </div>
                    </div>
                    <div class="save-bar"><button class="btn btn-primary btn-sm px-4">Save TCN Settings</button></div>
                </form>
            </div>

            {{-- ── WHATSAPP ────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $activeTab === 'whatsapp' ? 'show active' : '' }}" id="tab-whatsapp">
                <form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="whatsapp">

                    <p class="section-header">Meta WhatsApp Cloud API</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Access Token</label>
                            <input type="password" name="whatsapp[token]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->wa_token) ? '● Token is set' : 'Not set' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number ID</label>
                            <input type="text" name="whatsapp[phone_number_id]" class="form-control form-control-sm" value="{{ $tenant->wa_phone_number_id ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Account ID</label>
                            <input type="text" name="whatsapp[business_account_id]" class="form-control form-control-sm" value="{{ $tenant->wa_business_account_id ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Webhook Verify Token</label>
                            <input type="text" name="whatsapp[verify_token]" class="form-control form-control-sm" value="{{ $tenant->wa_verify_token ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Default Template Name</label>
                            <input type="text" name="whatsapp[template_name]" class="form-control form-control-sm" value="{{ $tenant->wa_template_name ?? 'hello_world' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Template Language</label>
                            <input type="text" name="whatsapp[template_language]" class="form-control form-control-sm" value="{{ $tenant->wa_template_language ?? 'en' }}">
                        </div>
                    </div>
                    <div class="save-bar"><button class="btn btn-primary btn-sm px-4">Save WhatsApp Settings</button></div>
                </form>
            </div>

            {{-- ── ZOOM ────────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $activeTab === 'zoom' ? 'show active' : '' }}" id="tab-zoom">
                <form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="zoom">

                    <p class="section-header">Zoom (Server-to-Server OAuth)</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Account ID</label>
                            <input type="password" name="zoom[account_id]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->zoom_account_id) ? '● Set' : 'Not set' }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Client ID</label>
                            <input type="password" name="zoom[client_id]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->zoom_client_id) ? '● Set' : 'Not set' }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Client Secret</label>
                            <input type="password" name="zoom[client_secret]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->zoom_client_secret) ? '● Set' : 'Not set' }}</div>
                        </div>
                    </div>
                    <div class="save-bar"><button class="btn btn-primary btn-sm px-4">Save Zoom Settings</button></div>
                </form>
            </div>

            {{-- ── GOOGLE ──────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $activeTab === 'google' ? 'show active' : '' }}" id="tab-google">
                <form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="google">

                    <p class="section-header">Google Meet / Google OAuth</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Google Client ID</label>
                            <input type="text" name="google[client_id]" class="form-control form-control-sm" value="{{ $tenant->google_client_id ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Google Client Secret</label>
                            <input type="password" name="google[client_secret]" class="form-control form-control-sm" placeholder="Leave blank to keep existing">
                            <div class="credential-note">{{ !empty($tenant->google_client_secret) ? '● Set' : 'Not set' }}</div>
                        </div>
                    </div>
                    <div class="save-bar"><button class="btn btn-primary btn-sm px-4">Save Google Settings</button></div>
                </form>
            </div>

            {{-- ── REAL-TIME ────────────────────────────────────────────────── --}}
            <div class="tab-pane fade {{ $activeTab === 'broadcast' ? 'show active' : '' }}" id="tab-broadcast">
                <form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="tab" value="broadcast">

                    <p class="section-header">Real-time Broadcasting</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Driver</label>
                            <select name="broadcast[driver]" class="form-select form-select-sm" id="broadcastDriver">
                                <option value="null"   {{ ($tenant->broadcast_driver ?? 'null') === 'null'   ? 'selected' : '' }}>Disabled</option>
                                <option value="pusher" {{ ($tenant->broadcast_driver ?? '') === 'pusher'     ? 'selected' : '' }}>Pusher</option>
                                <option value="reverb" {{ ($tenant->broadcast_driver ?? '') === 'reverb'     ? 'selected' : '' }}>Laravel Reverb</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3" id="pusherFields" style="{{ ($tenant->broadcast_driver ?? 'null') === 'pusher' ? '' : 'display:none' }}">
                        <p class="section-header mt-2">Pusher Config</p>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">App ID</label><input type="text" name="broadcast[pusher_app_id]" class="form-control form-control-sm" value="{{ $tenant->pusher_app_id ?? '' }}"></div>
                            <div class="col-md-4"><label class="form-label">App Key</label><input type="text" name="broadcast[pusher_key]" class="form-control form-control-sm" value="{{ $tenant->pusher_key ?? '' }}"></div>
                            <div class="col-md-4"><label class="form-label">App Secret</label><input type="password" name="broadcast[pusher_secret]" class="form-control form-control-sm" placeholder="Leave blank to keep"><div class="credential-note">{{ !empty($tenant->pusher_secret) ? '● Set' : 'Not set' }}</div></div>
                            <div class="col-md-4"><label class="form-label">Cluster</label><input type="text" name="broadcast[pusher_cluster]" class="form-control form-control-sm" value="{{ $tenant->pusher_cluster ?? 'mt1' }}"></div>
                        </div>
                    </div>

                    <div class="mt-3" id="reverbFields" style="{{ ($tenant->broadcast_driver ?? 'null') === 'reverb' ? '' : 'display:none' }}">
                        <p class="section-header mt-2">Laravel Reverb Config</p>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">App ID</label><input type="text" name="broadcast[reverb_app_id]" class="form-control form-control-sm" value="{{ $tenant->reverb_app_id ?? '' }}"></div>
                            <div class="col-md-4"><label class="form-label">App Key</label><input type="text" name="broadcast[reverb_key]" class="form-control form-control-sm" value="{{ $tenant->reverb_key ?? '' }}"></div>
                            <div class="col-md-4"><label class="form-label">App Secret</label><input type="password" name="broadcast[reverb_secret]" class="form-control form-control-sm" placeholder="Leave blank to keep"><div class="credential-note">{{ !empty($tenant->reverb_secret) ? '● Set' : 'Not set' }}</div></div>
                            <div class="col-md-4"><label class="form-label">Host</label><input type="text" name="broadcast[reverb_host]" class="form-control form-control-sm" value="{{ $tenant->reverb_host ?? '0.0.0.0' }}"></div>
                            <div class="col-md-3"><label class="form-label">Port</label><input type="number" name="broadcast[reverb_port]" class="form-control form-control-sm" value="{{ $tenant->reverb_port ?? 8080 }}"></div>
                            <div class="col-md-3"><label class="form-label">Scheme</label>
                                <select name="broadcast[reverb_scheme]" class="form-select form-select-sm">
                                    <option value="http"  {{ ($tenant->reverb_scheme ?? 'http') === 'http'  ? 'selected' : '' }}>http</option>
                                    <option value="https" {{ ($tenant->reverb_scheme ?? '') === 'https'     ? 'selected' : '' }}>https</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="save-bar"><button class="btn btn-primary btn-sm px-4">Save Broadcasting Settings</button></div>
                </form>
            </div>

        </div>{{-- end tab-content --}}
    </div>
</div>

<script>
document.getElementById('broadcastDriver').addEventListener('change', function () {
    document.getElementById('pusherFields').style.display = this.value === 'pusher' ? '' : 'none';
    document.getElementById('reverbFields').style.display = this.value === 'reverb' ? '' : 'none';
});

// Restore active tab from URL hash
const hash = window.location.hash.replace('#', '');
if (hash) {
    const tab = document.querySelector(`[href="#tab-${hash}"]`);
    if (tab) new bootstrap.Tab(tab).show();
}
</script>
@endsection
