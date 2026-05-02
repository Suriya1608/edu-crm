<style>
/* ═══════════════════════════════════════════
   FLOATING GLASS SIDEBAR — override
   ═══════════════════════════════════════════ */
.sidebar {
    position: fixed !important;
    left: 20px !important;
    top: 55% !important;
    transform: translateY(-50%) !important;
    height: 77vh !important;
    width: 72px !important;
    background: #000!important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255,255,255,0.42) !important;
    border-radius: 26px !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.03) !important;
    display: flex !important;
    flex-direction: column !important;
    z-index: 1000 !important;
    overflow: hidden !important;
    transition: width 0.30s cubic-bezier(0.4,0,0.2,1),
                box-shadow 0.30s ease !important;
}
.sidebar:hover {
    width: 224px !important;
    box-shadow: 0 16px 44px rgba(0,0,0,0.13), 0 4px 12px rgba(0,0,0,0.06) !important;
}
.sidebar.expanded { width: 224px !important; }
.sidebar.mobile-hidden {
    transform: translateY(-50%) translateX(calc(-100% - 28px)) !important;
}

/* Main content offset */
.main-content { margin-left: 114px !important; }
@media (max-width: 768px) {
    .sidebar { left: 10px !important; width: 62px !important; }
    .sidebar:hover { width: 200px !important; }
    .main-content { margin-left: 86px !important; }
}

/* ── Header ────────────────────────────────── */
.sb-header {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 16px 13px; flex-shrink: 0;
    border-bottom: 1px solid rgba(99,102,241,0.09);
    cursor: pointer; user-select: none;
}
.sb-logo-icon {
    width: 40px; height: 40px; flex-shrink: 0;
    background: linear-gradient(135deg, #47474c, #747276);;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(99,102,241,0.38);
    transition: transform 0.22s ease;
}
.sb-header:hover .sb-logo-icon { transform: scale(1.06); }
.sb-site-name {
    font-size: 13px; font-weight: 700; color: #ffffff;
    white-space: nowrap; line-height: 1.3; flex-shrink: 0;
}

/* ── Nav container ─────────────────────────── */
.sb-nav {
    flex: 1; overflow-y: auto; overflow-x: hidden;
    padding: 8px 10px;
    scrollbar-width: thin;
    scrollbar-color: rgba(99,102,241,0.18) transparent;
}
.sb-nav::-webkit-scrollbar { width: 3px; }
.sb-nav::-webkit-scrollbar-track { background: transparent; }
.sb-nav::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.18); border-radius: 3px; }

/* ── Section labels ────────────────────────── */
.sb-section {
    font-size: 9px; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: #ffffff; padding: 14px 14px 5px;
    white-space: nowrap; opacity: 0;
    transition: opacity 0.18s ease 0.10s;
}
.sidebar:hover .sb-section,
.sidebar.expanded .sb-section { opacity: 1; }

/* ── Nav items ─────────────────────────────── */
.sb-item {
    display: flex !important; align-items: center !important;
    gap: 10px !important; padding: 5px 6px !important;
    border-radius: 16px !important; margin-bottom: 3px !important;
    text-decoration: none !important; white-space: nowrap !important;
    position: relative !important; border: none !important;
    background: transparent !important; cursor: pointer !important;
    color: #fff !important; font-size: 13px !important;
    font-weight: 500 !important; width: 100% !important;
    text-align: left !important;
    transition: background 0.22s ease, color 0.22s ease !important;
}
.sb-item:hover {
    background: rgba(99,102,241,0.07) !important;
    color: #ffffff !important; text-decoration: none !important;
}
.sb-item.active {
    color: #fff !important;
    background: rgb(113 113 118 / 27%) !important;;
}

/* ── Icon wrapper ──────────────────────────── */
.sb-icon-wrap {
    width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    position: relative;
    transition: background 0.22s ease, transform 0.22s ease, box-shadow 0.22s ease;
}
.sb-item:hover .sb-icon-wrap {
    background: rgba(99,102,241,0.11);
    transform: scale(1.05);
}
.sb-item.active .sb-icon-wrap {
    background:linear-gradient(135deg, #7d7d88, #87848a);
    /* box-shadow: 0 4px 14px rgba(216, 216, 252, 0.38), 0 0 0 4px rgba(99,102,241,0.10); */
    transform: scale(1.05);
}
.sb-icon-wrap .material-icons {
    font-size: 20px !important; color: #94A3B8;
    transition: color 0.22s ease;
}
.sb-item:hover .sb-icon-wrap .material-icons { color: #ffffff; }
.sb-item.active .sb-icon-wrap .material-icons { color: #ffffff !important; }

/* Badge dot on icon */
.sb-badge-dot {
    position: absolute; top: 5px; right: 5px;
    width: 8px; height: 8px; background: #ef4444;
    border-radius: 50%; border: 2px solid rgba(255,255,255,0.85);
    animation: sbDotPulse 2s ease-in-out infinite;
}
@keyframes sbDotPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.25)} }

