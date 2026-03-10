@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('header_actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge rounded-pill text-bg-light border px-3 py-2 d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size: 15px;">call</span>
            <span id="realtimeCallStatus">{{ $activeCallCount > 0 ? 'On Call' : 'Idle' }}</span>
        </span>
        <span class="badge rounded-pill {{ $activeCallCount > 0 ? 'text-bg-danger' : 'text-bg-success' }} px-3 py-2"
            id="activeCallBadge">
            Active Calls: <span id="activeCallCount">{{ $activeCallCount }}</span>
        </span>
        <div class="form-check form-switch m-0 ms-2">
            <input class="form-check-input" type="checkbox" role="switch" id="availabilityToggle"
                {{ auth()->user()->is_online ? 'checked' : '' }}>
            <label class="form-check-label small fw-semibold" for="availabilityToggle" id="availabilityLabel">
                {{ auth()->user()->is_online ? 'Online' : 'Offline' }}
            </label>
        </div>
    </div>
@endsection

@section('content')
    @if ($followupsToday > 0 || $overdueFollowups > 0)
        <div class="alert alert-warning">
            <strong>Follow-up reminder:</strong>
            Today: <span id="todayFollowupCount">{{ $followupsToday }}</span>,
            Overdue: <span id="overdueFollowupCount">{{ $overdueFollowups }}</span>.
            <a href="{{ route('telecaller.leads', ['status' => 'follow_up']) }}" class="fw-semibold ms-2">Open Leads</a>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">assignment_ind</span></div>
                <div class="stat-label">Total Assigned Leads</div>
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
                <div class="stat-icon amber"><span class="material-icons">event</span></div>
                <div class="stat-label">Followups Today</div>
                <div class="stat-value" id="followupsToday">{{ $followupsToday }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card highlight-danger">
                <div class="stat-icon red"><span class="material-icons">warning</span></div>
                <div class="stat-label">Overdue Followups</div>
                <div class="stat-value" id="overdueFollowups">{{ $overdueFollowups }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">call</span></div>
                <div class="stat-label">Total Calls Today</div>
                <div class="stat-value" id="totalCallsToday">{{ $totalCallsToday }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">timer</span></div>
                <div class="stat-label">Talk Time Today</div>
                <div class="stat-value" id="talkTimeToday">
                    {{ gmdate('H:i:s', max(0, (int) $talkTimeTodaySeconds)) }}
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="stat-icon red"><span class="material-icons">phone_missed</span></div>
                <div class="stat-label">Missed Call Alerts</div>
                <div class="stat-value" id="missedCallAlerts">{{ $missedCallbacks->count() }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="chart-card h-100">
                <div class="chart-header mb-3">
                    <h3>Quick Actions</h3>
                    <p>Speed up daily workflow</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('telecaller.leads', ['status' => 'new']) }}" class="btn btn-outline-primary btn-sm">
                        <span class="material-icons align-middle" style="font-size:16px;">new_releases</span> New Leads
                    </a>
                    <a href="{{ route('telecaller.leads', ['status' => 'follow_up']) }}"
                        class="btn btn-outline-warning btn-sm text-dark">
                        <span class="material-icons align-middle" style="font-size:16px;">event</span> Follow-ups Due
                    </a>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="jumpMissedCallbacks">
                        <span class="material-icons align-middle" style="font-size:16px;">phone_missed</span> Missed Callbacks
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="refreshTelecallerPanel">
                        <span class="material-icons align-middle" style="font-size:16px;">refresh</span> Refresh Status
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-4" id="missedCallbacksPanel">
            <div class="chart-card h-100">
                <div class="chart-header mb-2">
                    <h3>Missed Callback Alerts</h3>
                    <p><span id="missedCallbackCount">{{ $missedCallbacks->count() }}</span> pending callback(s)</p>
                </div>
                <div class="d-flex flex-column gap-2" id="missedCallbackList">
                    @forelse($missedCallbacks as $item)
                        <div class="border rounded p-2">
                            <div class="fw-semibold">{{ $item->lead->name ?? 'Unknown Lead' }}</div>
                            <small class="text-muted">{{ $item->lead->lead_code ?? '-' }} | {{ $item->lead->phone ?? $item->customer_number }}</small>
                            @if ($item->lead_id)
                                <div class="mt-2">
                                    <a href="{{ route('telecaller.leads.show', encrypt($item->lead_id)) }}"
                                        class="btn btn-sm btn-primary">Call Back</a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small">No missed callbacks.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const csrfToken = @json(csrf_token());
            const availabilityUrl = @json(route('telecaller.status.availability'));
            const snapshotUrl = @json(route('telecaller.panel.snapshot'));
            const availabilityStorageKey = 'telecaller_availability';

            const availabilityToggle = document.getElementById('availabilityToggle');
            const availabilityLabel = document.getElementById('availabilityLabel');
            const realtimeCallStatus = document.getElementById('realtimeCallStatus');
            const activeCallBadge = document.getElementById('activeCallBadge');
            const activeCallCount = document.getElementById('activeCallCount');
            const totalAssignedLeads = document.getElementById('totalAssignedLeads');
            const newLeads = document.getElementById('newLeads');
            const followupsToday = document.getElementById('followupsToday');
            const overdueFollowups = document.getElementById('overdueFollowups');
            const totalCallsToday = document.getElementById('totalCallsToday');
            const talkTimeToday = document.getElementById('talkTimeToday');
            const missedCallAlerts = document.getElementById('missedCallAlerts');
            const missedCallbackCount = document.getElementById('missedCallbackCount');
            const missedCallbackList = document.getElementById('missedCallbackList');
            const refreshBtn = document.getElementById('refreshTelecallerPanel');
            const jumpMissedCallbacks = document.getElementById('jumpMissedCallbacks');
            const todayFollowupCount = document.getElementById('todayFollowupCount');
            const overdueFollowupCount = document.getElementById('overdueFollowupCount');

            function setAvailabilityUI(isOnline) {
                availabilityToggle.checked = !!isOnline;
                availabilityLabel.textContent = isOnline ? 'Online' : 'Offline';
                localStorage.setItem(availabilityStorageKey, isOnline ? 'online' : 'offline');
            }

            function toTimeLabel(totalSeconds) {
                const sec = Number(totalSeconds || 0);
                const h = Math.floor(sec / 3600);
                const m = Math.floor((sec % 3600) / 60);
                const s = sec % 60;
                return [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
            }

            async function postAvailability(isOnline) {
                await fetch(availabilityUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        is_online: !!isOnline
                    })
                });
            }

            function renderMissedCallbacks(callbacks) {
                if (!missedCallbackList) return;
                if (!callbacks || !callbacks.length) {
                    missedCallbackList.innerHTML = '<div class="text-muted small">No missed callbacks.</div>';
                    return;
                }

                missedCallbackList.innerHTML = callbacks.map(item => {
                    const hasLead = !!item.encrypted_lead_id;
                    const target = hasLead ? `{{ url('telecaller/leads') }}/${encodeURIComponent(item.encrypted_lead_id)}` : '#';
                    return `
                        <div class="border rounded p-2">
                            <div class="fw-semibold">${item.lead_name || 'Unknown Lead'}</div>
                            <small class="text-muted">${item.lead_code || '-'} | ${item.phone || '-'}</small>
                            ${hasLead ? `<div class="mt-2"><a href="${target}" class="btn btn-sm btn-primary">Call Back</a></div>` : ''}
                        </div>
                    `;
                }).join('');
            }

            function renderSnapshot(data) {
                if (!data || !data.ok) return;

                setAvailabilityUI(!!data.is_online);

                const calls = Number(data.active_call_count || 0);
                activeCallCount.textContent = calls;
                realtimeCallStatus.textContent = data.call_status || (calls > 0 ? 'On Call' : 'Idle');
                activeCallBadge.classList.remove('text-bg-danger', 'text-bg-success');
                activeCallBadge.classList.add(calls > 0 ? 'text-bg-danger' : 'text-bg-success');

                totalAssignedLeads.textContent = Number(data.total_assigned_leads || 0);
                newLeads.textContent = Number(data.new_leads || 0);
                followupsToday.textContent = Number(data.today_followup_count || 0);
                overdueFollowups.textContent = Number(data.overdue_followup_count || 0);
                totalCallsToday.textContent = Number(data.total_calls_today || 0);
                talkTimeToday.textContent = toTimeLabel(Number(data.talk_time_today_seconds || 0));

                const missed = Number(data.missed_callback_count || 0);
                missedCallAlerts.textContent = missed;
                missedCallbackCount.textContent = missed;
                if (todayFollowupCount) todayFollowupCount.textContent = Number(data.today_followup_count || 0);
                if (overdueFollowupCount) overdueFollowupCount.textContent = Number(data.overdue_followup_count || 0);

                renderMissedCallbacks(data.missed_callbacks || []);
            }

            async function fetchSnapshot() {
                try {
                    const res = await fetch(snapshotUrl, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    renderSnapshot(data);
                } catch (e) {}
            }

            availabilityToggle.addEventListener('change', async function() {
                const isOnline = availabilityToggle.checked;
                setAvailabilityUI(isOnline);
                await postAvailability(isOnline);
                await fetchSnapshot();
            });

            refreshBtn?.addEventListener('click', fetchSnapshot);
            jumpMissedCallbacks?.addEventListener('click', function() {
                document.getElementById('missedCallbacksPanel')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });

            fetchSnapshot();
            setInterval(fetchSnapshot, 20000);
        })();
    </script>
@endsection
