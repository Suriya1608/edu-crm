<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            @php $siteLogo = \App\Models\Setting::get('site_logo'); @endphp
            @if($siteLogo)
                <img src="{{ asset('storage/' . $siteLogo) }}" alt="Logo" style="width:100%;height:100%;object-fit:contain;border-radius:8px;">
            @else
                <span class="material-icons">school</span>
            @endif
        </div>
        <div class="sidebar-title">
            <h1>{{ \App\Models\Setting::get('site_name', 'Admission CRM') }}</h1>
            <p>{{ ucfirst(auth()->user()->role) }} Panel</p>
        </div>
        <button class="sidebar-close-btn" onclick="closeSidebar()" title="Close menu">
            <span class="material-icons">close</span>
        </button>
    </div>

    <nav class="sidebar-nav">

        {{-- ADMIN MENU --}}
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

            {{-- Dashboard --}}
            <a href="{{ route('admin.dashboard') }}"
                class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span class="material-icons">dashboard</span>
                <span>Dashboard</span>
            </a>

            {{-- ── People ── --}}
            <div class="nav-section-label">People</div>

            <button class="nav-item w-100 border-0 {{ $adminUsersActive ? 'active' : 'bg-transparent' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminUsersMenu"
                aria-expanded="{{ $adminUsersActive ? 'true' : 'false' }}" aria-controls="adminUsersMenu">
                <span class="material-icons">group</span>
                <span class="flex-grow-1 text-start">User Management</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="adminUsersMenu" class="collapse {{ $adminUsersActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('admin.users.admins') }}"
                    class="nav-item {{ request()->routeIs('admin.users.admins') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Admin Users</a>
                <a href="{{ route('admin.users.managers') }}"
                    class="nav-item {{ request()->routeIs('admin.users.managers') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Managers</a>
                <a href="{{ route('admin.users.telecallers') }}"
                    class="nav-item {{ request()->routeIs('admin.users.telecallers') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Telecallers</a>
            </div>

            <button class="nav-item w-100 border-0 {{ $adminLeadsActive ? 'active' : 'bg-transparent' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminLeadsMenu"
                aria-expanded="{{ $adminLeadsActive ? 'true' : 'false' }}" aria-controls="adminLeadsMenu">
                <span class="material-icons">person_add</span>
                <span class="flex-grow-1 text-start">Lead Management</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="adminLeadsMenu" class="collapse {{ $adminLeadsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('admin.leads.all') }}"
                    class="nav-item {{ request()->routeIs('admin.leads.all') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">All Leads</a>
                <a href="{{ route('admin.leads.unassigned') }}"
                    class="nav-item {{ request()->routeIs('admin.leads.unassigned') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Unassigned</a>
                <a href="{{ route('admin.leads.assigned') }}"
                    class="nav-item {{ request()->routeIs('admin.leads.assigned') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Assigned</a>
                <a href="{{ route('admin.leads.converted') }}"
                    class="nav-item {{ request()->routeIs('admin.leads.converted') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Converted</a>
                <a href="{{ route('admin.leads.lost') }}"
                    class="nav-item {{ request()->routeIs('admin.leads.lost') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Lost</a>
                <a href="{{ route('admin.leads.duplicates') }}"
                    class="nav-item {{ request()->routeIs('admin.leads.duplicates') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Duplicates</a>
            </div>

            {{-- ── Outreach ── --}}
            <div class="nav-section-label">Outreach</div>

            <button class="nav-item w-100 border-0 {{ $adminCampaignsActive ? 'active' : 'bg-transparent' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminCampaignsMenu"
                aria-expanded="{{ $adminCampaignsActive ? 'true' : 'false' }}" aria-controls="adminCampaignsMenu">
                <span class="material-icons">insights</span>
                <span class="flex-grow-1 text-start">Campaigns</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="adminCampaignsMenu" class="collapse {{ $adminCampaignsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('admin.campaigns.performance') }}"
                    class="nav-item {{ request()->routeIs('admin.campaigns.performance') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Performance</a>
                <a href="{{ route('admin.campaigns.contacts') }}"
                    class="nav-item {{ request()->routeIs('admin.campaigns.contacts') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">All Contacts</a>
            </div>

            <a href="{{ route('admin.marketing.social.media') }}"
                class="nav-item {{ request()->routeIs('admin.marketing.*') ? 'active' : '' }}">
                <span class="material-icons">share</span>
                <span>Social Media</span>
            </a>

            <button class="nav-item w-100 border-0 {{ $adminEmailCampActive ? 'active' : 'bg-transparent' }}"
                type="button" data-bs-toggle="collapse" data-bs-target="#adminEmailMenu"
                aria-expanded="{{ $adminEmailCampActive ? 'true' : 'false' }}" aria-controls="adminEmailMenu">
                <span class="material-icons">mark_email_read</span>
                <span class="flex-grow-1 text-start">Email Marketing</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="adminEmailMenu" class="collapse {{ $adminEmailCampActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('admin.email-campaigns.index') }}"
                    class="nav-item {{ request()->routeIs('admin.email-campaigns*') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Campaigns</a>
                <a href="{{ route('admin.email-templates.index') }}"
                    class="nav-item {{ request()->routeIs('admin.email-templates*') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Templates</a>
            </div>

            {{-- ── Analytics ── --}}
            <div class="nav-section-label">Analytics</div>

            <button class="nav-item w-100 border-0 {{ $adminReportsActive ? 'active' : 'bg-transparent' }}"
                type="button" data-bs-toggle="collapse" data-bs-target="#adminReportsMenu"
                aria-expanded="{{ $adminReportsActive ? 'true' : 'false' }}" aria-controls="adminReportsMenu">
                <span class="material-icons">bar_chart</span>
                <span class="flex-grow-1 text-start">Reports</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="adminReportsMenu" class="collapse {{ $adminReportsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('admin.reports.telecaller-performance') }}"
                    class="nav-item {{ request()->routeIs('admin.reports.telecaller-performance') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Telecaller Performance</a>
                <a href="{{ route('admin.reports.manager-performance') }}"
                    class="nav-item {{ request()->routeIs('admin.reports.manager-performance') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Manager Performance</a>
                <a href="{{ route('admin.reports.conversion') }}"
                    class="nav-item {{ request()->routeIs('admin.reports.conversion') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Conversion Report</a>
                <a href="{{ route('admin.reports.lead-source') }}"
                    class="nav-item {{ request()->routeIs('admin.reports.lead-source') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Lead Source</a>
                <a href="{{ route('admin.reports.period') }}"
                    class="nav-item {{ request()->routeIs('admin.reports.period') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Daily / Weekly / Monthly</a>
                <a href="{{ route('admin.reports.call-efficiency') }}"
                    class="nav-item {{ request()->routeIs('admin.reports.call-efficiency') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Call Efficiency</a>
                <a href="{{ route('admin.reports.response-time') }}"
                    class="nav-item {{ request()->routeIs('admin.reports.response-time') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Response Time</a>
            </div>

            <button class="nav-item w-100 border-0 {{ $adminAutomationActive ? 'active' : 'bg-transparent' }}"
                type="button" data-bs-toggle="collapse" data-bs-target="#adminAutomationMenu"
                aria-expanded="{{ $adminAutomationActive ? 'true' : 'false' }}" aria-controls="adminAutomationMenu">
                <span class="material-icons">auto_fix_high</span>
                <span class="flex-grow-1 text-start">Automation</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="adminAutomationMenu" class="collapse {{ $adminAutomationActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('admin.automation.lead-assignment') }}"
                    class="nav-item {{ request()->routeIs('admin.automation.lead-assignment') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Lead Assignment Rules</a>
                <a href="{{ route('admin.automation.followup-reminders') }}"
                    class="nav-item {{ request()->routeIs('admin.automation.followup-reminders') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Follow-up Reminders</a>
                <a href="{{ route('admin.automation.escalation') }}"
                    class="nav-item {{ request()->routeIs('admin.automation.escalation') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Escalation Rules</a>
            </div>

            {{-- ── Master Data ── --}}
            <div class="nav-section-label">Master Data</div>

            <a href="{{ route('admin.courses.index') }}"
                class="nav-item {{ request()->routeIs('admin.courses*') ? 'active' : '' }}">
                <span class="material-icons">school</span>
                <span>Courses</span>
            </a>

            <a href="{{ route('admin.documents') }}"
                class="nav-item {{ request()->routeIs('admin.documents*') ? 'active' : '' }}">
                <span class="material-icons">folder_open</span>
                <span>Documents</span>
            </a>

            {{-- ── System ── --}}
            <div class="nav-section-label">System</div>

            <button class="nav-item w-100 border-0 {{ $adminSettingsActive ? 'active' : 'bg-transparent' }}"
                type="button" data-bs-toggle="collapse" data-bs-target="#adminSettingsMenu"
                aria-expanded="{{ $adminSettingsActive ? 'true' : 'false' }}" aria-controls="adminSettingsMenu">
                <span class="material-icons">settings</span>
                <span class="flex-grow-1 text-start">Settings</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="adminSettingsMenu" class="collapse {{ $adminSettingsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('admin.settings.general') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.general') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">General</a>
                <a href="{{ route('admin.settings.smtp') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.smtp') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">SMTP Settings</a>
                <a href="{{ route('admin.settings.sms') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.sms') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">SMS Settings</a>
                <a href="{{ route('admin.settings.whatsapp') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.whatsapp') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Meta WhatsApp</a>
                <a href="{{ route('admin.settings.instagram') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.instagram') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Instagram</a>
                <a href="{{ route('admin.settings.business-hours') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.business-hours') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Business Hours</a>
                <a href="{{ route('admin.settings.working-days') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.working-days') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Working Days</a>
                <a href="{{ route('admin.settings.timezone') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.timezone') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Timezone</a>
                <a href="{{ route('admin.settings.default-lead-status') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.default-lead-status') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Default Lead Status</a>
                <a href="{{ route('admin.settings.notifications') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.notifications') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Notifications</a>
                <a href="{{ route('admin.settings.pages') }}"
                    class="nav-item {{ request()->routeIs('admin.settings.pages') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Pages</a>
            </div>
        @endif


        {{-- MANAGER MENU --}}
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

            {{-- Dashboard --}}
            <a href="{{ route('manager.dashboard') }}"
                onclick="inertiaVisit(event, this.href)"
                class="nav-item {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
                <span class="material-icons">dashboard</span>
                <span>Dashboard</span>
            </a>

            {{-- ── People ── --}}
            <div class="nav-section-label">People</div>

            <button class="nav-item w-100 border-0 {{ $mgrLeadsActive ? 'active' : 'bg-transparent' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrLeadsMenu"
                aria-expanded="{{ $mgrLeadsActive ? 'true' : 'false' }}" aria-controls="mgrLeadsMenu">
                <span class="material-icons">person_add</span>
                <span class="flex-grow-1 text-start">Leads</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="mgrLeadsMenu" class="collapse {{ $mgrLeadsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('manager.leads') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.leads') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">All Leads</a>
                <a href="{{ route('manager.leads.duplicates') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.leads.duplicates') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Duplicate Leads</a>
            </div>

            <a href="{{ route('manager.telecallers') }}"
                onclick="inertiaVisit(event, this.href)"
                class="nav-item {{ request()->routeIs('manager.telecallers*') ? 'active' : '' }}">
                <span class="material-icons">headset_mic</span>
                <span>Telecallers</span>
            </a>

            {{-- ── Outreach ── --}}
            <div class="nav-section-label">Outreach</div>

            <button class="nav-item w-100 border-0 {{ $mgrCampaignsActive ? 'active' : 'bg-transparent' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrCampaignsMenu"
                aria-expanded="{{ $mgrCampaignsActive ? 'true' : 'false' }}" aria-controls="mgrCampaignsMenu">
                <span class="material-icons">campaign</span>
                <span class="flex-grow-1 text-start">Campaigns</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="mgrCampaignsMenu" class="collapse {{ $mgrCampaignsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('manager.campaigns.index') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.campaigns.index') || request()->routeIs('manager.campaigns.show') || request()->routeIs('manager.campaigns.create') || request()->routeIs('manager.campaigns.contact') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">All Campaigns</a>
                <a href="{{ route('manager.campaigns.performance') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.campaigns.performance') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Performance</a>
            </div>

            <a href="{{ route('manager.email-campaigns.index') }}"
                onclick="inertiaVisit(event, this.href)"
                class="nav-item {{ $mgrEmailCampActive ? 'active' : '' }}">
                <span class="material-icons">mark_email_read</span>
                <span>Email Campaigns</span>
            </a>

            <a href="{{ route('manager.whatsapp.hub') }}"
                onclick="inertiaVisit(event, this.href)"
                class="nav-item {{ request()->routeIs('manager.whatsapp.*') ? 'active' : '' }}">
                <span class="material-icons">chat</span>
                <span>WhatsApp Chat</span>
            </a>

            {{-- ── Activity ── --}}
            <div class="nav-section-label">Activity</div>

            <button class="nav-item w-100 border-0 {{ $mgrFollowupsActive ? 'active' : 'bg-transparent' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrFollowupMenu"
                aria-expanded="{{ $mgrFollowupsActive ? 'true' : 'false' }}" aria-controls="mgrFollowupMenu">
                <span class="material-icons">event_note</span>
                <span class="flex-grow-1 text-start">Follow-up Management</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="mgrFollowupMenu" class="collapse {{ $mgrFollowupsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('manager.followups.today') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.followups.today') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Today</a>
                <a href="{{ route('manager.followups.overdue') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.followups.overdue') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Overdue</a>
                <a href="{{ route('manager.followups.upcoming') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.followups.upcoming') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Upcoming</a>
                <a href="{{ route('manager.followups.missed') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.followups.missed') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Missed by Telecaller</a>
            </div>

            <button class="nav-item w-100 border-0 {{ $mgrCallLogsActive ? 'active' : 'bg-transparent' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrCallLogsMenu"
                aria-expanded="{{ $mgrCallLogsActive ? 'true' : 'false' }}" aria-controls="mgrCallLogsMenu">
                <span class="material-icons">call</span>
                <span class="flex-grow-1 text-start">Call Logs</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="mgrCallLogsMenu" class="collapse {{ $mgrCallLogsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('manager.call-logs.index', ['scope' => 'all']) }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ $mgrCallLogsActive && $mgrCallScope === 'all' ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">All Calls</a>
                <a href="{{ route('manager.call-logs.index', ['scope' => 'inbound']) }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ $mgrCallLogsActive && $mgrCallScope === 'inbound' ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Inbound</a>
                <a href="{{ route('manager.call-logs.index', ['scope' => 'outbound']) }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ $mgrCallLogsActive && $mgrCallScope === 'outbound' ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Outbound</a>
                <a href="{{ route('manager.call-logs.index', ['scope' => 'missed']) }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ $mgrCallLogsActive && $mgrCallScope === 'missed' ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Missed</a>
            </div>

            {{-- ── Analytics ── --}}
            <div class="nav-section-label">Analytics</div>

            <button class="nav-item w-100 border-0 {{ $mgrReportsActive ? 'active' : 'bg-transparent' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#mgrReportsMenu"
                aria-expanded="{{ $mgrReportsActive ? 'true' : 'false' }}" aria-controls="mgrReportsMenu">
                <span class="material-icons">bar_chart</span>
                <span class="flex-grow-1 text-start">Reports & Analytics</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="mgrReportsMenu" class="collapse {{ $mgrReportsActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('manager.reports.home') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.reports.home') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Overview</a>
                <a href="{{ route('manager.reports.telecaller-performance') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.reports.telecaller-performance') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Telecaller Performance</a>
                <a href="{{ route('manager.reports.conversion') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.reports.conversion') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Conversion Report</a>
                <a href="{{ route('manager.reports.source-performance') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.reports.source-performance') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Source Performance</a>
                <a href="{{ route('manager.reports.period') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.reports.period') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Daily / Weekly / Monthly</a>
                <a href="{{ route('manager.reports.response-time') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.reports.response-time') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Lead Response Time</a>
                <a href="{{ route('manager.reports.call-efficiency') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('manager.reports.call-efficiency') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Call Efficiency</a>
            </div>
        @endif


        {{-- TELECALLER MENU --}}
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

            {{--
                Inertia-enabled links use inertiaVisit() so the browser never does a full
                page reload — the SIP iframe and its WebRTC session survive navigation.
                Links for pages not yet migrated to Inertia are left as plain <a href>.
            --}}

            {{-- Dashboard (Inertia) --}}
            <a href="{{ route('telecaller.dashboard') }}"
                onclick="inertiaVisit(event, this.href)"
                class="nav-item {{ request()->routeIs('telecaller.dashboard') ? 'active' : '' }}">
                <span class="material-icons">dashboard</span>
                <span>Dashboard</span>
            </a>

            {{-- ── Work ── --}}
            <div class="nav-section-label">Work</div>

            {{-- My Leads (Inertia) --}}
            <a href="{{ route('telecaller.leads') }}"
                onclick="inertiaVisit(event, this.href)"
                class="nav-item {{ request()->routeIs('telecaller.leads*') ? 'active' : '' }}">
                <span class="material-icons">person_add</span>
                <span>My Leads</span>
            </a>

            {{-- My Campaigns (Inertia) --}}
            <a href="{{ route('telecaller.campaigns.index') }}"
                onclick="inertiaVisit(event, this.href)"
                class="nav-item {{ request()->routeIs('telecaller.campaigns*') ? 'active' : '' }}">
                <span class="material-icons">campaign</span>
                <span>My Campaigns</span>
            </a>

            {{-- ── Messaging ── --}}
            <div class="nav-section-label">Messaging</div>

            {{-- WhatsApp Chat (Inertia) --}}
            <a href="{{ route('telecaller.whatsapp.hub') }}"
                onclick="inertiaVisit(event, this.href)"
                class="nav-item {{ request()->routeIs('telecaller.whatsapp.*') ? 'active' : '' }}">
                <span class="material-icons">chat</span>
                <span>WhatsApp Chat</span>
            </a>

            {{-- ── Activity ── --}}
            <div class="nav-section-label">Activity</div>

            {{-- Call Management (Inertia) --}}
            <button class="nav-item w-100 border-0 {{ $teleCallsMenuActive ? 'active' : 'bg-transparent' }}"
                type="button" data-bs-toggle="collapse" data-bs-target="#telecallerCallsMenu"
                aria-expanded="{{ $teleCallsMenuActive ? 'true' : 'false' }}"
                aria-controls="telecallerCallsMenu">
                <span class="material-icons">call</span>
                <span class="flex-grow-1 text-start">Call Management</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="telecallerCallsMenu" class="collapse {{ $teleCallsMenuActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('telecaller.calls.outbound') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.calls.outbound') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Outbound Calls</a>
                <a href="{{ route('telecaller.calls.inbound') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.calls.inbound') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Inbound Calls</a>
                <a href="{{ route('telecaller.calls.missed') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.calls.missed') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Missed Calls</a>
                <a href="{{ route('telecaller.calls.history') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.calls.history') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Call History</a>
            </div>

            {{-- Follow-ups (Inertia) --}}
            <button class="nav-item w-100 border-0 {{ $teleFollowupMenuActive ? 'active' : 'bg-transparent' }}"
                type="button" data-bs-toggle="collapse" data-bs-target="#telecallerFollowupMenu"
                aria-expanded="{{ $teleFollowupMenuActive ? 'true' : 'false' }}" aria-controls="telecallerFollowupMenu">
                <span class="material-icons">event_note</span>
                <span class="flex-grow-1 text-start">Follow-ups</span>
                @if ($teleFollowupReminderCount > 0)
                    <span class="badge bg-danger ms-1">{{ $teleFollowupReminderCount }}</span>
                @endif
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="telecallerFollowupMenu" class="collapse {{ $teleFollowupMenuActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('telecaller.followups.today') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.followups.today') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Today</a>
                <a href="{{ route('telecaller.followups.overdue') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.followups.overdue') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Overdue</a>
                <a href="{{ route('telecaller.followups.upcoming') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.followups.upcoming') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Upcoming</a>
                <a href="{{ route('telecaller.followups.completed') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.followups.completed') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Completed</a>
            </div>

            {{-- ── Analytics ── --}}
            <div class="nav-section-label">Analytics</div>

            {{-- My Performance (plain — not yet on Inertia) --}}
            <button class="nav-item w-100 border-0 {{ $telePerformanceMenuActive ? 'active' : 'bg-transparent' }}"
                type="button" data-bs-toggle="collapse" data-bs-target="#telecallerPerformanceMenu"
                aria-expanded="{{ $telePerformanceMenuActive ? 'true' : 'false' }}"
                aria-controls="telecallerPerformanceMenu">
                <span class="material-icons">trending_up</span>
                <span class="flex-grow-1 text-start">My Performance</span>
                <span class="material-icons" style="font-size:18px;">expand_more</span>
            </button>
            <div id="telecallerPerformanceMenu" class="collapse {{ $telePerformanceMenuActive ? 'show' : '' }}"
                style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
                <a href="{{ route('telecaller.performance.daily') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.performance.daily') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Daily</a>
                <a href="{{ route('telecaller.performance.weekly') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.performance.weekly') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Weekly</a>
                <a href="{{ route('telecaller.performance.monthly') }}"
                    onclick="inertiaVisit(event, this.href)"
                    class="nav-item {{ request()->routeIs('telecaller.performance.monthly') ? 'active' : '' }}"
                    style="padding:8px 12px 8px 36px;font-size:13px;">Monthly</a>
            </div>
        @endif

    </nav>

    <div class="sidebar-footer">
        <div class="user-profile" style="position:relative;">
            <div class="user-avatar" role="button" onclick="toggleUserMenu()" title="Account options" style="cursor:pointer;">
                @php $initials = strtoupper(substr(auth()->user()->name, 0, 1)); @endphp
                <span class="user-avatar-initials">{{ $initials }}</span>
            </div>
            <div class="user-info" style="cursor:pointer;" onclick="toggleUserMenu()">
                <p>{{ auth()->user()->name }}</p>
                <span>
                    <span class="material-icons" style="font-size:10px;vertical-align:middle;">
                        @if(auth()->user()->role === 'admin') admin_panel_settings
                        @elseif(auth()->user()->role === 'manager') manage_accounts
                        @else headset_mic
                        @endif
                    </span>
                    {{ ucfirst(auth()->user()->role) }}
                </span>
            </div>

            <form method="POST" action="{{ route('logout') }}" id="sidebar-logout-form">
                @csrf
                <button type="submit" class="btn btn-link p-0" title="Logout">
                    <span class="material-icons" style="font-size: 20px;">logout</span>
                </button>
            </form>

            {{-- User popup menu --}}
            <div id="sidebarUserMenu" style="display:none;position:absolute;bottom:60px;left:0;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.12);z-index:9999;overflow:hidden;">
                <a href="{{ route('password.change') }}" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none" style="color:#0f172a;font-size:13px;font-weight:500;transition:background .15s;" onmouseover="this.style.background='#f6f7f8'" onmouseout="this.style.background='transparent'">
                    <span class="material-icons" style="font-size:18px;color:#137fec;">lock_reset</span>
                    Change Password
                </a>
            </div>
        </div>
    </div>