/* ── Labels ────────────────────────────────── */
.sb-label {
    opacity: 0; white-space: nowrap; overflow: hidden; flex-shrink: 0;
    transition: opacity 0.18s ease 0.08s;
}
.sidebar:hover .sb-label,
.sidebar.expanded .sb-label { opacity: 1; }

/* ── Chevron ───────────────────────────────── */
.sb-chevron {
    font-size: 16px !important; color: #94A3B8 !important;
    margin-left: auto !important; opacity: 0; flex-shrink: 0;
    transition: opacity 0.18s ease 0.08s, transform 0.22s ease !important;
}
.sidebar:hover .sb-chevron,
.sidebar.expanded .sb-chevron { opacity: 1; }
.sb-item[aria-expanded="true"] .sb-chevron { transform: rotate(180deg); }

/* ── Sub-menus ─────────────────────────────── */
.sb-submenu { padding: 2px 0 2px 8px; }
.sb-subitem {
    display: flex; align-items: center;
    padding: 7px 10px 7px 52px; border-radius: 10px;
    margin-bottom: 2px; text-decoration: none;
    font-size: 12.5px; font-weight: 500; color: #64748b;
    white-space: nowrap; position: relative;
    transition: background 0.18s ease, color 0.18s ease;
}
.sb-subitem::before {
    content: ''; position: absolute; left: 36px; top: 50%;
    transform: translateY(-50%);
    width: 5px; height: 5px; border-radius: 50%; background: #cbd5e1;
    transition: background 0.18s ease, transform 0.18s ease;
}
.sb-subitem:hover {
    background: rgba(99,102,241,0.07); color: #6366f1;
    text-decoration: none;
}
.sb-subitem:hover::before { background: #6366f1; transform: translateY(-50%) scale(1.3); }
.sb-subitem.active { color: #6366f1; font-weight: 600; }
.sb-subitem.active::before { background: #6366f1; }

/* ── Footer ────────────────────────────────── */
.sb-footer {
    padding: 10px; border-top: 1px solid rgba(99,102,241,0.09);
    flex-shrink: 0; position: relative;
}
.sb-user {
    display: flex; align-items: center; gap: 10px;
    padding: 5px 6px; border-radius: 16px; cursor: pointer;
    transition: background 0.22s ease;
}
.sb-user:hover { background: rgba(99,102,241,0.07); }

.sb-avatar {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #47474c, #747276);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 3px 10px rgba(99,102,241,0.35);
    font-size: 15px; font-weight: 700; color: #fff; line-height: 1;
    transition: transform 0.22s ease; font-family: 'Poppins', sans-serif;
}
.sb-user:hover .sb-avatar { transform: scale(1.05); }

.sb-user-info { min-width: 0; flex: 1; }
.sb-user-info p {
    margin: 0; font-size: 12px; font-weight: 600; color: #ffffff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sb-user-info small { font-size: 10px; color: #94A3B8; white-space: nowrap; }

.sb-logout-btn {
    width: 32px; height: 32px; border-radius: 10px; border: none;
    background: transparent; cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    color: #94A3B8; transition: all 0.2s ease; padding: 0;
}
.sb-logout-btn:hover { background: rgba(239,68,68,0.10); color: #ef4444; }
.sb-logout-btn .material-icons { font-size: 18px !important; }
</style>

<aside class="sidebar" id="sidebar">

    {{-- Logo / Pin toggle --}}
    <div class="sb-header" onclick="toggleSidebarPin()" title="Pin sidebar open">
        <div class="sb-logo-icon">
            @php $siteLogo = \App\Models\Setting::get('site_logo'); @endphp
            @if($siteLogo)
                <img src="{{ asset('storage/' . $siteLogo) }}" alt="Logo"
                     style="width:26px;height:26px;object-fit:contain;border-radius:8px;">
            @else
                <span class="material-icons" style="font-size:19px;color:#fff;">school</span>
            @endif
        </div>
        <span class="sb-label sb-site-name">{{ \App\Models\Setting::get('site_name', 'Admission CRM') }}</span>
    </div>

    <nav class="sb-nav">

        {{-- ══════════════ ADMIN ══════════════ --}}
        @if (auth()->user()->role == 'admin')
            @php
                $adminUsersActive      = request()->routeIs('admin.users*');
                $adminLeadsActive      = request()->routeIs('admin.leads.*');
                $adminCampaignsActive  = request()->routeIs('admin.campaigns.*');
                $adminEmailCampActive  = request()->routeIs('admin.email-campaigns*') || request()->routeIs('admin.email-templates*');
                $adminReportsActive    = request()->routeIs('admin.reports.*');
                $adminAutomationActive = request()->routeIs('admin.automation.*');
                $adminSettingsActive   = request()->routeIs('admin.settings.*') || request()->routeIs('admin.settings');
            @endphp

            <a href="{{ route('admin.dashboard') }}"
               class="sb-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">dashboard</span></div>
                <span class="sb-label">Dashboard</span>
            </a>

            <div class="sb-section">People</div>

            <button class="sb-item {{ $adminUsersActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminUsersMenu"
                aria-expanded="{{ $adminUsersActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">group</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">User Management</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="adminUsersMenu" class="collapse sb-submenu {{ $adminUsersActive ? 'show' : '' }}">
                <a href="{{ route('admin.users.admins') }}"   class="sb-subitem {{ request()->routeIs('admin.users.admins')     ? 'active' : '' }}">Admin Users</a>
                <a href="{{ route('admin.users.managers') }}" class="sb-subitem {{ request()->routeIs('admin.users.managers')   ? 'active' : '' }}">Managers</a>
                <a href="{{ route('admin.users.telecallers') }}" class="sb-subitem {{ request()->routeIs('admin.users.telecallers') ? 'active' : '' }}">Telecallers</a>
            </div>

            <button class="sb-item {{ $adminLeadsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminLeadsMenu"
                aria-expanded="{{ $adminLeadsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">person_add</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Lead Management</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="adminLeadsMenu" class="collapse sb-submenu {{ $adminLeadsActive ? 'show' : '' }}">
                <a href="{{ route('admin.leads.all') }}"        class="sb-subitem {{ request()->routeIs('admin.leads.all')        ? 'active' : '' }}">All Leads</a>
                <a href="{{ route('admin.leads.unassigned') }}" class="sb-subitem {{ request()->routeIs('admin.leads.unassigned') ? 'active' : '' }}">Unassigned</a>
                <a href="{{ route('admin.leads.assigned') }}"   class="sb-subitem {{ request()->routeIs('admin.leads.assigned')   ? 'active' : '' }}">Assigned</a>
                <a href="{{ route('admin.leads.converted') }}"  class="sb-subitem {{ request()->routeIs('admin.leads.converted')  ? 'active' : '' }}">Converted</a>
                <a href="{{ route('admin.leads.lost') }}"       class="sb-subitem {{ request()->routeIs('admin.leads.lost')       ? 'active' : '' }}">Lost</a>
                <a href="{{ route('admin.leads.duplicates') }}" class="sb-subitem {{ request()->routeIs('admin.leads.duplicates') ? 'active' : '' }}">Duplicates</a>
            </div>

            <div class="sb-section">Outreach</div>

            <button class="sb-item {{ $adminCampaignsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminCampaignsMenu"
                aria-expanded="{{ $adminCampaignsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">insights</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Campaigns</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="adminCampaignsMenu" class="collapse sb-submenu {{ $adminCampaignsActive ? 'show' : '' }}">
                <a href="{{ route('admin.campaigns.performance') }}" class="sb-subitem {{ request()->routeIs('admin.campaigns.performance') ? 'active' : '' }}">Performance</a>
                <a href="{{ route('admin.campaigns.contacts') }}"    class="sb-subitem {{ request()->routeIs('admin.campaigns.contacts')    ? 'active' : '' }}">All Contacts</a>
            </div>

            <a href="{{ route('admin.marketing.social.media') }}"
               class="sb-item {{ request()->routeIs('admin.marketing.*') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">share</span></div>
                <span class="sb-label">Social Media</span>
            </a>

            <button class="sb-item {{ $adminEmailCampActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminEmailMenu"
                aria-expanded="{{ $adminEmailCampActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">mark_email_read</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Email Marketing</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="adminEmailMenu" class="collapse sb-submenu {{ $adminEmailCampActive ? 'show' : '' }}">
                <a href="{{ route('admin.email-campaigns.index') }}" class="sb-subitem {{ request()->routeIs('admin.email-campaigns*') ? 'active' : '' }}">Campaigns</a>
                <a href="{{ route('admin.email-templates.index') }}" class="sb-subitem {{ request()->routeIs('admin.email-templates*') ? 'active' : '' }}">Templates</a>
            </div>

            <div class="sb-section">Analytics</div>

            <button class="sb-item {{ $adminReportsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminReportsMenu"
                aria-expanded="{{ $adminReportsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">bar_chart</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Reports</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="adminReportsMenu" class="collapse sb-submenu {{ $adminReportsActive ? 'show' : '' }}">
                <a href="{{ route('admin.reports.telecaller-performance') }}" class="sb-subitem {{ request()->routeIs('admin.reports.telecaller-performance') ? 'active' : '' }}">Telecaller Performance</a>
                <a href="{{ route('admin.reports.manager-performance') }}"   class="sb-subitem {{ request()->routeIs('admin.reports.manager-performance')   ? 'active' : '' }}">Manager Performance</a>
                <a href="{{ route('admin.reports.conversion') }}"            class="sb-subitem {{ request()->routeIs('admin.reports.conversion')            ? 'active' : '' }}">Conversion Report</a>
                <a href="{{ route('admin.reports.lead-source') }}"           class="sb-subitem {{ request()->routeIs('admin.reports.lead-source')           ? 'active' : '' }}">Lead Source</a>
                <a href="{{ route('admin.reports.period') }}"                class="sb-subitem {{ request()->routeIs('admin.reports.period')                ? 'active' : '' }}">Daily / Weekly / Monthly</a>
                <a href="{{ route('admin.reports.call-efficiency') }}"       class="sb-subitem {{ request()->routeIs('admin.reports.call-efficiency')       ? 'active' : '' }}">Call Efficiency</a>
                <a href="{{ route('admin.reports.response-time') }}"         class="sb-subitem {{ request()->routeIs('admin.reports.response-time')         ? 'active' : '' }}">Response Time</a>
            </div>

            <button class="sb-item {{ $adminAutomationActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminAutomationMenu"
                aria-expanded="{{ $adminAutomationActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">auto_fix_high</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Automation</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="adminAutomationMenu" class="collapse sb-submenu {{ $adminAutomationActive ? 'show' : '' }}">
                <a href="{{ route('admin.automation.lead-assignment') }}"    class="sb-subitem {{ request()->routeIs('admin.automation.lead-assignment')    ? 'active' : '' }}">Lead Assignment Rules</a>
                <a href="{{ route('admin.automation.followup-reminders') }}" class="sb-subitem {{ request()->routeIs('admin.automation.followup-reminders') ? 'active' : '' }}">Follow-up Reminders</a>
                <a href="{{ route('admin.automation.escalation') }}"         class="sb-subitem {{ request()->routeIs('admin.automation.escalation')         ? 'active' : '' }}">Escalation Rules</a>
            </div>

            <div class="sb-section">Master Data</div>

            <a href="{{ route('admin.courses.index') }}"        class="sb-item {{ request()->routeIs('admin.courses*')         ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">school</span></div>
                <span class="sb-label">Courses</span>
            </a>
            <a href="{{ route('admin.academic-years.index') }}" class="sb-item {{ request()->routeIs('admin.academic-years*')  ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">calendar_month</span></div>
                <span class="sb-label">Academic Years</span>
            </a>
            <a href="{{ route('admin.course-intakes.index') }}" class="sb-item {{ request()->routeIs('admin.course-intakes*')  ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">event_seat</span></div>
                <span class="sb-label">Course Intakes</span>
            </a>
            <a href="{{ route('admin.documents') }}"            class="sb-item {{ request()->routeIs('admin.documents*')       ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">folder_open</span></div>
                <span class="sb-label">Documents</span>
            </a>

            <div class="sb-section">System</div>

            <button class="sb-item {{ $adminSettingsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminSettingsMenu"
                aria-expanded="{{ $adminSettingsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">settings</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Settings</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="adminSettingsMenu" class="collapse sb-submenu {{ $adminSettingsActive ? 'show' : '' }}">
                <a href="{{ route('admin.settings.general') }}"              class="sb-subitem {{ request()->routeIs('admin.settings.general')              ? 'active' : '' }}">General</a>
                <a href="{{ route('admin.settings.smtp') }}"                 class="sb-subitem {{ request()->routeIs('admin.settings.smtp')                 ? 'active' : '' }}">SMTP Settings</a>
                <a href="{{ route('admin.settings.sms') }}"                  class="sb-subitem {{ request()->routeIs('admin.settings.sms')                  ? 'active' : '' }}">SMS Settings</a>
                <a href="{{ route('admin.settings.whatsapp') }}"             class="sb-subitem {{ request()->routeIs('admin.settings.whatsapp')             ? 'active' : '' }}">Meta WhatsApp</a>
                <a href="{{ route('admin.settings.instagram') }}"            class="sb-subitem {{ request()->routeIs('admin.settings.instagram')            ? 'active' : '' }}">Instagram</a>
                <a href="{{ route('admin.settings.business-hours') }}"       class="sb-subitem {{ request()->routeIs('admin.settings.business-hours')       ? 'active' : '' }}">Business Hours</a>
                <a href="{{ route('admin.settings.working-days') }}"         class="sb-subitem {{ request()->routeIs('admin.settings.working-days')         ? 'active' : '' }}">Working Days</a>
                <a href="{{ route('admin.settings.timezone') }}"             class="sb-subitem {{ request()->routeIs('admin.settings.timezone')             ? 'active' : '' }}">Timezone</a>
                <a href="{{ route('admin.settings.default-lead-status') }}"  class="sb-subitem {{ request()->routeIs('admin.settings.default-lead-status')  ? 'active' : '' }}">Default Lead Status</a>
                <a href="{{ route('admin.settings.notifications') }}"        class="sb-subitem {{ request()->routeIs('admin.settings.notifications')        ? 'active' : '' }}">Notifications</a>
                <a href="{{ route('admin.settings.pages') }}"                class="sb-subitem {{ request()->routeIs('admin.settings.pages')                ? 'active' : '' }}">Pages</a>
            </div>
        @endif


        {{-- ══════════════ MANAGER ══════════════ --}}
        @if (auth()->user()->role == 'manager')
            @php
                $mgrLeadsActive     = request()->routeIs('manager.leads*');
                $mgrCampaignsActive = request()->routeIs('manager.campaigns.*');
                $mgrEmailCampActive = request()->routeIs('manager.email-campaigns*');
                $mgrReportsActive   = request()->routeIs('manager.reports.*');
                $mgrFollowupsActive = request()->routeIs('manager.followups.*');
                $mgrCallLogsActive  = request()->routeIs('manager.call-logs.*');
                $mgrCallScope       = request('scope', 'all');
            @endphp

            <a href="{{ route('manager.dashboard') }}" onclick="inertiaVisit(event, this.href)"
               class="sb-item {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">dashboard</span></div>
                <span class="sb-label">Dashboard</span>
            </a>

            <div class="sb-section">People</div>

            <button class="sb-item {{ $mgrLeadsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrLeadsMenu"
                aria-expanded="{{ $mgrLeadsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">person_add</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Leads</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="mgrLeadsMenu" class="collapse sb-submenu {{ $mgrLeadsActive ? 'show' : '' }}">
                <a href="{{ route('manager.leads') }}"            onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.leads')            ? 'active' : '' }}">All Leads</a>
                <a href="{{ route('manager.leads.duplicates') }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.leads.duplicates') ? 'active' : '' }}">Duplicate Leads</a>
            </div>

            <a href="{{ route('manager.telecallers') }}" onclick="inertiaVisit(event, this.href)"
               class="sb-item {{ request()->routeIs('manager.telecallers*') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">headset_mic</span></div>
                <span class="sb-label">Telecallers</span>
            </a>

            <div class="sb-section">Outreach</div>

            <button class="sb-item {{ $mgrCampaignsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrCampaignsMenu"
                aria-expanded="{{ $mgrCampaignsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">campaign</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Campaigns</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="mgrCampaignsMenu" class="collapse sb-submenu {{ $mgrCampaignsActive ? 'show' : '' }}">
                <a href="{{ route('manager.campaigns.index') }}"       onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.campaigns.index') || request()->routeIs('manager.campaigns.show') || request()->routeIs('manager.campaigns.create') || request()->routeIs('manager.campaigns.contact') ? 'active' : '' }}">All Campaigns</a>
                <a href="{{ route('manager.campaigns.performance') }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.campaigns.performance') ? 'active' : '' }}">Performance</a>
            </div>

            <a href="{{ route('manager.email-campaigns.index') }}" onclick="inertiaVisit(event, this.href)"
               class="sb-item {{ $mgrEmailCampActive ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">mark_email_read</span></div>
                <span class="sb-label">Email Campaigns</span>
            </a>

            <a href="{{ route('manager.whatsapp.hub') }}" onclick="inertiaVisit(event, this.href)"
               class="sb-item {{ request()->routeIs('manager.whatsapp.*') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">chat</span></div>
                <span class="sb-label">WhatsApp Chat</span>
            </a>

            <div class="sb-section">Activity</div>

            <button class="sb-item {{ $mgrFollowupsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrFollowupMenu"
                aria-expanded="{{ $mgrFollowupsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">event_note</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Follow-up Management</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="mgrFollowupMenu" class="collapse sb-submenu {{ $mgrFollowupsActive ? 'show' : '' }}">
                <a href="{{ route('manager.followups.today') }}"   onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.followups.today')   ? 'active' : '' }}">Today</a>
                <a href="{{ route('manager.followups.overdue') }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.followups.overdue') ? 'active' : '' }}">Overdue</a>
                <a href="{{ route('manager.followups.upcoming') }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.followups.upcoming') ? 'active' : '' }}">Upcoming</a>
                <a href="{{ route('manager.followups.missed') }}"  onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.followups.missed')  ? 'active' : '' }}">Missed by Telecaller</a>
            </div>

            <button class="sb-item {{ $mgrCallLogsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrCallLogsMenu"
                aria-expanded="{{ $mgrCallLogsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">call</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Call Logs</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="mgrCallLogsMenu" class="collapse sb-submenu {{ $mgrCallLogsActive ? 'show' : '' }}">
                <a href="{{ route('manager.call-logs.index', ['scope' => 'all']) }}"      onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ $mgrCallLogsActive && $mgrCallScope === 'all'      ? 'active' : '' }}">All Calls</a>
                <a href="{{ route('manager.call-logs.index', ['scope' => 'inbound']) }}"  onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ $mgrCallLogsActive && $mgrCallScope === 'inbound'  ? 'active' : '' }}">Inbound</a>
                <a href="{{ route('manager.call-logs.index', ['scope' => 'outbound']) }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ $mgrCallLogsActive && $mgrCallScope === 'outbound' ? 'active' : '' }}">Outbound</a>
                <a href="{{ route('manager.call-logs.index', ['scope' => 'missed']) }}"   onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ $mgrCallLogsActive && $mgrCallScope === 'missed'   ? 'active' : '' }}">Missed</a>
            </div>

            <div class="sb-section">Analytics</div>

            <button class="sb-item {{ $mgrReportsActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrReportsMenu"
                aria-expanded="{{ $mgrReportsActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">bar_chart</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Reports &amp; Analytics</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="mgrReportsMenu" class="collapse sb-submenu {{ $mgrReportsActive ? 'show' : '' }}">
                <a href="{{ route('manager.reports.home') }}"                 onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.reports.home')                 ? 'active' : '' }}">Overview</a>
                <a href="{{ route('manager.reports.telecaller-performance') }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.reports.telecaller-performance') ? 'active' : '' }}">Telecaller Performance</a>
                <a href="{{ route('manager.reports.conversion') }}"           onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.reports.conversion')           ? 'active' : '' }}">Conversion Report</a>
                <a href="{{ route('manager.reports.source-performance') }}"   onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.reports.source-performance')   ? 'active' : '' }}">Source Performance</a>
                <a href="{{ route('manager.reports.period') }}"               onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.reports.period')               ? 'active' : '' }}">Daily / Weekly / Monthly</a>
                <a href="{{ route('manager.reports.response-time') }}"        onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.reports.response-time')        ? 'active' : '' }}">Lead Response Time</a>
                <a href="{{ route('manager.reports.call-efficiency') }}"      onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('manager.reports.call-efficiency')      ? 'active' : '' }}">Call Efficiency</a>
            </div>
        @endif


        {{-- ══════════════ TELECALLER ══════════════ --}}
        @if (auth()->user()->role == 'telecaller')
            @php
                $teleFollowupReminderCount = \App\Models\Followup::query()
                    ->whereHas('lead', function ($q) {
                        $q->where('assigned_to', auth()->id());
                    })
                    ->whereDate('next_followup', '<=', now()->toDateString())
                    ->when(\Illuminate\Support\Facades\Schema::hasColumn('followups', 'completed_at'), function ($q) {
                        $q->whereNull('completed_at');
                    })
                    ->count();
                $teleFollowupMenuActive    = request()->routeIs('telecaller.followups.*');
                $telePerformanceMenuActive = request()->routeIs('telecaller.performance.*');
                $teleCallsMenuActive       = request()->routeIs('telecaller.calls.*');
            @endphp

            <a href="{{ route('telecaller.dashboard') }}" onclick="inertiaVisit(event, this.href)"
               class="sb-item {{ request()->routeIs('telecaller.dashboard') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">dashboard</span></div>
                <span class="sb-label">Dashboard</span>
            </a>

            <div class="sb-section">Work</div>

            <a href="{{ route('telecaller.leads') }}" onclick="inertiaVisit(event, this.href)"
               class="sb-item {{ request()->routeIs('telecaller.leads*') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">person_add</span></div>
                <span class="sb-label">My Leads</span>
            </a>

            <a href="{{ route('telecaller.campaigns.index') }}" onclick="inertiaVisit(event, this.href)"
               class="sb-item {{ request()->routeIs('telecaller.campaigns*') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">campaign</span></div>
                <span class="sb-label">My Campaigns</span>
            </a>

            <div class="sb-section">Messaging</div>

            <a href="{{ route('telecaller.whatsapp.hub') }}" onclick="inertiaVisit(event, this.href)"
               class="sb-item {{ request()->routeIs('telecaller.whatsapp.*') ? 'active' : '' }}">
                <div class="sb-icon-wrap"><span class="material-icons">chat</span></div>
                <span class="sb-label">WhatsApp Chat</span>
            </a>

            <div class="sb-section">Activity</div>

            <button class="sb-item {{ $teleCallsMenuActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#telecallerCallsMenu"
                aria-expanded="{{ $teleCallsMenuActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">call</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">Call Management</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="telecallerCallsMenu" class="collapse sb-submenu {{ $teleCallsMenuActive ? 'show' : '' }}">
                <a href="{{ route('telecaller.calls.outbound') }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.calls.outbound') ? 'active' : '' }}">Outbound Calls</a>
                <a href="{{ route('telecaller.calls.inbound') }}"  onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.calls.inbound')  ? 'active' : '' }}">Inbound Calls</a>
                <a href="{{ route('telecaller.calls.missed') }}"   onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.calls.missed')   ? 'active' : '' }}">Missed Calls</a>
                <a href="{{ route('telecaller.calls.history') }}"  onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.calls.history')  ? 'active' : '' }}">Call History</a>
            </div>

            <button class="sb-item {{ $teleFollowupMenuActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#telecallerFollowupMenu"
                aria-expanded="{{ $teleFollowupMenuActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap">
                    <span class="material-icons">event_note</span>
                    @if($teleFollowupReminderCount > 0)
                        <span class="sb-badge-dot"></span>
                    @endif
                </div>
                <span class="sb-label" style="flex:1;text-align:left;">Follow-ups</span>
                @if($teleFollowupReminderCount > 0)
                    <span class="badge bg-danger sb-label" style="font-size:10px;margin-right:2px;">{{ $teleFollowupReminderCount }}</span>
                @endif
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="telecallerFollowupMenu" class="collapse sb-submenu {{ $teleFollowupMenuActive ? 'show' : '' }}">
                <a href="{{ route('telecaller.followups.today') }}"     onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.followups.today')     ? 'active' : '' }}">Today</a>
                <a href="{{ route('telecaller.followups.overdue') }}"   onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.followups.overdue')   ? 'active' : '' }}">Overdue</a>
                <a href="{{ route('telecaller.followups.upcoming') }}"  onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.followups.upcoming')  ? 'active' : '' }}">Upcoming</a>
                <a href="{{ route('telecaller.followups.completed') }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.followups.completed') ? 'active' : '' }}">Completed</a>
            </div>

            <div class="sb-section">Analytics</div>

            <button class="sb-item {{ $telePerformanceMenuActive ? 'active' : '' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#telecallerPerformanceMenu"
                aria-expanded="{{ $telePerformanceMenuActive ? 'true' : 'false' }}">
                <div class="sb-icon-wrap"><span class="material-icons">trending_up</span></div>
                <span class="sb-label" style="flex:1;text-align:left;">My Performance</span>
                <span class="material-icons sb-chevron">expand_more</span>
            </button>
            <div id="telecallerPerformanceMenu" class="collapse sb-submenu {{ $telePerformanceMenuActive ? 'show' : '' }}">
                <a href="{{ route('telecaller.performance.daily') }}"   onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.performance.daily')   ? 'active' : '' }}">Daily</a>
                <a href="{{ route('telecaller.performance.weekly') }}"  onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.performance.weekly')  ? 'active' : '' }}">Weekly</a>
                <a href="{{ route('telecaller.performance.monthly') }}" onclick="inertiaVisit(event, this.href)" class="sb-subitem {{ request()->routeIs('telecaller.performance.monthly') ? 'active' : '' }}">Monthly</a>
            </div>
        @endif

    </nav>

    {{-- Footer: user avatar + profile + logout --}}
    <div class="sb-footer">
        <div class="sb-user" onclick="toggleUserMenu()">
            <div class="sb-avatar">
                @php $initials = strtoupper(substr(auth()->user()->name, 0, 1)); @endphp
                {{ $initials }}
            </div>
            <div class="sb-user-info sb-label">
                <p>{{ auth()->user()->name }}</p>
                <small>
                    <span class="material-icons" style="font-size:10px;vertical-align:middle;">
                        @if(auth()->user()->role === 'admin') admin_panel_settings
                        @elseif(auth()->user()->role === 'manager') manage_accounts
                        @else headset_mic
                        @endif
                    </span>
                    {{ ucfirst(auth()->user()->role) }}
                </small>
            </div>
            <form method="POST" action="{{ route('logout') }}" id="sidebar-logout-form"
                  style="margin:0;flex-shrink:0;" onclick="event.stopPropagation()">
                @csrf
                <button type="submit" class="sb-logout-btn sb-label" title="Logout">
                    <span class="material-icons">logout</span>
                </button>
            </form>
        </div>

        <div id="sidebarUserMenu"
             style="display:none;position:absolute;bottom:70px;left:10px;right:10px;
                    background:#fff;border:1px solid #e2e8f0;border-radius:12px;
                    box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:9999;overflow:hidden;">
            <a href="{{ route('password.change') }}"
               class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none"
               style="color:#0f172a;font-size:13px;font-weight:500;transition:background .15s;"
               onmouseover="this.style.background='#f6f7f8'"
               onmouseout="this.style.background='transparent'">
                <span class="material-icons" style="font-size:18px;color:#6366f1;">lock_reset</span>
                Change Password
            </a>
        </div>
    </div>
