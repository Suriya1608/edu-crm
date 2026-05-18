@extends('layouts.app')

@section('page_title', 'Telecaller Lead Activity')

@section('content')
<style>
    .lead-toggle[aria-expanded="true"] { background: #f0f4ff !important; }
    .lead-toggle[aria-expanded="true"] .lead-chevron { transform: rotate(180deg); }
</style>

@php
    $rp = Auth::user()->role === 'report_viewer' ? 'report_viewer' : 'admin';
    $exportBase = request()->query();
@endphp

{{-- Back breadcrumb --}}
<div class="mb-3">
    <a href="{{ route($rp . '.reports.telecaller-performance') }}"
        class="btn btn-sm btn-outline-secondary">
        <span class="material-icons me-1" style="font-size:15px;vertical-align:-3px">arrow_back</span>
        Back to Performance Overview
    </a>
</div>

{{-- Filters --}}
<div class="chart-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Time Period</label>
            <select name="date_range" class="form-select form-select-sm">
                <option value="7"       {{ ($filters['date_range'] ?? '30') === '7'       ? 'selected' : '' }}>Last 7 Days</option>
                <option value="30"      {{ ($filters['date_range'] ?? '30') === '30'      ? 'selected' : '' }}>Last 30 Days</option>
                <option value="90"      {{ ($filters['date_range'] ?? '30') === '90'      ? 'selected' : '' }}>Last 90 Days</option>
                <option value="quarter" {{ ($filters['date_range'] ?? '30') === 'quarter' ? 'selected' : '' }}>This Quarter</option>
                <option value="year"    {{ ($filters['date_range'] ?? '30') === 'year'    ? 'selected' : '' }}>This Year</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Lead Source</label>
            <select name="source" class="form-select form-select-sm">
                <option value="all">All Sources</option>
                @foreach (($filterOptions['sources'] ?? collect()) as $source)
                    <option value="{{ $source }}" {{ ($filters['source'] ?? 'all') === $source ? 'selected' : '' }}>{{ $source }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Telecaller</label>
            <select name="telecaller" class="form-select form-select-sm">
                <option value="all">All Telecallers</option>
                @foreach (($filterOptions['telecallers'] ?? collect()) as $tc)
                    <option value="{{ $tc->id }}" {{ (string) ($filters['telecaller'] ?? 'all') === (string) $tc->id ? 'selected' : '' }}>{{ $tc->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm w-100">Apply</button>
            <a href="{{ route($rp . '.reports.telecaller-lead-activity') }}" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
        {{-- Search row --}}
        <div class="col-12 mt-1">
            <label class="form-label fw-semibold">Search Lead</label>
            <div class="input-group input-group-sm" style="max-width:480px">
                <span class="input-group-text bg-white border-end-0">
                    <span class="material-icons text-muted" style="font-size:16px">search</span>
                </span>
                <input type="text" name="search" id="leadSearchInput"
                       class="form-control border-start-0"
                       placeholder="Lead ID, name, email or mobile..."
                       value="{{ $filters['search'] ?? '' }}">
                @if(!empty($filters['search']))
                <a href="{{ route($rp . '.reports.telecaller-lead-activity', array_diff_key($exportBase, ['search' => ''])) }}"
                   class="btn btn-outline-secondary" title="Clear search">
                    <span class="material-icons" style="font-size:15px;vertical-align:-3px">close</span>
                </a>
                @endif
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-sm btn-outline-success"
            href="{{ route($rp . '.reports.telecaller-lead-activity.export', array_merge(['format' => 'excel'], $exportBase)) }}">
            <span class="material-icons me-1" style="font-size:15px;vertical-align:-2px">file_download</span>Export Excel
        </a>
        <a class="btn btn-sm btn-primary"
            href="{{ route($rp . '.reports.telecaller-lead-activity.export', array_merge(['format' => 'pdf'], $exportBase)) }}"
            target="_blank">
            <span class="material-icons me-1" style="font-size:15px;vertical-align:-2px">picture_as_pdf</span>Export PDF
        </a>
    </div>
</div>

{{-- Telecaller blocks --}}
@forelse ($telecallers as $tc)
@php
    $leads = collect($tc['leads']);
    $tcId  = $tc['id'];
    $totalCalls    = $leads->sum('call_count');
    $totalMsgs     = $leads->sum('msg_count');
    $totalMeetings = $leads->sum('meeting_count');
    $converted     = $leads->where('status', 'converted')->count();
@endphp
<div class="chart-card mb-4" id="tc-{{ $tcId }}">

    {{-- Telecaller header --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;font-weight:700;flex-shrink:0">
                {{ strtoupper(substr($tc['name'], 0, 1)) }}
            </div>
            <div>
                <div class="fw-bold" style="font-size:1rem;color:#0f172a">{{ $tc['name'] }}</div>
                <div class="text-muted small">{{ $leads->count() }} leads in this period</div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge" style="background:#6366f115;color:#6366f1;font-size:12px;padding:5px 10px">
                <span class="material-icons" style="font-size:13px;vertical-align:-2px">groups</span>
                {{ $leads->count() }} Leads
            </span>
            <span class="badge" style="background:#10b98115;color:#10b981;font-size:12px;padding:5px 10px">
                <span class="material-icons" style="font-size:13px;vertical-align:-2px">verified</span>
                {{ $converted }} Converted
            </span>
            <span class="badge" style="background:#06b6d415;color:#06b6d4;font-size:12px;padding:5px 10px">
                <span class="material-icons" style="font-size:13px;vertical-align:-2px">call</span>
                {{ $totalCalls }} Calls
            </span>
            <span class="badge" style="background:#25d36615;color:#25d366;font-size:12px;padding:5px 10px">
                <span class="material-icons" style="font-size:13px;vertical-align:-2px">chat</span>
                {{ $totalMsgs }} Messages
            </span>
            <span class="badge" style="background:#f59e0b15;color:#f59e0b;font-size:12px;padding:5px 10px">
                <span class="material-icons" style="font-size:13px;vertical-align:-2px">event</span>
                {{ $totalMeetings }} Meetings
            </span>
        </div>
    </div>

    {{-- Leads accordion --}}
    <div data-tc-leads-container="{{ $tcId }}">
    @forelse ($tc['leads'] as $leadIdx => $lead)
    @php
        $accordionId = 'lead-' . $lead['id'];
        $statusColor = match($lead['status']) {
            'converted'     => '#10b981',
            'lost'          => '#ef4444',
            'active'        => '#6366f1',
            'interested'    => '#06b6d4',
            'not_interested'=> '#94a3b8',
            'callback'      => '#f59e0b',
            default         => '#94a3b8',
        };
        $statusBg = $statusColor . '18';
    @endphp
    <div class="border rounded mb-2" data-lead-row style="border-color:#e2e8f0!important;overflow:hidden">

        {{-- Lead row header (clickable toggle) --}}
        <div class="lead-toggle d-flex align-items-center gap-3 px-3 py-2"
            role="button"
            style="background:#f8fafc;cursor:pointer;user-select:none"
            data-bs-toggle="collapse"
            data-bs-target="#{{ $accordionId }}"
            aria-expanded="false"
            aria-controls="{{ $accordionId }}">
            <div style="min-width:80px">
                <span class="fw-bold" style="font-size:0.75rem;color:#6366f1">{{ $lead['lead_code'] }}</span>
            </div>
            <div class="flex-grow-1">
                <div class="fw-semibold" style="font-size:0.875rem;color:#0f172a">{{ $lead['name'] }}</div>
                <div class="text-muted" style="font-size:0.75rem">
                    {{ $lead['phone'] }}
                    @if($lead['source']) &nbsp;&bull;&nbsp; {{ $lead['source'] }} @endif
                    @if($lead['course'] !== '—') &nbsp;&bull;&nbsp; {{ $lead['course'] }} @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <span class="badge" style="background:{{ $statusBg }};color:{{ $statusColor }};font-size:11px">
                    {{ ucfirst(str_replace('_', ' ', $lead['status'])) }}
                </span>
                @if($lead['final_course'] !== '—')
                <span class="badge bg-success-subtle text-success" style="font-size:11px">
                    Final: {{ $lead['final_course'] }}
                </span>
                @endif
                <span class="badge bg-light text-secondary" style="font-size:10px">
                    {{ $lead['created_at'] }}
                </span>
                <div class="d-flex gap-1">
                    @if($lead['call_count'] > 0)
                    <span class="badge" style="background:#06b6d418;color:#06b6d4;font-size:10px">
                        <span class="material-icons" style="font-size:10px;vertical-align:-1px">call</span>
                        {{ $lead['call_count'] }}
                    </span>
                    @endif
                    @if($lead['msg_count'] > 0)
                    <span class="badge" style="background:#25d36618;color:#25d366;font-size:10px">
                        <span class="material-icons" style="font-size:10px;vertical-align:-1px">chat</span>
                        {{ $lead['msg_count'] }}
                    </span>
                    @endif
                    @if($lead['meeting_count'] > 0)
                    <span class="badge" style="background:#f59e0b18;color:#f59e0b;font-size:10px">
                        <span class="material-icons" style="font-size:10px;vertical-align:-1px">event</span>
                        {{ $lead['meeting_count'] }}
                    </span>
                    @endif
                </div>
                <span class="material-icons text-muted lead-chevron" style="font-size:18px;transition:transform .25s">expand_more</span>
            </div>
        </div>

        {{-- Collapsible detail body --}}
        <div class="collapse" id="{{ $accordionId }}">
            <div class="px-3 py-3" style="background:#fff">

                {{-- Tabs --}}
                <ul class="nav nav-tabs nav-tabs-sm mb-3" id="tabs-{{ $lead['id'] }}" role="tablist" style="border-bottom:1px solid #e2e8f0">
                    <li class="nav-item">
                        <button class="nav-link active px-3 py-1" style="font-size:0.8rem"
                            data-bs-toggle="tab"
                            data-bs-target="#tab-calls-{{ $lead['id'] }}"
                            type="button">
                            <span class="material-icons me-1" style="font-size:14px;vertical-align:-3px;color:#06b6d4">call</span>
                            Calls <span class="badge bg-secondary ms-1" style="font-size:9px">{{ $lead['call_count'] }}</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link px-3 py-1" style="font-size:0.8rem"
                            data-bs-toggle="tab"
                            data-bs-target="#tab-msgs-{{ $lead['id'] }}"
                            type="button">
                            <span class="material-icons me-1" style="font-size:14px;vertical-align:-3px;color:#25d366">chat</span>
                            Messages <span class="badge bg-secondary ms-1" style="font-size:9px">{{ $lead['msg_count'] }}</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link px-3 py-1" style="font-size:0.8rem"
                            data-bs-toggle="tab"
                            data-bs-target="#tab-meetings-{{ $lead['id'] }}"
                            type="button">
                            <span class="material-icons me-1" style="font-size:14px;vertical-align:-3px;color:#f59e0b">event</span>
                            Meetings <span class="badge bg-secondary ms-1" style="font-size:9px">{{ $lead['meeting_count'] }}</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    {{-- CALLS TAB --}}
                    <div class="tab-pane fade show active" id="tab-calls-{{ $lead['id'] }}">
                        @if(count($lead['calls']) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:0.8rem">
                                <thead style="background:#f1f5f9">
                                    <tr>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Date &amp; Time</th>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Direction</th>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Status</th>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Outcome</th>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lead['calls'] as $call)
                                    @php
                                        $dirColor = $call['direction'] === 'outbound' ? '#6366f1' : '#10b981';
                                        $statusBadge = match($call['status']) {
                                            'completed'  => 'success',
                                            'no-answer'  => 'warning',
                                            'busy'       => 'warning',
                                            'missed'     => 'danger',
                                            'failed'     => 'danger',
                                            'canceled'   => 'secondary',
                                            default      => 'secondary',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="text-muted">{{ $call['date'] }}</td>
                                        <td>
                                            <span style="color:{{ $dirColor }};font-weight:600">
                                                <span class="material-icons" style="font-size:12px;vertical-align:-2px">
                                                    {{ $call['direction'] === 'outbound' ? 'call_made' : 'call_received' }}
                                                </span>
                                                {{ ucfirst($call['direction']) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $statusBadge }}-subtle text-{{ $statusBadge }}" style="font-size:10px">
                                                {{ ucfirst($call['status']) }}
                                            </span>
                                        </td>
                                        <td class="text-muted">{{ ucfirst($call['outcome']) }}</td>
                                        <td class="fw-semibold" style="color:#0f172a">{{ $call['duration'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-4 text-muted">
                            <span class="material-icons d-block mb-1" style="font-size:28px;opacity:.3">call_end</span>
                            No calls recorded for this lead.
                        </div>
                        @endif
                    </div>

                    {{-- MESSAGES TAB --}}
                    <div class="tab-pane fade" id="tab-msgs-{{ $lead['id'] }}">
                        @if(count($lead['messages']) > 0)
                        <div style="max-height:300px;overflow-y:auto;display:flex;flex-direction:column;gap:8px">
                            @foreach($lead['messages'] as $msg)
                            @php
                                $isOut = $msg['direction'] === 'outbound';
                            @endphp
                            <div class="d-flex {{ $isOut ? 'justify-content-end' : 'justify-content-start' }}">
                                <div style="max-width:70%;padding:8px 12px;border-radius:{{ $isOut ? '12px 12px 4px 12px' : '12px 12px 12px 4px' }};background:{{ $isOut ? '#6366f1' : '#f1f5f9' }};color:{{ $isOut ? '#fff' : '#0f172a' }};font-size:0.8rem">
                                    <div>{{ $msg['body'] ?: ('(' . ucfirst($msg['type']) . ' attachment)') }}</div>
                                    <div style="font-size:10px;opacity:0.7;margin-top:3px;text-align:{{ $isOut ? 'right' : 'left' }}">
                                        {{ $msg['date'] }}
                                        @if($msg['type'] !== 'text')
                                        &nbsp;&bull;&nbsp;{{ ucfirst($msg['type']) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-4 text-muted">
                            <span class="material-icons d-block mb-1" style="font-size:28px;opacity:.3">chat_bubble_outline</span>
                            No WhatsApp messages for this lead.
                        </div>
                        @endif
                    </div>

                    {{-- MEETINGS TAB --}}
                    <div class="tab-pane fade" id="tab-meetings-{{ $lead['id'] }}">
                        @if(count($lead['meetings']) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:0.8rem">
                                <thead style="background:#f1f5f9">
                                    <tr>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Title</th>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Date &amp; Time</th>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Type</th>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Status</th>
                                        <th class="text-muted fw-semibold" style="font-size:0.7rem">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lead['meetings'] as $mt)
                                    @php
                                        $mtStatusColor = match($mt['status']) {
                                            'completed' => 'success',
                                            'scheduled' => 'primary',
                                            'cancelled', 'canceled' => 'danger',
                                            'rescheduled' => 'warning',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold" style="color:#0f172a">{{ $mt['title'] }}</td>
                                        <td class="text-muted">{{ $mt['time'] }}</td>
                                        <td>
                                            <span class="badge bg-light text-secondary" style="font-size:10px">
                                                {{ ucfirst($mt['type']) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $mtStatusColor }}-subtle text-{{ $mtStatusColor }}" style="font-size:10px">
                                                {{ ucfirst($mt['status']) }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">{{ $mt['notes'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-4 text-muted">
                            <span class="material-icons d-block mb-1" style="font-size:28px;opacity:.3">event_busy</span>
                            No meetings scheduled for this lead.
                        </div>
                        @endif
                    </div>

                </div>{{-- /tab-content --}}
            </div>
        </div>

    </div>
    @empty
    <div class="text-center py-5 text-muted">
        <span class="material-icons d-block mb-2" style="font-size:36px;opacity:.3">inbox</span>
        No leads found for this telecaller in the selected period.
    </div>
    @endforelse
    </div>{{-- /data-tc-leads-container --}}
</div>
@empty
<div class="chart-card text-center py-5 text-muted">
    <span class="material-icons d-block mb-2" style="font-size:40px;opacity:.3">person_off</span>
    No telecallers found. Adjust your filters.
</div>
@endforelse


<script>
(function () {
    const PER_PAGE = 10;

    document.querySelectorAll('[data-tc-leads-container]').forEach(function (container) {
        var leads = Array.from(container.querySelectorAll('[data-lead-row]'));
        var total = leads.length;
        if (total <= PER_PAGE) return;

        var currentPage = 1;
        var totalPages = Math.ceil(total / PER_PAGE);

        var paginationDiv = document.createElement('div');
        paginationDiv.className = 'd-flex align-items-center justify-content-between mt-2 mb-1 px-1';
        paginationDiv.innerHTML =
            '<span class="text-muted small tc-range-label">Showing 1–' + Math.min(PER_PAGE, total) + ' of ' + total + ' leads</span>' +
            '<div class="d-flex align-items-center gap-2">' +
                '<button class="btn btn-sm btn-outline-secondary tc-prev" style="padding:2px 10px;font-size:12px" disabled>' +
                    '<span class="material-icons" style="font-size:14px;vertical-align:-3px">chevron_left</span> Prev' +
                '</button>' +
                '<span class="text-muted small tc-page-label">Page 1 / ' + totalPages + '</span>' +
                '<button class="btn btn-sm btn-outline-secondary tc-next" style="padding:2px 10px;font-size:12px">' +
                    'Next <span class="material-icons" style="font-size:14px;vertical-align:-3px">chevron_right</span>' +
                '</button>' +
            '</div>';
        container.after(paginationDiv);

        function showPage(page) {
            currentPage = page;
            var start = (page - 1) * PER_PAGE;
            var end   = start + PER_PAGE;

            leads.forEach(function (row, i) {
                row.style.display = (i >= start && i < end) ? '' : 'none';
                if (i < start || i >= end) {
                    var collapse = row.querySelector('.collapse.show');
                    if (collapse) {
                        collapse.classList.remove('show');
                        var toggle = row.querySelector('.lead-toggle');
                        if (toggle) toggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });

            paginationDiv.querySelector('.tc-range-label').textContent =
                'Showing ' + (start + 1) + '–' + Math.min(end, total) + ' of ' + total + ' leads';
            paginationDiv.querySelector('.tc-page-label').textContent =
                'Page ' + page + ' / ' + totalPages;
            paginationDiv.querySelector('.tc-prev').disabled = page <= 1;
            paginationDiv.querySelector('.tc-next').disabled = page >= totalPages;
        }

        showPage(1);

        paginationDiv.querySelector('.tc-prev').addEventListener('click', function () {
            if (currentPage > 1) showPage(currentPage - 1);
        });
        paginationDiv.querySelector('.tc-next').addEventListener('click', function () {
            if (currentPage < totalPages) showPage(currentPage + 1);
        });
    });
})();
</script>

@endsection
