@extends('layouts.app')

@section('page_title', 'My Leads')

@section('header_actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{-- View Toggle --}}
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('telecaller.leads') }}" class="btn btn-primary d-flex align-items-center gap-1" title="List View">
                <span class="material-icons" style="font-size:15px;">view_list</span>
                List
            </a>
            <a href="{{ route('telecaller.leads.pipeline') }}" class="btn btn-outline-primary d-flex align-items-center gap-1" title="Pipeline View">
                <span class="material-icons" style="font-size:15px;">view_kanban</span>
                Pipeline
            </a>
        </div>
        <span class="badge rounded-pill text-bg-light border px-3 py-2 d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size:15px;">call</span>
            <span id="realtimeCallStatus">{{ $activeCallCount > 0 ? 'On Call' : 'Idle' }}</span>
        </span>
        <span class="badge rounded-pill {{ $activeCallCount > 0 ? 'text-bg-danger' : 'text-bg-success' }} px-3 py-2" id="activeCallBadge">
            Active: <span id="activeCallCount">{{ $activeCallCount }}</span>
        </span>
    </div>
@endsection

@section('content')

{{-- ── Stat Cards ──────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-v2">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Total Leads</div>
                    <div class="stat-value">{{ $totalLeads }}</div>
                </div>
                <div class="stat-icon blue"><span class="material-icons">groups</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:var(--primary-color);--bar-pct:{{ min(100,($totalLeads/max(1,50))*100) }}%"></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-v2">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">New Leads</div>
                    <div class="stat-value">{{ $newLeads }}</div>
                </div>
                <div class="stat-icon green"><span class="material-icons">fiber_new</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#10b981;--bar-pct:{{ min(100,($newLeads/max(1,20))*100) }}%"></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-v2">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Interested</div>
                    <div class="stat-value">{{ $interestedLeads }}</div>
                </div>
                <div class="stat-icon amber"><span class="material-icons">thumb_up</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#f59e0b;--bar-pct:{{ min(100,($interestedLeads/max(1,$totalLeads))*100) }}%"></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-v2 highlight-success">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-label">Follow-up Today</div>
                    <div class="stat-value">{{ $followupToday }}</div>
                </div>
                <div class="stat-icon cyan"><span class="material-icons">event</span></div>
            </div>
            <div class="stat-card-bar" style="--bar-color:#06b6d4;--bar-pct:{{ min(100,($followupToday/max(1,10))*100) }}%"></div>
        </div>
    </div>
</div>

{{-- ── Filter Panel ────────────────────────────────────────────────────── --}}
<div class="lf-filter-card mb-4">
    <div class="lf-filter-head">
        <div class="d-flex align-items-center gap-2">
            <span class="material-icons" style="font-size:18px;color:var(--primary-color);">filter_list</span>
            <span style="font-size:14px;font-weight:700;color:var(--text-dark);">Filter Leads</span>
        </div>
        @if(request()->hasAny(['search','status','date_range']))
            <a href="{{ route('telecaller.leads') }}" class="lf-filter-reset">
                <span class="material-icons" style="font-size:13px;">close</span> Clear filters
            </a>
        @endif
    </div>
    <form method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <div class="lf-search-wrap">
                    <span class="material-icons lf-search-icon">search</span>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control lf-search-input"
                        placeholder="Search name, code, phone…">
                </div>
            </div>
            <div class="col-md-3">
                <div class="lf-select-wrap">
                    <span class="material-icons lf-select-icon">label</span>
                    <select name="status" class="form-select lf-select">
                        <option value="">All Statuses</option>
                        <option value="new"            {{ request('status') == 'new' ? 'selected' : '' }}>New</option>
                        <option value="contacted"      {{ request('status') == 'contacted' ? 'selected' : '' }}>Contacted</option>
                        <option value="interested"     {{ request('status') == 'interested' ? 'selected' : '' }}>Interested</option>
                        <option value="follow_up"      {{ request('status') == 'follow_up' ? 'selected' : '' }}>Follow-up</option>
                        <option value="not_interested" {{ request('status') == 'not_interested' ? 'selected' : '' }}>Not Interested</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="lf-select-wrap">
                    <span class="material-icons lf-select-icon">date_range</span>
                    <select name="date_range" class="form-select lf-select">
                        <option value="">All Dates</option>
                        <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="7"     {{ request('date_range') == '7' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30"    {{ request('date_range') == '30' ? 'selected' : '' }}>Last 30 Days</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-1" style="border-radius:10px;font-size:13px;font-weight:600;padding:10px;">
                    <span class="material-icons" style="font-size:16px;">search</span> Apply
                </button>
            </div>
        </div>

        {{-- Active filter pills --}}
        @if(request()->hasAny(['search','status','date_range']))
        <div class="d-flex align-items-center gap-2 flex-wrap mt-3 pt-3 border-top" style="border-color:var(--border-color)!important;">
            <span style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Active:</span>
            @if(request('search'))
                <span class="lf-active-pill">
                    <span class="material-icons" style="font-size:12px;">search</span>
                    "{{ request('search') }}"
                    <a href="{{ route('telecaller.leads', array_merge(request()->except('search', 'page'))) }}" class="lf-pill-close">×</a>
                </span>
            @endif
            @if(request('status'))
                <span class="lf-active-pill">
                    <span class="material-icons" style="font-size:12px;">label</span>
                    {{ ucfirst(str_replace('_', ' ', request('status'))) }}
                    <a href="{{ route('telecaller.leads', array_merge(request()->except('status', 'page'))) }}" class="lf-pill-close">×</a>
                </span>
            @endif
            @if(request('date_range'))
                <span class="lf-active-pill">
                    <span class="material-icons" style="font-size:12px;">date_range</span>
                    {{ request('date_range') == 'today' ? 'Today' : 'Last '.request('date_range').' Days' }}
                    <a href="{{ route('telecaller.leads', array_merge(request()->except('date_range', 'page'))) }}" class="lf-pill-close">×</a>
                </span>
            @endif
        </div>
        @endif
    </form>
</div>

{{-- ── Leads Table ─────────────────────────────────────────────────────── --}}
<div class="lf-table-card">
    <div class="lf-table-head">
        <div>
            <h3 class="lf-table-title">My Lead List</h3>
            <span class="lf-table-count">{{ $leads->total() }} records</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span style="font-size:12px;color:var(--text-muted);">
                Showing {{ $leads->firstItem() ?? 0 }}–{{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }}
            </span>
        </div>
    </div>

    @if($leads->count())
    <div class="table-responsive">
        <table class="table lf-table mb-0">
            <thead>
                <tr>
                    <th style="width:44px;">#</th>
                    <th>Lead</th>
                    <th>Phone</th>
                    <th>Course</th>
                    <th>Status</th>
                    <th>Next Follow-up</th>
                    <th style="width:100px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leads as $lead)
                    @php
                        $stCls = str_replace('_', '-', $lead->status);
                        $latestFollowup = $lead->followups->sortByDesc('next_followup')->first();
                        $followupDate = $latestFollowup?->next_followup
                            ? \Carbon\Carbon::parse($latestFollowup->next_followup)
                            : null;
                        $isOverdue = $followupDate && $followupDate->isPast();
                        $initial = strtoupper(substr($lead->name, 0, 1));
                        $avatarColors = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899'];
                        $avatarBg = $avatarColors[crc32($lead->name) % count($avatarColors)];
                    @endphp
                    <tr class="lf-lead-row">
                        <td class="text-muted" style="font-size:12px;font-weight:600;">
                            {{ ($leads->currentPage() - 1) * $leads->perPage() + $loop->iteration }}
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="lf-avatar" style="background:{{ $avatarBg }};">{{ $initial }}</div>
                                <div style="min-width:0;">
                                    <div class="lf-lead-name">{{ $lead->name }}</div>
                                    <div class="lf-lead-meta">
                                        <span class="material-icons" style="font-size:11px;">tag</span>{{ $lead->lead_code }}
                                        @if($lead->email)
                                            <span style="opacity:.35;">•</span>
                                            <span class="d-none d-md-inline">{{ $lead->email }}</span>
                                        @endif
                                        <x-aging-badge :days="$lead->days_aged" />
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1" style="font-size:13px;font-weight:600;color:var(--text-dark);">
                                <span class="material-icons" style="font-size:14px;color:var(--text-muted);">phone</span>
                                {{ $lead->phone }}
                            </div>
                        </td>
                        <td>
                            @if($lead->course)
                                <span class="lf-course-pill">{{ $lead->course }}</span>
                            @else
                                <span style="color:var(--text-muted);font-size:12px;">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="lead-status status-{{ $stCls }}">
                                {{ ucfirst(str_replace('_', ' ', $lead->status)) }}
                            </span>
                        </td>
                        <td>
                            @if($followupDate)
                                <div class="d-flex align-items-center gap-1 {{ $isOverdue ? 'text-danger' : '' }}" style="font-size:12.5px;font-weight:600;">
                                    <span class="material-icons" style="font-size:13px;">{{ $isOverdue ? 'warning' : 'event' }}</span>
                                    {{ $followupDate->format('d M Y') }}
                                </div>
                                @if($isOverdue)
                                    <div style="font-size:10.5px;color:#ef4444;font-weight:600;margin-top:1px;">Overdue</div>
                                @elseif($followupDate->isToday())
                                    <div style="font-size:10.5px;color:#f59e0b;font-weight:600;margin-top:1px;">Today</div>
                                @endif
                            @else
                                <span style="color:var(--text-muted);font-size:12px;">—</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ route('telecaller.leads.show', encrypt($lead->id)) }}"
                                class="lf-view-btn" title="View Lead">
                                <span class="material-icons" style="font-size:15px;">open_in_new</span>
                                View
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="lf-pagination">
        {{ $leads->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>

    @else
    {{-- Empty State --}}
    <div class="lf-empty-state">
        <div class="lf-empty-icon-wrap">
            <span class="material-icons">person_search</span>
        </div>
        <h4>No leads found</h4>
        <p>Try adjusting your filters or search query.</p>
        <a href="{{ route('telecaller.leads') }}" class="btn btn-primary btn-sm px-4" style="border-radius:8px;">
            Clear Filters
        </a>
    </div>
    @endif
</div>

<script>
    (function() {
        const snapshotUrl = @json(route('telecaller.panel.snapshot'));
        const realtimeCallStatus = document.getElementById('realtimeCallStatus');
        const activeCallBadge = document.getElementById('activeCallBadge');
        const activeCallCount = document.getElementById('activeCallCount');

        function renderSnapshot(data) {
            if (!data || !data.ok) return;
            const calls = Number(data.active_call_count || 0);
            activeCallCount.textContent = calls;
            realtimeCallStatus.textContent = data.call_status || (calls > 0 ? 'On Call' : 'Idle');
            activeCallBadge.classList.remove('text-bg-danger', 'text-bg-success');
            activeCallBadge.classList.add(calls > 0 ? 'text-bg-danger' : 'text-bg-success');
        }

        async function fetchSnapshot() {
            try {
                const res = await fetch(snapshotUrl, { headers: { 'Accept': 'application/json' } });
                renderSnapshot(await res.json());
            } catch (e) {}
        }

        fetchSnapshot();
        setInterval(fetchSnapshot, 45000);
    })();
</script>

<style>
/* ── Filter Card ─────────────────────────────────── */
.lf-filter-card {
    background: #fff; border: 1px solid var(--border-color);
    border-radius: 14px; padding: 18px 22px;
}
.lf-filter-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
}
.lf-filter-reset {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12px; font-weight: 600; color: #ef4444;
    text-decoration: none;
}
.lf-search-wrap, .lf-select-wrap {
    position: relative;
}
.lf-search-icon, .lf-select-icon {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    font-size: 18px !important; color: var(--text-muted); pointer-events: none; z-index: 2;
}
.lf-search-input, .lf-select {
    padding-left: 36px !important;
    border-radius: 10px !important;
    border-color: var(--border-color) !important;
    font-size: 13px !important;
}
.lf-search-input:focus, .lf-select:focus {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1) !important;
}
.lf-active-pill {
    display: inline-flex; align-items: center; gap: 4px;
    background: #eef2ff; color: #4338ca;
    border: 1px solid #c7d2fe;
    border-radius: 20px; padding: 3px 10px;
    font-size: 12px; font-weight: 600;
}
.lf-pill-close {
    color: #4338ca; font-weight: 700; text-decoration: none; margin-left: 2px;
    font-size: 14px; line-height: 1;
}
.lf-pill-close:hover { color: #ef4444; }

/* ── Table Card ──────────────────────────────────── */
.lf-table-card {
    background: #fff; border: 1px solid var(--border-color);
    border-radius: 14px; overflow: hidden;
}
.lf-table-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border-color);
}
.lf-table-title { font-size: 15px; font-weight: 700; color: var(--text-dark); margin: 0; }
.lf-table-count { font-size: 12px; color: var(--text-muted); margin-left: 8px; }

.lf-table thead th {
    background: var(--background-light);
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    color: var(--text-muted); padding: 10px 14px;
    border-bottom: 1px solid var(--border-color); white-space: nowrap;
}
.lf-table tbody td {
    padding: 13px 14px; vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
    font-size: 13px;
}
.lf-lead-row:last-child td { border-bottom: none; }
.lf-lead-row:hover { background: #fafbff; }

.lf-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    color: #fff; font-size: 14px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.lf-lead-name { font-size: 13.5px; font-weight: 700; color: var(--text-dark); white-space: nowrap; }
.lf-lead-meta {
    display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
    font-size: 11.5px; color: var(--text-muted); margin-top: 2px;
}

.lf-course-pill {
    display: inline-block;
    background: #eef2ff; color: #4338ca;
    border: 1px solid #c7d2fe;
    border-radius: 6px; padding: 2px 8px;
    font-size: 11.5px; font-weight: 600; white-space: nowrap;
}

.lf-view-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 6px 12px; border-radius: 8px;
    font-size: 12px; font-weight: 600;
    background: #f1f5f9; color: var(--text-dark);
    text-decoration: none; transition: all .15s;
    white-space: nowrap;
}
.lf-view-btn:hover { background: var(--primary-color); color: #fff; }

.lf-pagination {
    padding: 12px 16px;
    border-top: 1px solid var(--border-color);
    display: flex; justify-content: center;
}
.lf-pagination .pagination { margin: 0; }

/* ── Empty State ─────────────────────────────────── */
.lf-empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--text-muted);
}
.lf-empty-icon-wrap {
    width: 72px; height: 72px; border-radius: 20px;
    background: #f1f5f9; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
}
.lf-empty-icon-wrap .material-icons { font-size: 36px; color: var(--text-muted); }
.lf-empty-state h4 { font-size: 16px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; }
.lf-empty-state p  { font-size: 13px; margin-bottom: 16px; }

/* ── Stat card-v2 reuse ──────────────────────────── */
.stat-card-v2 .stat-icon { margin-bottom: 0; }
.stat-card-bar {
    height: 3px; border-radius: 2px;
    background: linear-gradient(90deg, var(--bar-color), color-mix(in srgb, var(--bar-color) 60%, transparent));
    width: var(--bar-pct, 50%); margin-top: 12px; transition: width .6s ease;
}
</style>
@endsection
