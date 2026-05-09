<div class="card mb-4 p-3">
    <div class="row g-3">

        {{-- System --}}
        <div class="col-auto">
            <div class="text-uppercase fw-semibold mb-2"
                 style="font-size:10px;letter-spacing:.6px;color:#94a3b8;">System</div>
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('admin.settings.general') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.general') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    General
                </a>
                <a href="{{ route('admin.settings.pages') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.pages') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Pages
                </a>
                <a href="{{ route('admin.settings.security') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.security') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Security
                </a>
            </div>
        </div>

        <div class="vr d-none d-md-block" style="opacity:.15;"></div>

        {{-- Communication --}}
        <div class="col-auto">
            <div class="text-uppercase fw-semibold mb-2"
                 style="font-size:10px;letter-spacing:.6px;color:#94a3b8;">Communication</div>
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('admin.settings.smtp') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.smtp') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    SMTP
                </a>
                <a href="{{ route('admin.settings.sms') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.sms') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    SMS
                </a>
                <a href="{{ route('admin.settings.whatsapp') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.whatsapp') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    WhatsApp
                </a>
                <a href="{{ route('admin.settings.tcn') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.tcn', 'admin.settings.call') || request()->routeIs('admin.tcn-relay-clients.*') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <span class="material-icons me-1" style="font-size:13px;vertical-align:middle;">headset_mic</span>
                    Softphone
                </a>
                <a href="{{ route('admin.settings.zoom') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.zoom') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Zoom
                </a>
                <a href="{{ route('admin.settings.google-meet') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.google-meet') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Google Meet
                </a>
                <a href="{{ route('admin.settings.realtime') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.realtime') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <span class="material-icons me-1" style="font-size:13px;vertical-align:middle;">bolt</span>
                    Real-Time
                </a>
            </div>
        </div>

        <div class="vr d-none d-md-block" style="opacity:.15;"></div>

        {{-- Lead Management --}}
        <div class="col-auto">
            <div class="text-uppercase fw-semibold mb-2"
                 style="font-size:10px;letter-spacing:.6px;color:#94a3b8;">Lead Management</div>
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('admin.settings.default-lead-status') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.default-lead-status') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Lead Status
                </a>
                <a href="{{ route('admin.settings.lead-portals') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.lead-portals') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Lead Portals
                </a>
                <a href="{{ route('admin.settings.notifications') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.notifications') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Notifications
                </a>
            </div>
        </div>

        <div class="vr d-none d-md-block" style="opacity:.15;"></div>

        {{-- Operations --}}
        <div class="col-auto">
            <div class="text-uppercase fw-semibold mb-2"
                 style="font-size:10px;letter-spacing:.6px;color:#94a3b8;">Operations</div>
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('admin.settings.business-hours') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.business-hours') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Business Hours
                </a>
                <a href="{{ route('admin.settings.working-days') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.working-days') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Working Days
                </a>
                <a href="{{ route('admin.settings.timezone') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.settings.timezone') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Timezone
                </a>
            </div>
        </div>

    </div>
</div>
