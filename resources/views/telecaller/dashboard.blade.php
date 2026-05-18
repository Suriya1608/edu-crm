@extends('layouts.app')

@section('page_title', 'Dashboard')

@endsection

@section('content')

{{-- ── Hero Greeting Banner ─────────────────────────────────────────────── --}}
<div class="tc-hero-banner mb-4">

    {{-- Decorative blobs --}}
    <div class="tc-blob tc-blob-1"></div>
    <div class="tc-blob tc-blob-2"></div>
    <div class="tc-blob tc-blob-3"></div>

    <div class="tc-hero-inner">

        {{-- Profile Card --}}
        <div class="tc-profile-card">
            <div class="tc-profile-avatar">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div class="tc-profile-name" id="greetingTitle">Good Morning, {{ auth()->user()->name }}</div>
            <div class="tc-profile-role">
                <span class="material-icons" style="font-size:13px;">headset_mic</span>
                Telecaller
            </div>
            <div class="tc-profile-meta-row">
                <div class="tc-profile-meta-item">
                    <div class="tc-profile-meta-val" id="heroAssigned">{{ $totalAssignedLeads }}</div>
                    <div class="tc-profile-meta-key">Leads</div>
                </div>
                <div class="tc-profile-meta-sep"></div>
                <div class="tc-profile-meta-item">
                    <div class="tc-profile-meta-val" id="heroCalls">{{ $totalCallsToday }}</div>
                    <div class="tc-profile-meta-key">Calls</div>
                </div>
                <div class="tc-profile-meta-sep"></div>
                <div class="tc-profile-meta-item">
                    <div class="tc-profile-meta-val" id="heroFollowups">{{ $followupsToday }}</div>
                    <div class="tc-profile-meta-key">Due Today</div>
                </div>
            </div>
        </div>

        {{-- Right: Date + Rings + Alert --}}
        <div class="tc-hero-right">
            <div class="tc-hero-meta">
                <div class="tc-hero-meta-item">
                    <span class="material-icons" style="font-size:14px;">today</span>
                    <span id="greetingDate"></span>
                </div>
                <div class="tc-hero-meta-item">
                    <span class="live-pulse-dot"></span>
                    Live
                    <span class="material-icons" style="font-size:13px;opacity:.7;">sync</span>
                    <span id="lastRefreshed">Just now</span>
                </div>
                @if($followupsToday > 0 || $overdueFollowups > 0)
                <div class="tc-hero-meta-item tc-hero-alert">
                    <span class="material-icons" style="font-size:14px;">notifications_active</span>
                    @if($overdueFollowups > 0)
                        {{ $overdueFollowups }} overdue follow-up{{ $overdueFollowups > 1 ? 's' : '' }}
                    @else
                        {{ $followupsToday }} follow-up{{ $followupsToday > 1 ? 's' : '' }} today
                    @endif
                </div>
                @endif
            </div>

            {{-- Progress rings --}}
            <div class="tc-rings">
                <div class="tc-ring-item">
                    <svg width="80" height="80" viewBox="0 0 80 80">
                        <circle cx="40" cy="40" r="32" fill="none" stroke="rgba(255,255,255,0.10)" stroke-width="6"/>
                        <circle cx="40" cy="40" r="32" fill="none" stroke="#93c5fd" stroke-width="6"
                            stroke-dasharray="201.1"
                            stroke-dashoffset="{{ max(0, 201.1 - (min(1, $totalCallsToday / max(1,20)) * 201.1)) }}"
                            stroke-linecap="round" transform="rotate(-90 40 40)" id="ringCalls"/>
                    </svg>
                    <div class="tc-ring-label">
                        <span class="tc-ring-val" id="ringCallsVal">{{ $totalCallsToday }}</span>
                        <span class="tc-ring-sub">Calls</span>
                    </div>
                </div>
                <div class="tc-ring-item">
                    <svg width="80" height="80" viewBox="0 0 80 80">
                        <circle cx="40" cy="40" r="32" fill="none" stroke="rgba(255,255,255,0.10)" stroke-width="6"/>
                        <circle cx="40" cy="40" r="32" fill="none" stroke="rgba(147,197,253,0.65)" stroke-width="6"
                            stroke-dasharray="201.1"
                            stroke-dashoffset="{{ max(0, 201.1 - (min(1, $followupsToday / max(1,10)) * 201.1)) }}"
                            stroke-linecap="round" transform="rotate(-90 40 40)" id="ringFollowups"/>
                    </svg>
                    <div class="tc-ring-label">
                        <span class="tc-ring-val" id="ringFollowupsVal">{{ $followupsToday }}</span>
                        <span class="tc-ring-sub">Due</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ── Row 1 — Lead & Follow-up Stats ───────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-v2 tc-stat">
            <div class="tc-stat-badge" style="background:linear-gradient(135deg,#253769,#1d2c4e);">
                {{ $totalAssignedLeads > 0 ? min(99, round(($totalAssignedLeads / max(1,50))*100)) : 0 }}%
            </div>
            <div class="d-flex align-items-start justify-content-between mt-1">
                <div>
                    <div class="stat-label">Assigned Leads</div>
                    <div class="stat-value" id="totalAssignedLeads">{{ $totalAssignedLeads }}</div>
                </div>
                <div class="stat-icon blue"><span class="material-icons" style="font-size:21px;">assignment_ind</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#1d2c4e;--bar-pct:{{ min(100, ($totalAssignedLeads / max(1,50))*100) }}%"></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-v2 tc-stat">
            <div class="tc-stat-badge" style="background:linear-gradient(135deg,#10b981,#059669);">
                {{ $newLeads > 0 ? min(99, round(($newLeads / max(1,20))*100)) : 0 }}%
            </div>
            <div class="d-flex align-items-start justify-content-between mt-1">
                <div>
                    <div class="stat-label">New Leads</div>
                    <div class="stat-value" id="newLeads">{{ $newLeads }}</div>
                </div>
                <div class="stat-icon green"><span class="material-icons" style="font-size:21px;">fiber_new</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#10b981;--bar-pct:{{ min(100, ($newLeads / max(1,20))*100) }}%"></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-v2 tc-stat">
            <div class="tc-stat-badge" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                {{ $followupsToday > 0 ? min(99, round(($followupsToday / max(1,10))*100)) : 0 }}%
            </div>
            <div class="d-flex align-items-start justify-content-between mt-1">
                <div>
                    <div class="stat-label">Follow-ups Today</div>
                    <div class="stat-value" id="followupsToday">{{ $followupsToday }}</div>
                </div>
                <div class="stat-icon amber"><span class="material-icons" style="font-size:21px;">event</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#f59e0b;--bar-pct:{{ min(100, ($followupsToday / max(1,10))*100) }}%"></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-v2 tc-stat {{ $overdueFollowups > 0 ? 'highlight-danger' : '' }}">
            <div class="tc-stat-badge" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                {{ $overdueFollowups > 0 ? min(99, round(($overdueFollowups / max(1,5))*100)) : 0 }}%
            </div>
            <div class="d-flex align-items-start justify-content-between mt-1">
                <div>
                    <div class="stat-label">Overdue Follow-ups</div>
                    <div class="stat-value" id="overdueFollowups">{{ $overdueFollowups }}</div>
                </div>
                <div class="stat-icon red"><span class="material-icons" style="font-size:21px;">warning_amber</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#ef4444;--bar-pct:{{ min(100, ($overdueFollowups / max(1,5))*100) }}%"></div>
        </div>
    </div>