</aside>

<script>
function toggleUserMenu() {
    var menu = document.getElementById('sidebarUserMenu');
    if (menu) menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

function toggleSidebarPin() {
    var sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('expanded');
}

/* Keep legacy toggleSidebar() working for mobile hamburger in header */
function closeSidebar() {
    var sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.remove('show', 'expanded');
}

document.addEventListener('click', function(e) {
    var menu = document.getElementById('sidebarUserMenu');
    if (!menu) return;
    if (!e.target.closest('.sb-user') && !e.target.closest('#sidebarUserMenu')) {
        menu.style.display = 'none';
    }
});

function inertiaVisit(event, url) {
    if (window._inertiaRouter) {
        event.preventDefault();
        window._inertiaRouter.visit(url);
    }
}

function syncSidebarActive(url) {
    try {
        const path = new URL(url, window.location.origin).pathname;
        const nav  = document.querySelector('.sb-nav');
        if (!nav) return;

        nav.querySelectorAll('a.sb-item, button.sb-item, a.sb-subitem').forEach(el => {
            el.classList.remove('active');
        });
        nav.querySelectorAll('.collapse').forEach(el => el.classList.remove('show'));

        let bestLink = null, bestLen = 0;
        nav.querySelectorAll('a.sb-item[href], a.sb-subitem[href]').forEach(a => {
            try {
                const aPath = new URL(a.href, window.location.origin).pathname;
                if (path.startsWith(aPath) && aPath.length > bestLen) {
                    bestLen = aPath.length;
                    bestLink = a;
                }
            } catch (_) {}
        });

        if (bestLink) {
            bestLink.classList.add('active');
            const collapse = bestLink.closest('.collapse');
            if (collapse) {
                collapse.classList.add('show');
                const btn = nav.querySelector(`[data-bs-target="#${collapse.id}"]`);
                if (btn) btn.classList.add('active');
            }
        }
    } catch (_) {}
}

document.addEventListener('DOMContentLoaded', function () {
    syncSidebarActive(window.location.href);
    if (window._inertiaRouter && typeof window._inertiaRouter.on === 'function') {
        window._inertiaRouter.on('navigate', function () {
            syncSidebarActive(window.location.href);
        });
    }
});
</script>
