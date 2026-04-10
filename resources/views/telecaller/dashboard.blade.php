@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('header_actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge rounded-pill border px-3 py-2 d-flex align-items-center gap-1"
              style="background:rgba(99,102,241,0.08);color:#6366f1;border-color:rgba(99,102,241,0.2)!important;font-size:11px;font-weight:700;">
            <span class="material-icons" style="font-size:14px;">headset_mic</span>
            <span id="realtimeCallStatus">{{ $activeCallCount > 0 ? 'On Call' : 'Idle' }}</span>
        </span>
        <span id="activeCallBadge"
              class="badge rounded-pill px-3 py-2 d-flex align-items-center gap-1 {{ $activeCallCount > 0 ? 'bg-danger' : 'bg-success' }}"
              style="font-size:11px;font-weight:700;">
            <span class="material-icons" style="font-size:13px;">call</span>
            Active: <span id="activeCallCount">{{ $activeCallCount }}</span>
        </span>
    </div>
@endsection

@section('content')

    {{-- ── Page Header ── --}}
    <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 style="font-size:22px;font-weight:800;color:#0f172a;margin:0;letter-spacing:-0.3px;">
                @php
                    $hour = now()->hour;
                    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
                @endphp
                {{ $greeting }}, {{ explode(' ', auth()->user()->name)[0] }} 👋
            </h2>
            <p style="color:#64748b;font-size:13px;margin:4px 0 0 0;">Here's your activity summary for today.</p>
        </div>
        <span style="font-size:11px;font-weight:700;color:#64748b;background:#f1f5f9;border-radius:20px;padding:5px 14px;border:1px solid #e2e8f0;">
            {{ now()->format('D, d M Y') }}
        </span>
    </div>

    {{-- ── Follow-up Reminder Banner ── --}}
    @if ($followupsToday > 0 || $overdueFollowups > 0)
        <div class="mb-4 rounded-3 px-4 py-3 d-flex align-items-center gap-3 flex-wrap"
             style="background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(245,158,11,0.05));border:1px solid rgba(245,158,11,0.3);">
            <div style="width:38px;height:38px;min-width:38px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#fbbf24);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(245,158,11,0.35);">
                <span class="material-icons" style="font-size:20px;color:#fff;">notifications_active</span>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:700;color:#92400e;">Follow-up Reminder</div>
                <div style="font-size:12px;color:#78350f;margin-top:1px;">
                    <span id="todayFollowupCount">{{ $followupsToday }}</span> due today &nbsp;·&nbsp;
                    <span id="overdueFollowupCount">{{ $overdueFollowups }}</span> overdue
                </div>
            </div>
            <a href="{{ route('telecaller.leads', ['status' => 'follow_up']) }}"
               class="btn btn-sm"
               style="background:#f59e0b;color:#fff;font-weight:600;font-size:12px;border:none;padding:6px 16px;border-radius:8px;white-space:nowrap;">
                View Leads
            </a>
        </div>
    @endif

    {{-- ── Row 1 — Primary Stats ── --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">assignment_ind</span></div>
                <div class="stat-label">Assigned Leads</div>
                <div class="stat-value" id="totalAssignedLeads">{{ $totalAssignedLeads }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">fiber_new</span></div>
                <div class="stat-label">New Leads</div>
                <div class="stat-value" id="newLeads">{{ $newLeads }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">event_note</span></div>
                <div class="stat-label">Follow-ups Today</div>
                <div class="stat-value" id="followupsToday">{{ $followupsToday }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card highlight-danger">
                <div class="stat-icon red"><span class="material-icons">warning</span></div>
                <div class="stat-label">Overdue Follow-ups</div>
                <div class="stat-value" id="overdueFollowups">{{ $overdueFollowups }}</div>
            </div>
        </div>
    </div>

    {{-- ── Row 2 — Call Stats ── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">call</span></div>
                <div class="stat-label">Calls Today</div>
                <div class="stat-value" id="totalCallsToday">{{ $totalCallsToday }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon cyan"><span class="material-icons">timer</span></div>
                <div class="stat-label">Talk Time Today</div>
                <div class="stat-value stat-value--mono" id="talkTimeToday">
                    {{ gmdate('H:i:s', max(0, (int) $talkTimeTodaySeconds)) }}
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card {{ $missedCallbacks->count() > 0 ? 'highlight-danger' : '' }}">
                <div class="stat-icon red"><span class="material-icons">phone_missed</span></div>
                <div class="stat-label">Missed Callbacks</div>
                <div class="stat-value" id="missedCallAlerts">{{ $missedCallbacks->count() }}</div>
            </div>
        </div>
    </div>

    {{-- ── Row 3 — Quick Actions + Missed Callbacks ── --}}
    <div class="row g-3 mb-4">
        {{-- Quick Actions --}}
        <div class="col-lg-7">
            <div class="chart-card h-100" style="margin-bottom:0;">
                <div class="chart-header mb-3">
                    <div>
                        <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Quick Actions</h3>
                        <p style="font-size:12px;color:#64748b;margin:3px 0 0 0;">Jump straight into your daily workflow</p>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6 col-sm-3">
                        <a href="{{ route('telecaller.leads', ['status' => 'new']) }}"
                           class="d-flex flex-column align-items-center justify-content-center gap-2 p-3 rounded-3 text-decoration-none text-center h-100"
                           style="background:rgba(99,102,241,0.07);border:1px solid rgba(99,102,241,0.15);color:#6366f1;transition:all .2s;"
                           onmouseover="this.style.background='rgba(99,102,241,0.14)'" onmouseout="this.style.background='rgba(99,102,241,0.07)'">
                            <span class="material-icons" style="font-size:22px;">new_releases</span>
                            <span style="font-size:11px;font-weight:700;">New Leads</span>
                        </a>
                    </div>
                    <div class="col-6 col-sm-3">
                        <a href="{{ route('telecaller.leads', ['status' => 'follow_up']) }}"
                           class="d-flex flex-column align-items-center justify-content-center gap-2 p-3 rounded-3 text-decoration-none text-center h-100"
                           style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);color:#d97706;transition:all .2s;"
                           onmouseover="this.style.background='rgba(245,158,11,0.15)'" onmouseout="this.style.background='rgba(245,158,11,0.08)'">
                            <span class="material-icons" style="font-size:22px;">event</span>
                            <span style="font-size:11px;font-weight:700;">Follow-ups</span>
                        </a>
                    </div>
                    <div class="col-6 col-sm-3">
                        <button type="button" id="jumpMissedCallbacks"
                           class="d-flex flex-column align-items-center justify-content-center gap-2 p-3 rounded-3 text-center w-100 border-0 h-100"
                           style="background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.15)!important;color:#ef4444;transition:all .2s;cursor:pointer;"
                           onmouseover="this.style.background='rgba(239,68,68,0.14)'" onmouseout="this.style.background='rgba(239,68,68,0.07)'">
                            <span class="material-icons" style="font-size:22px;">phone_missed</span>
                            <span style="font-size:11px;font-weight:700;">Missed Calls</span>
                        </button>
                    </div>
                    <div class="col-6 col-sm-3">
                        <button type="button" id="refreshTelecallerPanel"
                           class="d-flex flex-column align-items-center justify-content-center gap-2 p-3 rounded-3 text-center w-100 border-0 h-100"
                           style="background:rgba(100,116,139,0.07);border:1px solid rgba(100,116,139,0.15)!important;color:#64748b;transition:all .2s;cursor:pointer;"
                           onmouseover="this.style.background='rgba(100,116,139,0.13)'" onmouseout="this.style.background='rgba(100,116,139,0.07)'">
                            <span class="material-icons" style="font-size:22px;">refresh</span>
                            <span style="font-size:11px;font-weight:700;">Refresh</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Missed Callbacks Panel --}}
        <div class="col-lg-5" id="missedCallbacksPanel">
            <div class="chart-card h-100" style="margin-bottom:0;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Missed Callbacks</h3>
                        <p style="font-size:12px;color:#64748b;margin:3px 0 0 0;">
                            <span id="missedCallbackCount">{{ $missedCallbacks->count() }}</span> pending callback(s)
                        </p>
                    </div>
                    @if($missedCallbacks->count() > 0)
                        <span style="background:rgba(239,68,68,0.1);color:#ef4444;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;border:1px solid rgba(239,68,68,0.2);">
                            Action needed
                        </span>
                    @endif
                </div>
                <div class="d-flex flex-column gap-2" id="missedCallbackList" style="max-height:240px;overflow-y:auto;">
                    @forelse($missedCallbacks as $item)
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3"
                             style="background:#fef2f2;border:1px solid rgba(239,68,68,0.15);">
                            <div style="width:34px;height:34px;min-width:34px;border-radius:50%;background:linear-gradient(135deg,#ef4444,#f87171);display:flex;align-items:center;justify-content:center;">
                                <span class="material-icons" style="font-size:17px;color:#fff;">phone_missed</span>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    {{ $item->lead->name ?? 'Unknown Lead' }}
                                </div>
                                <div style="font-size:11px;color:#64748b;margin-top:1px;">
                                    {{ $item->lead->lead_code ?? '-' }} · {{ $item->lead->phone ?? $item->customer_number }}
                                </div>
                            </div>
                            @if ($item->lead_id)
                                <a href="{{ route('telecaller.leads.show', encrypt($item->lead_id)) }}"
                                   class="btn btn-sm"
                                   style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:5px 12px;border:none;border-radius:7px;white-space:nowrap;">
                                    Call Back
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="d-flex flex-column align-items-center justify-content-center py-4 gap-2"
                             style="color:#94a3b8;">
                            <span class="material-icons" style="font-size:36px;opacity:.4;">check_circle</span>
                            <span style="font-size:12px;font-weight:600;">No missed callbacks</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ── Follow-up Calendar ── --}}
    <div class="row g-3">
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
        (function() {
            const csrfToken = @json(csrf_token());
            const snapshotUrl = @json(route('telecaller.panel.snapshot'));

            const realtimeCallStatus   = document.getElementById('realtimeCallStatus');
            const activeCallBadge      = document.getElementById('activeCallBadge');
            const activeCallCount      = document.getElementById('activeCallCount');
            const totalAssignedLeads   = document.getElementById('totalAssignedLeads');
            const newLeads             = document.getElementById('newLeads');
            const followupsToday       = document.getElementById('followupsToday');
            const overdueFollowups     = document.getElementById('overdueFollowups');
            const totalCallsToday      = document.getElementById('totalCallsToday');
            const talkTimeToday        = document.getElementById('talkTimeToday');
            const missedCallAlerts     = document.getElementById('missedCallAlerts');
            const missedCallbackCount  = document.getElementById('missedCallbackCount');
            const missedCallbackList   = document.getElementById('missedCallbackList');
            const refreshBtn           = document.getElementById('refreshTelecallerPanel');
            const jumpMissedCallbacks  = document.getElementById('jumpMissedCallbacks');
            const todayFollowupCount   = document.getElementById('todayFollowupCount');
            const overdueFollowupCount = document.getElementById('overdueFollowupCount');

            function toTimeLabel(totalSeconds) {
                const sec = Number(totalSeconds || 0);
                const h = Math.floor(sec / 3600);
                const m = Math.floor((sec % 3600) / 60);
                const s = sec % 60;
                return [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
            }

            function renderMissedCallbacks(callbacks) {
                if (!missedCallbackList) return;
                if (!callbacks || !callbacks.length) {
                    missedCallbackList.innerHTML = `
                        <div class="d-flex flex-column align-items-center justify-content-center py-4 gap-2" style="color:#94a3b8;">
                            <span class="material-icons" style="font-size:36px;opacity:.4;">check_circle</span>
                            <span style="font-size:12px;font-weight:600;">No missed callbacks</span>
                        </div>`;
                    return;
                }
                missedCallbackList.innerHTML = callbacks.map(item => {
                    const hasLead = !!item.encrypted_lead_id;
                    const target  = hasLead ? `{{ url('telecaller/leads') }}/${encodeURIComponent(item.encrypted_lead_id)}` : '#';
                    return `
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:#fef2f2;border:1px solid rgba(239,68,68,0.15);">
                            <div style="width:34px;height:34px;min-width:34px;border-radius:50%;background:linear-gradient(135deg,#ef4444,#f87171);display:flex;align-items:center;justify-content:center;">
                                <span class="material-icons" style="font-size:17px;color:#fff;">phone_missed</span>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.lead_name || 'Unknown Lead'}</div>
                                <div style="font-size:11px;color:#64748b;margin-top:1px;">${item.lead_code || '-'} · ${item.phone || '-'}</div>
                            </div>
                            ${hasLead ? `<a href="${target}" class="btn btn-sm" style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:5px 12px;border:none;border-radius:7px;white-space:nowrap;">Call Back</a>` : ''}
                        </div>`;
                }).join('');
            }

            function renderSnapshot(data) {
                if (!data || !data.ok) return;
                const calls = Number(data.active_call_count || 0);

                activeCallCount.textContent = calls;
                realtimeCallStatus.textContent = data.call_status || (calls > 0 ? 'On Call' : 'Idle');
                activeCallBadge.classList.remove('bg-danger', 'bg-success');
                activeCallBadge.classList.add(calls > 0 ? 'bg-danger' : 'bg-success');

                totalAssignedLeads.textContent = Number(data.total_assigned_leads || 0);
                newLeads.textContent           = Number(data.new_leads || 0);
                followupsToday.textContent     = Number(data.today_followup_count || 0);
                overdueFollowups.textContent   = Number(data.overdue_followup_count || 0);
                totalCallsToday.textContent    = Number(data.total_calls_today || 0);
                talkTimeToday.textContent      = toTimeLabel(Number(data.talk_time_today_seconds || 0));

                const missed = Number(data.missed_callback_count || 0);
                missedCallAlerts.textContent  = missed;
                missedCallbackCount.textContent = missed;

                if (todayFollowupCount)   todayFollowupCount.textContent   = Number(data.today_followup_count || 0);
                if (overdueFollowupCount) overdueFollowupCount.textContent = Number(data.overdue_followup_count || 0);

                renderMissedCallbacks(data.missed_callbacks || []);
            }

            async function fetchSnapshot() {
                try {
                    const res  = await fetch(snapshotUrl, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    renderSnapshot(data);
                } catch (e) {}
            }

            refreshBtn?.addEventListener('click', fetchSnapshot);
            jumpMissedCallbacks?.addEventListener('click', function () {
                document.getElementById('missedCallbacksPanel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            fetchSnapshot();
            setInterval(fetchSnapshot, 45000);
        })();
    </script>
@endsection