</div>

{{-- ── Row 2 — Call Stats ─────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card stat-card-v2 tc-stat">
            <div class="tc-stat-badge" style="background:linear-gradient(135deg,#253769,#1d2c4e);">
                {{ $totalCallsToday > 0 ? min(99, round(($totalCallsToday / max(1,30))*100)) : 0 }}%
            </div>
            <div class="d-flex align-items-start justify-content-between mt-1">
                <div>
                    <div class="stat-label">Calls Today</div>
                    <div class="stat-value" id="totalCallsToday">{{ $totalCallsToday }}</div>
                </div>
                <div class="stat-icon blue"><span class="material-icons" style="font-size:21px;">call</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#1d2c4e;--bar-pct:{{ min(100, ($totalCallsToday / max(1,30))*100) }}%"></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card stat-card-v2 tc-stat">
            <div class="tc-stat-badge" style="background:linear-gradient(135deg,#06b6d4,#0891b2);">
                {{ $talkTimeTodaySeconds > 0 ? min(99, round(($talkTimeTodaySeconds / max(1,3600))*100)) : 0 }}%
            </div>
            <div class="d-flex align-items-start justify-content-between mt-1">
                <div>
                    <div class="stat-label">Talk Time</div>
                    <div class="stat-value" id="talkTimeToday" style="font-size:22px;letter-spacing:-0.5px;">
                        {{ gmdate('H:i:s', max(0, (int) $talkTimeTodaySeconds)) }}
                    </div>
                </div>
                <div class="stat-icon cyan"><span class="material-icons" style="font-size:21px;">timer</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#06b6d4;--bar-pct:{{ min(100, ($talkTimeTodaySeconds / max(1,3600))*100) }}%"></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card stat-card-v2 tc-stat {{ $missedCallbacks->count() > 0 ? 'highlight-danger' : '' }}">
            <div class="tc-stat-badge" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                {{ $missedCallbacks->count() > 0 ? min(99, round(($missedCallbacks->count() / max(1,5))*100)) : 0 }}%
            </div>
            <div class="d-flex align-items-start justify-content-between mt-1">
                <div>
                    <div class="stat-label">Missed Callbacks</div>
                    <div class="stat-value" id="missedCallAlerts">{{ $missedCallbacks->count() }}</div>
                </div>
                <div class="stat-icon red"><span class="material-icons" style="font-size:21px;">phone_missed</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#ef4444;--bar-pct:{{ min(100, ($missedCallbacks->count() / max(1,5))*100) }}%"></div>
        </div>
    </div>
</div>

{{-- ── Row 3 — Quick Actions + Missed Callbacks ───────────────────────── --}}
<div class="row g-3 mb-4">

    {{-- Quick Actions --}}
    <div class="col-lg-8">
        <div class="chart-card h-100" style="margin-bottom:0;">
            <div class="tc-section-head mb-3">
                <h3 class="tc-section-title">
                    <span class="material-icons" style="color:#1d2c4e;">bolt</span>
                    Quick Actions
                </h3>
                <span class="tc-section-badge">Daily Workflow</span>
            </div>

            <div class="tc-qa-grid">
                <a href="{{ route('telecaller.leads', ['status' => 'new']) }}" class="tc-qa-card tc-qa-violet">
                    <div class="tc-qa-icon"><span class="material-icons">new_releases</span></div>
                    <div>
                        <div class="tc-qa-label">New Leads</div>
                        <div class="tc-qa-hint">Freshly assigned</div>
                    </div>
                </a>
                <a href="{{ route('telecaller.leads', ['status' => 'follow_up']) }}" class="tc-qa-card tc-qa-warning">
                    <div class="tc-qa-icon"><span class="material-icons">event_note</span></div>
                    <div>
                        <div class="tc-qa-label">Follow-ups Due</div>
                        <div class="tc-qa-hint">Pending today</div>
                    </div>
                </a>
                <button type="button" class="tc-qa-card tc-qa-danger" id="jumpMissedCallbacks">
                    <div class="tc-qa-icon"><span class="material-icons">phone_missed</span></div>
                    <div>
                        <div class="tc-qa-label">Missed Callbacks</div>
                        <div class="tc-qa-hint">Needs attention</div>
                    </div>
                </button>
                <a href="{{ route('telecaller.calls.outbound') }}" class="tc-qa-card tc-qa-success">
                    <div class="tc-qa-icon"><span class="material-icons">call</span></div>
                    <div>
                        <div class="tc-qa-label">Outbound Calls</div>
                        <div class="tc-qa-hint">Start calling</div>
                    </div>
                </a>
            </div>

            {{-- Performance mini strip --}}
            <div class="tc-perf-strip mt-3">
                <div class="tc-perf-item">
                    <div class="tc-perf-dot" style="background:#1d2c4e;"></div>
                    <div>
                        <div class="tc-perf-val" id="miniAssigned">{{ $totalAssignedLeads }}</div>
                        <div class="tc-perf-key">Leads</div>
                    </div>
                </div>
                <div class="tc-perf-divider"></div>
                <div class="tc-perf-item">
                    <div class="tc-perf-dot" style="background:#253769;"></div>
                    <div>
                        <div class="tc-perf-val" id="miniCalls">{{ $totalCallsToday }}</div>
                        <div class="tc-perf-key">Calls</div>
                    </div>
                </div>
                <div class="tc-perf-divider"></div>
                <div class="tc-perf-item">
                    <div class="tc-perf-dot" style="background:#f59e0b;"></div>
                    <div>
                        <div class="tc-perf-val" id="miniFollowups">{{ $followupsToday }}</div>
                        <div class="tc-perf-key">Due Today</div>
                    </div>
                </div>
                <div class="ms-auto">
                    <button type="button" class="btn btn-sm tc-refresh-btn d-flex align-items-center gap-1" id="refreshTelecallerPanel">
                        <span class="material-icons" style="font-size:15px;">refresh</span> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Missed Callbacks Panel --}}
    <div class="col-lg-4" id="missedCallbacksPanel">
        <div class="chart-card h-100" style="margin-bottom:0;">
            <div class="tc-section-head mb-2">
                <h3 class="tc-section-title">
                    <span class="material-icons" style="color:#ef4444;">phone_missed</span>
                    Missed Callbacks
                </h3>
                <span class="badge bg-danger rounded-pill" style="font-size:11px;" id="missedCallbackCountBadge">{{ $missedCallbacks->count() }}</span>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                <span id="missedCallbackCount">{{ $missedCallbacks->count() }}</span> pending callback(s)
            </p>
            <div class="d-flex flex-column gap-2" id="missedCallbackList" style="max-height:260px;overflow-y:auto;">
                @forelse($missedCallbacks as $item)
                    <div class="tc-callback-item tc-callback-item-v2">
                        <div class="tc-callback-avatar-v2">
                            {{ strtoupper(substr($item->lead->name ?? 'U', 0, 1)) }}
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="fw-semibold" style="font-size:13px;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                {{ $item->lead->name ?? 'Unknown Lead' }}
                            </div>
                            <div style="font-size:11.5px;color:var(--text-muted);margin-top:1px;display:flex;align-items:center;gap:4px;">
                                <span class="material-icons" style="font-size:12px;">tag</span>{{ $item->lead->lead_code ?? '-' }}
                                <span style="opacity:.4;">•</span>
                                <span class="material-icons" style="font-size:12px;">phone</span>{{ $item->lead->phone ?? $item->customer_number }}
                            </div>
                            @if ($item->lead_id)
                                <a href="{{ route('telecaller.leads.show', encrypt($item->lead_id)) }}"
                                    class="tc-callback-cta mt-2">
                                    <span class="material-icons" style="font-size:13px;">call</span> Call Back
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="tc-empty-state">
                        <span class="material-icons tc-empty-icon">check_circle</span>
                        <span>No missed callbacks</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

