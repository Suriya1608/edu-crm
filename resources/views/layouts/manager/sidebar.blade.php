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
        <a href="{{ route('manager.dashboard') }}"
            class="nav-item {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
            <span class="material-icons">dashboard</span>
            <span>Dashboard</span>
        </a>

        <a href="{{ route('manager.leads') }}" class="nav-item {{ request()->routeIs('manager.leads*') ? 'active' : '' }}">
            <span class="material-icons">person_add</span>
            <span>Leads</span>
        </a>

        <a href="{{ route('manager.telecallers') }}"
            class="nav-item {{ request()->routeIs('manager.telecallers*') ? 'active' : '' }}">
            <span class="material-icons">person</span>
            <span>Telecallers</span>
        </a>

        <a href="{{ route('manager.whatsapp.hub') }}"
            class="nav-item {{ request()->routeIs('manager.whatsapp.*') ? 'active' : '' }}">
            <span class="material-icons">chat</span>
            <span>WhatsApp Chat</span>
        </a>

        <a href="{{ route('manager.campaigns.index') }}"
            class="nav-item {{ request()->routeIs('manager.campaigns.index') || request()->routeIs('manager.campaigns.show') || request()->routeIs('manager.campaigns.contact') || request()->routeIs('manager.campaigns.create') ? 'active' : '' }}">
            <span class="material-icons">campaign</span>
            <span>Campaigns</span>
        </a>

        <a href="{{ route('manager.campaigns.performance') }}"
            class="nav-item {{ request()->routeIs('manager.campaigns.performance') ? 'active' : '' }}">
            <span class="material-icons">insights</span>
            <span>Campaign Performance</span>
        </a>

        @php
            $reportsActive = request()->routeIs('manager.reports.*');
        @endphp
        <button class="nav-item w-100 border-0 {{ $reportsActive ? 'active' : 'bg-transparent' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#managerReportsMenu"
            aria-expanded="{{ $reportsActive ? 'true' : 'false' }}" aria-controls="managerReportsMenu">
            <span class="material-icons">bar_chart</span>
            <span class="flex-grow-1 text-start">Reports & Analytics</span>
            <span class="material-icons" style="font-size: 18px;">expand_more</span>
        </button>
        <div id="managerReportsMenu" class="collapse {{ $reportsActive ? 'show' : '' }}"
            style="padding-left: 12px; margin-top: -2px; margin-bottom: 8px;">
            <a href="{{ route('manager.reports.home') }}"
                class="nav-item {{ request()->routeIs('manager.reports.home') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">Home (Dashboard)</a>
            <a href="{{ route('manager.reports.telecaller-performance') }}"
                class="nav-item {{ request()->routeIs('manager.reports.telecaller-performance') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">Telecaller Performance</a>
            <a href="{{ route('manager.reports.conversion') }}"
                class="nav-item {{ request()->routeIs('manager.reports.conversion') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">Conversion Report</a>
            <a href="{{ route('manager.reports.source-performance') }}"
                class="nav-item {{ request()->routeIs('manager.reports.source-performance') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">Source Performance</a>
            <a href="{{ route('manager.reports.period') }}"
                class="nav-item {{ request()->routeIs('manager.reports.period') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">Daily / Weekly / Monthly</a>
            <a href="{{ route('manager.reports.response-time') }}"
                class="nav-item {{ request()->routeIs('manager.reports.response-time') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">Lead Response Time</a>
            <a href="{{ route('manager.reports.call-efficiency') }}"
                class="nav-item {{ request()->routeIs('manager.reports.call-efficiency') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">Call Efficiency</a>
        </div>

        @php
            $followupsActive = request()->routeIs('manager.followups.*');
        @endphp

        <button class="nav-item w-100 border-0 {{ $followupsActive ? 'active' : 'bg-transparent' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#managerFollowupMenu"
            aria-expanded="{{ $followupsActive ? 'true' : 'false' }}" aria-controls="managerFollowupMenu">
            <span class="material-icons">event_note</span>
            <span class="flex-grow-1 text-start">Follow-up Management</span>
            <span class="material-icons" style="font-size: 18px;">expand_more</span>
        </button>

        <div id="managerFollowupMenu" class="collapse {{ $followupsActive ? 'show' : '' }}"
            style="padding-left: 12px; margin-top: -2px; margin-bottom: 8px;">
            <a href="{{ route('manager.followups.today') }}"
                class="nav-item {{ request()->routeIs('manager.followups.today') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">
                Today Follow-ups
            </a>
            <a href="{{ route('manager.followups.overdue') }}"
                class="nav-item {{ request()->routeIs('manager.followups.overdue') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">
                Overdue Follow-ups
            </a>
            <a href="{{ route('manager.followups.upcoming') }}"
                class="nav-item {{ request()->routeIs('manager.followups.upcoming') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">
                Upcoming Follow-ups
            </a>
            <a href="{{ route('manager.followups.missed') }}"
                class="nav-item {{ request()->routeIs('manager.followups.missed') ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">
                Missed by Telecaller
            </a>
        </div>

        @php
            $callScope = request('scope', 'all');
            $callLogsActive = request()->routeIs('manager.call-logs.*');
        @endphp

        <button class="nav-item w-100 border-0 {{ $callLogsActive ? 'active' : 'bg-transparent' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#managerCallLogsMenu"
            aria-expanded="{{ $callLogsActive ? 'true' : 'false' }}" aria-controls="managerCallLogsMenu">
            <span class="material-icons">call</span>
            <span class="flex-grow-1 text-start">Call Logs</span>
            <span class="material-icons" style="font-size: 18px;">expand_more</span>
        </button>

        <div id="managerCallLogsMenu" class="collapse {{ $callLogsActive ? 'show' : '' }}"
            style="padding-left: 12px; margin-top: -2px; margin-bottom: 8px;">
            <a href="{{ route('manager.call-logs.index', ['scope' => 'all']) }}"
                class="nav-item {{ $callLogsActive && $callScope === 'all' ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">
                All calls
            </a>
            <a href="{{ route('manager.call-logs.index', ['scope' => 'inbound']) }}"
                class="nav-item {{ $callLogsActive && $callScope === 'inbound' ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">
                All inbound calls
            </a>
            <a href="{{ route('manager.call-logs.index', ['scope' => 'outbound']) }}"
                class="nav-item {{ $callLogsActive && $callScope === 'outbound' ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">
                All outbound calls
            </a>
            <a href="{{ route('manager.call-logs.index', ['scope' => 'missed']) }}"
                class="nav-item {{ $callLogsActive && $callScope === 'missed' ? 'active' : '' }}"
                style="padding: 8px 12px 8px 36px; font-size: 13px;">
                Missed calls
            </a>
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
                    <span class="material-icons" style="font-size: 20px;">logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>
