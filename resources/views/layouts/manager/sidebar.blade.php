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
            <p>Manager Panel</p>
        </div>
    </div>

    <nav class="sidebar-nav">
        @php
            $leadsActive     = request()->routeIs('manager.leads*');
            $campaignsActive = request()->routeIs('manager.campaigns.*');
            $emailCampActive = request()->routeIs('manager.email-campaigns*');
            $reportsActive   = request()->routeIs('manager.reports.*');
            $followupsActive = request()->routeIs('manager.followups.*');
            $callLogsActive  = request()->routeIs('manager.call-logs.*');
            $callScope       = request('scope', 'all');
        @endphp

        {{-- Dashboard --}}
        <a href="{{ route('manager.dashboard') }}"
            class="nav-item {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
            <span class="material-icons">dashboard</span>
            <span>Dashboard</span>
        </a>

        {{-- ── People ── --}}
        <div class="nav-section-label">People</div>

        <button class="nav-item w-100 border-0 {{ $leadsActive ? 'active' : 'bg-transparent' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#managerLeadsMenu"
            aria-expanded="{{ $leadsActive ? 'true' : 'false' }}" aria-controls="managerLeadsMenu">
            <span class="material-icons">person_add</span>
            <span class="flex-grow-1 text-start">Leads</span>
            <span class="material-icons" style="font-size:18px;">expand_more</span>
        </button>
        <div id="managerLeadsMenu" class="collapse {{ $leadsActive ? 'show' : '' }}"
            style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
            <a href="{{ route('manager.leads') }}"
                class="nav-item {{ request()->routeIs('manager.leads') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">All Leads</a>
            <a href="{{ route('manager.leads.duplicates') }}"
                class="nav-item {{ request()->routeIs('manager.leads.duplicates') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Duplicate Leads</a>
        </div>

        <a href="{{ route('manager.telecallers') }}"
            class="nav-item {{ request()->routeIs('manager.telecallers*') ? 'active' : '' }}">
            <span class="material-icons">headset_mic</span>
            <span>Telecallers</span>
        </a>

        {{-- ── Outreach ── --}}
        <div class="nav-section-label">Outreach</div>

        <button class="nav-item w-100 border-0 {{ $campaignsActive ? 'active' : 'bg-transparent' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#managerCampaignsMenu"
            aria-expanded="{{ $campaignsActive ? 'true' : 'false' }}" aria-controls="managerCampaignsMenu">
            <span class="material-icons">campaign</span>
            <span class="flex-grow-1 text-start">Campaigns</span>
            <span class="material-icons" style="font-size:18px;">expand_more</span>
        </button>
        <div id="managerCampaignsMenu" class="collapse {{ $campaignsActive ? 'show' : '' }}"
            style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
            <a href="{{ route('manager.campaigns.index') }}"
                class="nav-item {{ request()->routeIs('manager.campaigns.index') || request()->routeIs('manager.campaigns.show') || request()->routeIs('manager.campaigns.create') || request()->routeIs('manager.campaigns.contact') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">All Campaigns</a>
            <a href="{{ route('manager.campaigns.performance') }}"
                class="nav-item {{ request()->routeIs('manager.campaigns.performance') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Performance</a>
        </div>

        <a href="{{ route('manager.email-campaigns.index') }}"
            class="nav-item {{ $emailCampActive ? 'active' : '' }}">
            <span class="material-icons">mark_email_read</span>
            <span>Email Campaigns</span>
        </a>

        <a href="{{ route('manager.whatsapp.hub') }}"
            class="nav-item {{ request()->routeIs('manager.whatsapp.*') ? 'active' : '' }}">
            <span class="material-icons">chat</span>
            <span>WhatsApp Chat</span>
        </a>

        <a href="{{ route('manager.instagram.index') }}"
            class="nav-item {{ request()->routeIs('manager.instagram.*') ? 'active' : '' }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                style="flex-shrink:0;">
                <defs>
                    <linearGradient id="igGradMgr" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#f09433"/>
                        <stop offset="25%" style="stop-color:#e6683c"/>
                        <stop offset="50%" style="stop-color:#dc2743"/>
                        <stop offset="75%" style="stop-color:#cc2366"/>
                        <stop offset="100%" style="stop-color:#bc1888"/>
                    </linearGradient>
                </defs>
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="url(#igGradMgr)"/>
                <circle cx="12" cy="12" r="4.5" fill="none" stroke="white" stroke-width="1.8"/>
                <circle cx="17.5" cy="6.5" r="1.2" fill="white"/>
            </svg>
            <span>Instagram Chat</span>
        </a>

        {{-- ── Activity ── --}}
        <div class="nav-section-label">Activity</div>

        <button class="nav-item w-100 border-0 {{ $followupsActive ? 'active' : 'bg-transparent' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#managerFollowupMenu"
            aria-expanded="{{ $followupsActive ? 'true' : 'false' }}" aria-controls="managerFollowupMenu">
            <span class="material-icons">event_note</span>
            <span class="flex-grow-1 text-start">Follow-up Management</span>
            <span class="material-icons" style="font-size:18px;">expand_more</span>
        </button>
        <div id="managerFollowupMenu" class="collapse {{ $followupsActive ? 'show' : '' }}"
            style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
            <a href="{{ route('manager.followups.today') }}"
                class="nav-item {{ request()->routeIs('manager.followups.today') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Today</a>
            <a href="{{ route('manager.followups.overdue') }}"
                class="nav-item {{ request()->routeIs('manager.followups.overdue') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Overdue</a>
            <a href="{{ route('manager.followups.upcoming') }}"
                class="nav-item {{ request()->routeIs('manager.followups.upcoming') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Upcoming</a>
            <a href="{{ route('manager.followups.missed') }}"
                class="nav-item {{ request()->routeIs('manager.followups.missed') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Missed by Telecaller</a>
        </div>

        <button class="nav-item w-100 border-0 {{ $callLogsActive ? 'active' : 'bg-transparent' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#managerCallLogsMenu"
            aria-expanded="{{ $callLogsActive ? 'true' : 'false' }}" aria-controls="managerCallLogsMenu">
            <span class="material-icons">call</span>
            <span class="flex-grow-1 text-start">Call Logs</span>
            <span class="material-icons" style="font-size:18px;">expand_more</span>
        </button>
        <div id="managerCallLogsMenu" class="collapse {{ $callLogsActive ? 'show' : '' }}"
            style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
            <a href="{{ route('manager.call-logs.index', ['scope' => 'all']) }}"
                class="nav-item {{ $callLogsActive && $callScope === 'all' ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">All Calls</a>
            <a href="{{ route('manager.call-logs.index', ['scope' => 'inbound']) }}"
                class="nav-item {{ $callLogsActive && $callScope === 'inbound' ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Inbound</a>
            <a href="{{ route('manager.call-logs.index', ['scope' => 'outbound']) }}"
                class="nav-item {{ $callLogsActive && $callScope === 'outbound' ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Outbound</a>
            <a href="{{ route('manager.call-logs.index', ['scope' => 'missed']) }}"
                class="nav-item {{ $callLogsActive && $callScope === 'missed' ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Missed</a>
        </div>

        {{-- ── Analytics ── --}}
        <div class="nav-section-label">Analytics</div>

        <button class="nav-item w-100 border-0 {{ $reportsActive ? 'active' : 'bg-transparent' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#managerReportsMenu"
            aria-expanded="{{ $reportsActive ? 'true' : 'false' }}" aria-controls="managerReportsMenu">
            <span class="material-icons">bar_chart</span>
            <span class="flex-grow-1 text-start">Reports & Analytics</span>
            <span class="material-icons" style="font-size:18px;">expand_more</span>
        </button>
        <div id="managerReportsMenu" class="collapse {{ $reportsActive ? 'show' : '' }}"
            style="padding-left:12px;margin-top:-2px;margin-bottom:8px;">
            <a href="{{ route('manager.reports.home') }}"
                class="nav-item {{ request()->routeIs('manager.reports.home') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Overview</a>
            <a href="{{ route('manager.reports.telecaller-performance') }}"
                class="nav-item {{ request()->routeIs('manager.reports.telecaller-performance') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Telecaller Performance</a>
            <a href="{{ route('manager.reports.conversion') }}"
                class="nav-item {{ request()->routeIs('manager.reports.conversion') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Conversion Report</a>
            <a href="{{ route('manager.reports.source-performance') }}"
                class="nav-item {{ request()->routeIs('manager.reports.source-performance') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Source Performance</a>
            <a href="{{ route('manager.reports.period') }}"
                class="nav-item {{ request()->routeIs('manager.reports.period') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Daily / Weekly / Monthly</a>
            <a href="{{ route('manager.reports.response-time') }}"
                class="nav-item {{ request()->routeIs('manager.reports.response-time') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Lead Response Time</a>
            <a href="{{ route('manager.reports.call-efficiency') }}"
                class="nav-item {{ request()->routeIs('manager.reports.call-efficiency') ? 'active' : '' }}"
                style="padding:8px 12px 8px 36px;font-size:13px;">Call Efficiency</a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                @php $initials = strtoupper(substr(auth()->user()->name, 0, 1)); @endphp
                <span class="user-avatar-initials">{{ $initials }}</span>
            </div>
            <div class="user-info">
                <p>{{ auth()->user()->name }}</p>
                <span>
                    <span class="material-icons" style="font-size:10px;vertical-align:middle;">manage_accounts</span>
                    Manager
                </span>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-link p-0" title="Logout">
                    <span class="material-icons" style="font-size:20px;">logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>