</div>

{{-- ── Row 4 — Follow-up Calendar ─────────────────────────────────────── --}}
<div class="row g-4">
    <div class="col-12">
        <x-followup-calendar
            :calendarData="$followupCalendar"
            :fetchUrl="route('telecaller.followups.calendar-data')"
            :todayUrl="route('telecaller.followups.today')"
            :overdueUrl="route('telecaller.followups.overdue')"
            :upcomingUrl="route('telecaller.followups.upcoming')"
            title="My Follow-Up Calendar"
            uid="tc"
        />
    </div>
</div>

<script>
(function () {
    /* ── Greeting & date ────────────────────────────────────────────── */
    (function () {
        const h = new Date().getHours();
        const greet = h < 12 ? 'Good Morning' : h < 17 ? 'Good Afternoon' : 'Good Evening';
        const el = document.getElementById('greetingTitle');
        if (el) el.textContent = greet + ', {{ auth()->user()->name }}';
        const dateEl = document.getElementById('greetingDate');
        if (dateEl) dateEl.textContent = new Date().toLocaleDateString('en-US', { weekday:'long', month:'long', day:'numeric' });
    })();

    /* ── DOM refs ───────────────────────────────────────────────────── */
    const totalAssignedLeads  = document.getElementById('totalAssignedLeads');
    const newLeads            = document.getElementById('newLeads');
    const followupsTodayEl    = document.getElementById('followupsToday');
    const overdueFollowupsEl  = document.getElementById('overdueFollowups');
    const totalCallsTodayEl   = document.getElementById('totalCallsToday');
    const talkTimeTodayEl     = document.getElementById('talkTimeToday');
    const missedCallAlertsEl  = document.getElementById('missedCallAlerts');
    const missedCallbackCount = document.getElementById('missedCallbackCount');
    const missedCallbackCountBadge = document.getElementById('missedCallbackCountBadge');
    const missedCallbackList  = document.getElementById('missedCallbackList');
    const refreshBtn          = document.getElementById('refreshTelecallerPanel');
    const jumpMissedCallbacks = document.getElementById('jumpMissedCallbacks');
    const lastRefreshed       = document.getElementById('lastRefreshed');
    const miniAssigned        = document.getElementById('miniAssigned');
    const miniCalls           = document.getElementById('miniCalls');
    const miniFollowups       = document.getElementById('miniFollowups');
    const heroAssigned        = document.getElementById('heroAssigned');
    const heroCalls           = document.getElementById('heroCalls');
    const heroFollowups       = document.getElementById('heroFollowups');
    const ringCallsEl         = document.getElementById('ringCalls');
    const ringCallsValEl      = document.getElementById('ringCallsVal');
    const ringFollowupsEl     = document.getElementById('ringFollowups');
    const ringFollowupsValEl  = document.getElementById('ringFollowupsVal');

    const snapshotUrl = @json(route('telecaller.panel.snapshot'));
    const CIRCUMFERENCE = 201.1;

    function toTimeLabel(s) {
        s = Number(s || 0);
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
        return [h, m, sec].map(v => String(v).padStart(2, '0')).join(':');
    }

    function setRing(el, val, max) {
        if (!el) return;
        const pct = Math.min(1, val / Math.max(1, max));
        el.setAttribute('stroke-dashoffset', Math.max(0, CIRCUMFERENCE - pct * CIRCUMFERENCE));
    }

    function renderMissedCallbacks(callbacks) {
        if (!missedCallbackList) return;
        if (!callbacks || !callbacks.length) {
            missedCallbackList.innerHTML = '<div class="tc-empty-state"><span class="material-icons tc-empty-icon">check_circle</span><span>No missed callbacks</span></div>';
            return;
        }
        missedCallbackList.innerHTML = callbacks.map(item => {
            const hasLead = !!item.encrypted_lead_id;
            const initial = (item.lead_name || 'U').charAt(0).toUpperCase();
            const callLink = hasLead
                ? `<a href="{{ url('telecaller/leads') }}/${encodeURIComponent(item.encrypted_lead_id)}" class="tc-callback-cta mt-2" style="text-decoration:none;">
                      <span class="material-icons" style="font-size:13px;">call</span> Call Back
                   </a>`
                : '';
            return `<div class="tc-callback-item tc-callback-item-v2">
                <div class="tc-callback-avatar-v2">${initial}</div>
                <div style="flex:1;min-width:0;">
                    <div class="fw-semibold" style="font-size:13px;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.lead_name || 'Unknown Lead'}</div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:1px;display:flex;align-items:center;gap:4px;">
                        <span class="material-icons" style="font-size:12px;">tag</span>${item.lead_code || '-'}
                        <span style="opacity:.4;">•</span>
                        <span class="material-icons" style="font-size:12px;">phone</span>${item.phone || '-'}
                    </div>
                    ${callLink}
                </div>
            </div>`;
        }).join('');
    }

    function renderSnapshot(data) {
        if (!data || !data.ok) return;
        const n = v => Number(v || 0);
        totalAssignedLeads.textContent = n(data.total_assigned_leads);
        newLeads.textContent           = n(data.new_leads);
        followupsTodayEl.textContent   = n(data.today_followup_count);
        overdueFollowupsEl.textContent = n(data.overdue_followup_count);
        totalCallsTodayEl.textContent  = n(data.total_calls_today);
        talkTimeTodayEl.textContent    = toTimeLabel(n(data.talk_time_today_seconds));

        const missed = n(data.missed_callback_count);
        missedCallAlertsEl.textContent  = missed;
        missedCallbackCount.textContent = missed;
        if (missedCallbackCountBadge) missedCallbackCountBadge.textContent = missed;

        if (miniAssigned)  miniAssigned.textContent  = n(data.total_assigned_leads);
        if (miniCalls)     miniCalls.textContent     = n(data.total_calls_today);
        if (miniFollowups) miniFollowups.textContent = n(data.today_followup_count);
        if (heroAssigned)  heroAssigned.textContent  = n(data.total_assigned_leads);
        if (heroCalls)     heroCalls.textContent     = n(data.total_calls_today);
        if (heroFollowups) heroFollowups.textContent = n(data.today_followup_count);

        setRing(ringCallsEl, n(data.total_calls_today), 20);
        if (ringCallsValEl) ringCallsValEl.textContent = n(data.total_calls_today);
        setRing(ringFollowupsEl, n(data.today_followup_count), 10);
        if (ringFollowupsValEl) ringFollowupsValEl.textContent = n(data.today_followup_count);

        renderMissedCallbacks(data.missed_callbacks || []);

        if (lastRefreshed) {
            lastRefreshed.textContent = new Date().toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
        }
    }

    async function fetchSnapshot() {
        try {
            const res  = await fetch(snapshotUrl, { headers: { 'Accept': 'application/json' } });
            renderSnapshot(await res.json());
        } catch (e) {}
    }

    refreshBtn?.addEventListener('click', function () {
        const icon = this.querySelector('.material-icons');
        if (icon) { icon.style.animation = 'spin .6s linear'; setTimeout(() => icon.style.animation = '', 700); }
        fetchSnapshot();
    });

    jumpMissedCallbacks?.addEventListener('click', function () {
        document.getElementById('missedCallbacksPanel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    fetchSnapshot();
    setInterval(fetchSnapshot, 45000);
})();
</script>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes blobFloat { 0%,100%{transform:translateY(0) scale(1);} 50%{transform:translateY(-12px) scale(1.04);} }

/* ── Hero Banner — Navy theme matching sidebar ──── */
.tc-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1d2c4e 45%, #253769 75%, #1a3460 100%);
    border-radius: 20px;
    padding: 28px 32px;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(13,20,50,0.45), 0 2px 12px rgba(15,23,42,0.25);
}

/* Decorative blobs */
.tc-blob {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}
.tc-blob-1 {
    width: 220px; height: 220px;
    background: rgba(99,145,255,0.12);
    top: -70px; right: -50px;
    animation: blobFloat 7s ease-in-out infinite;
}
.tc-blob-2 {
    width: 140px; height: 140px;
    background: rgba(147,197,253,0.08);
    bottom: -50px; right: 140px;
    animation: blobFloat 9s ease-in-out infinite reverse;
}
.tc-blob-3 {
    width: 90px; height: 90px;
    background: rgba(255,255,255,0.05);
    top: 20px; left: 240px;
    animation: blobFloat 6s ease-in-out infinite 2s;
}

.tc-hero-inner {
    display: flex;
    align-items: stretch;
    gap: 28px;
    position: relative;
    z-index: 1;
}

/* ── Profile card inside hero ───────────────────── */
.tc-profile-card {
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 16px;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-width: 180px;
    flex-shrink: 0;
}

.tc-profile-avatar {
    width: 58px; height: 58px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b6cb8, #1d2c4e);
    border: 3px solid rgba(147,197,253,0.40);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: 800; color: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.30);
    margin-bottom: 4px;
}

.tc-profile-name {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    text-align: center;
    line-height: 1.3;
}

.tc-profile-role {
    display: flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600;
    color: rgba(147,197,253,0.80);
    text-transform: uppercase; letter-spacing: 1px;
}

.tc-profile-meta-row {
    display: flex; align-items: center; gap: 0;
    margin-top: 10px;
    background: rgba(0,0,0,0.18);
    border-radius: 10px;
    padding: 8px 12px;
    width: 100%;
    justify-content: space-around;
}

.tc-profile-meta-item { text-align: center; }
.tc-profile-meta-val {
    font-size: 20px; font-weight: 800; color: #fff; line-height: 1;
}
.tc-profile-meta-key {
    font-size: 9px; font-weight: 600;
    color: rgba(147,197,253,0.65);
    text-transform: uppercase; letter-spacing: .5px;
    margin-top: 2px;
}
.tc-profile-meta-sep {
    width: 1px; height: 28px;
    background: rgba(255,255,255,0.18);
    margin: 0 8px;
}

/* ── Right section ──────────────────────────────── */
.tc-hero-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 16px;
}