</aside>

<script>
function toggleUserMenu() {
    var menu = document.getElementById('sidebarUserMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    var menu = document.getElementById('sidebarUserMenu');
    if (!menu) return;
    if (!e.target.closest('.user-profile')) {
        menu.style.display = 'none';
    }
});

/**
 * inertiaVisit(event, url)
 *
 * Intercepts a link click and delegates to Inertia's router so only the
 * React page component swaps — the outer shell (sidebar, header, TCN iframe)
 * is never destroyed, keeping the SIP/WebRTC connection alive across navigation.
 *
 * Falls back to normal browser navigation if the Inertia router isn't ready yet
 * (e.g. first hard load before inertia-app.jsx has bootstrapped).
 */
function inertiaVisit(event, url) {
    if (window._inertiaRouter) {
        event.preventDefault();
        window._inertiaRouter.visit(url);
    }
    // else: let the default href navigate normally
}

function syncSidebarActive(url) {
    try {
        const path = new URL(url, window.location.origin).pathname;
        const nav = document.querySelector('.sidebar-nav');
        if (!nav) return;

        // Remove active from all plain nav-item anchors and buttons
        nav.querySelectorAll('a.nav-item, button.nav-item').forEach(el => {
            el.classList.remove('active');
            if (el.tagName === 'BUTTON') {
                el.classList.add('bg-transparent');
            }
        });

        // Close all collapse sub-menus
        nav.querySelectorAll('.collapse').forEach(el => el.classList.remove('show'));

        // Find the best matching link
        let bestLink = null;
        let bestLen = 0;
        nav.querySelectorAll('a.nav-item[href]').forEach(a => {
            try {
                const aPath = new URL(a.href, window.location.origin).pathname;
                if (path.startsWith(aPath) && aPath.length > bestLen) {
                    bestLen = aPath.length;
                    bestLink = a;
                }
            } catch (e) {}
        });

        if (bestLink) {
            bestLink.classList.add('active');
            // If it's inside a collapse sub-menu, open the parent collapse and activate its toggle button
            const collapse = bestLink.closest('.collapse');
            if (collapse) {
                collapse.classList.add('show');
                const toggleId = collapse.id;
                const btn = nav.querySelector(`[data-bs-target="#${toggleId}"]`);
                if (btn) {
                    btn.classList.add('active');
                    btn.classList.remove('bg-transparent');
                }
            }
        }
    } catch (e) {}
}

// Sync on hard load and on every Inertia navigation
document.addEventListener('DOMContentLoaded', function () {
    syncSidebarActive(window.location.href);
    // Keep active state in sync across Inertia SPA navigations
    if (window._inertiaRouter && typeof window._inertiaRouter.on === 'function') {
        window._inertiaRouter.on('navigate', function () {
            syncSidebarActive(window.location.href);
        });
    }
});
</script>