.tc-hero-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.tc-hero-meta-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 12.5px; font-weight: 600;
    background: rgba(255,255,255,0.12);
    padding: 5px 14px;
    border-radius: 20px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.10);
}

.tc-hero-alert {
    background: rgba(239,68,68,0.25);
    border-color: rgba(239,68,68,0.35);
}

.tc-rings {
    display: flex;
    gap: 20px;
    align-items: center;
}

.tc-ring-item {
    position: relative; width: 80px; height: 80px;
    display: flex; align-items: center; justify-content: center;
}

.tc-ring-label {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}

.tc-ring-val { font-size: 18px; font-weight: 800; color: #fff; line-height: 1; }
.tc-ring-sub { font-size: 9px; font-weight: 600; color: rgba(147,197,253,0.80); text-transform: uppercase; letter-spacing: .4px; }

/* ── Stat card badge ────────────────────────────── */
.tc-stat { overflow: visible !important; }
.tc-stat-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10.5px;
    font-weight: 700;
    color: #fff;
    padding: 3px 9px;
    border-radius: 20px;
    letter-spacing: .3px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
}

/* ── Section head ───────────────────────────────── */
.tc-section-head {
    display: flex; align-items: center; justify-content: space-between;
}

.tc-section-title {
    font-size: 14px; font-weight: 700;
    color: var(--text-dark);
    margin: 0;
    display: flex; align-items: center; gap: 6px;
}

.tc-section-badge {
    font-size: 11px; font-weight: 600;
    color: #1d2c4e;
    background: rgba(29,44,78,0.09);
    border: 1px solid rgba(29,44,78,0.18);
    padding: 3px 10px;
    border-radius: 20px;
}

/* ── Quick Actions Grid ─────────────────────────── */
.tc-qa-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.tc-qa-card {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px;
    border-radius: 14px;
    text-decoration: none;
    cursor: pointer; border: none;
    transition: all .18s ease;
    text-align: left;
}

.tc-qa-card:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,0,0,0.14); }

.tc-qa-icon {
    width: 42px; height: 42px; border-radius: 11px;
    background: rgba(255,255,255,0.22);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.tc-qa-icon .material-icons { font-size: 20px; }
.tc-qa-label { font-size: 13px; font-weight: 700; }
.tc-qa-hint  { font-size: 11px; opacity: .75; margin-top: 1px; }

.tc-qa-violet  { background: linear-gradient(135deg,#253769,#1d2c4e); color:#fff; box-shadow:0 3px 12px rgba(29,44,78,0.40); }
.tc-qa-warning { background: var(--grad-warning); color:#fff; box-shadow:0 3px 12px rgba(245,158,11,0.25); }
.tc-qa-danger  { background: var(--grad-danger);  color:#fff; box-shadow:0 3px 12px rgba(239,68,68,0.25); }
.tc-qa-success { background: var(--grad-success); color:#fff; box-shadow:0 3px 12px rgba(16,185,129,0.25); }

/* ── Performance Strip ──────────────────────────── */
.tc-perf-strip {
    display: flex; align-items: center; gap: 20px;
    background: rgba(29,44,78,0.05);
    border: 1px solid rgba(29,44,78,0.12);
    border-radius: 12px; padding: 10px 16px;
}

.tc-perf-item { display: flex; align-items: center; gap: 10px; }
.tc-perf-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.tc-perf-val { font-size: 17px; font-weight: 800; color: var(--text-dark); line-height: 1; }
.tc-perf-key { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; margin-top: 1px; }
.tc-perf-divider { width: 1px; height: 28px; background: rgba(29,44,78,0.15); }

/* ── Refresh Button ─────────────────────────────── */
.tc-refresh-btn {
    font-size: 12px;
    border-radius: 8px;
    border: 1px solid rgba(29,44,78,0.22);
    background: rgba(29,44,78,0.07);
    color: #1d2c4e;
    font-weight: 600;
    transition: all .15s;
}
.tc-refresh-btn:hover {
    background: rgba(29,44,78,0.14);
    color: #0f172a;
}

/* ── Callback Items ─────────────────────────────── */
.tc-callback-item-v2 {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    border: 1px solid var(--border-color);
    background: #fff;
    transition: box-shadow .15s;
}
.tc-callback-item-v2:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.07); }

.tc-callback-avatar-v2 {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--grad-danger); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 800; flex-shrink: 0;
}

.tc-callback-cta {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 6px;
    font-size: 11.5px; font-weight: 600;
    background: var(--grad-danger); color: #fff;
    text-decoration: none; border: none; cursor: pointer;
    transition: opacity .15s;
}
.tc-callback-cta:hover { opacity: .88; color: #fff; }

/* ── Empty State ────────────────────────────────── */
.tc-empty-state {
    display: flex; flex-direction: column; align-items: center;
    gap: 6px; padding: 28px 0; color: var(--text-muted);
    font-size: 12.5px; font-weight: 500;
}
.tc-empty-icon { font-size: 38px !important; opacity: .35; }

/* ── Stat bar ───────────────────────────────────── */
.stat-card-bar {
    height: 3px; border-radius: 2px;
    background: linear-gradient(90deg, var(--bar-color), color-mix(in srgb, var(--bar-color) 60%, transparent));
    width: var(--bar-pct, 50%);
    margin-top: 12px;
    transition: width .6s ease;
}

@media (max-width: 640px) {
    .tc-hero-inner { flex-direction: column; gap: 16px; }
    .tc-profile-card { min-width: unset; width: 100%; }
    .tc-rings { display: none; }
    .tc-qa-grid { grid-template-columns: 1fr 1fr; }
    .tc-hero-banner { padding: 20px; }
}
</style>
@endsection
