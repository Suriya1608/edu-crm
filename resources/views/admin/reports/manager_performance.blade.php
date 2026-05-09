@extends('layouts.app')

@section('page_title', 'Manager Performance')

@section('content')

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
            <label class="form-label fw-semibold">Manager</label>
            <select name="manager" class="form-select form-select-sm">
                <option value="all">All Managers</option>
                @foreach (($filterOptions['managers'] ?? collect()) as $mgr)
                    <option value="{{ $mgr->id }}" {{ (string) ($filters['manager'] ?? 'all') === (string) $mgr->id ? 'selected' : '' }}>{{ $mgr->name }}</option>
                @endforeach
            </select>
        </div>
        @php $rp = Auth::user()->role === 'report_viewer' ? 'report_viewer' : 'admin'; @endphp
        <div class="col-md-3 d-flex gap-2 mt-2">
            <button class="btn btn-primary btn-sm w-100">Apply</button>
            <a href="{{ route($rp . '.reports.manager-performance') }}" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
    </form>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-sm btn-outline-secondary"
            href="{{ route($rp . '.reports.export', ['report' => 'manager-performance', 'format' => 'excel'] + request()->query()) }}">
            <span class="material-icons me-1" style="font-size:16px;">file_download</span>Export Excel
        </a>
        <a class="btn btn-sm btn-primary"
            href="{{ route($rp . '.reports.export', ['report' => 'manager-performance', 'format' => 'pdf'] + request()->query()) }}"
            target="_blank">
            <span class="material-icons me-1" style="font-size:16px;">picture_as_pdf</span>Export PDF
        </a>
    </div>
</div>

{{-- KPI Summary Cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue">
                <span class="material-icons">manage_accounts</span>
            </div>
            <div class="stat-value">{{ $summary['total_managers'] }}</div>
            <div class="stat-label">Active Managers</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon cyan">
                <span class="material-icons">people</span>
            </div>
            <div class="stat-value">{{ number_format($summary['total_leads']) }}</div>
            <div class="stat-label">Total Leads Assigned</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green">
                <span class="material-icons">verified</span>
            </div>
            <div class="stat-value">{{ number_format($summary['total_converted']) }}</div>
            <div class="stat-label">Total Converted</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon amber">
                <span class="material-icons">trending_up</span>
            </div>
            <div class="stat-value">{{ $summary['avg_conversion'] }}%</div>
            <div class="stat-label">Avg Conversion Rate</div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3>Lead Pipeline by Manager</h3>
                <p>Assigned vs Converted vs Active vs Lost</p>
            </div>
            <div style="height:300px">
                <canvas id="pipelineChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <h3>Conversion Rate</h3>
                <p>Per manager comparison</p>
            </div>
            <div style="height:300px">
                <canvas id="conversionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="chart-card">
            <div class="chart-header">
                <h3>Monthly Lead Trend</h3>
                <p>Assigned vs Converted — last 6 months</p>
            </div>
            <div style="height:240px">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Performance Table --}}
<div class="custom-table">
    <div class="table-header">
        <h3>Manager Performance Breakdown</h3>
        <span class="badge bg-light text-dark">{{ $rows->count() }} managers</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:48px">Rank</th>
                    <th>Manager</th>
                    <th class="text-center">Team</th>
                    <th class="text-center">Assigned</th>
                    <th class="text-center">Converted</th>
                    <th class="text-center">Active</th>
                    <th class="text-center">Lost</th>
                    <th class="text-center">Calls</th>
                    <th class="text-center">Avg Talk</th>
                    <th class="text-center">Followup %</th>
                    <th class="text-center">Pending</th>
                    <th class="text-center">Conv %</th>
                    <th style="min-width:150px">Score</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $r)
                    @php
                        $rank  = $i + 1;
                        $score = $r['performance_score'];
                        $scoreColor = $score >= 70 ? '#10b981' : ($score >= 40 ? '#f59e0b' : '#ef4444');
                        $convColor  = $r['conversion_rate'] >= 50 ? 'success' : ($r['conversion_rate'] >= 25 ? 'warning' : 'danger');
                        $fuColor    = $r['followup_rate'] >= 70 ? 'success' : ($r['followup_rate'] >= 40 ? 'warning' : 'danger');
                        $rankBadge  = $rank === 1 ? 'warning' : ($rank === 2 ? 'secondary' : ($rank === 3 ? 'danger' : 'light text-dark'));
                    @endphp
                    <tr>
                        <td>
                            <span class="badge bg-{{ $rankBadge }} fw-bold">#{{ $rank }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $r['name'] }}</div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary rounded-pill">
                                <span class="material-icons" style="font-size:12px;vertical-align:-2px">person</span>
                                {{ $r['team_size'] }}
                            </span>
                        </td>
                        <td class="text-center fw-semibold">{{ number_format($r['assigned']) }}</td>
                        <td class="text-center">
                            <span class="text-success fw-semibold">{{ number_format($r['converted']) }}</span>
                        </td>
                        <td class="text-center">
                            <span class="text-primary fw-semibold">{{ number_format($r['active']) }}</span>
                        </td>
                        <td class="text-center">
                            <span class="text-danger fw-semibold">{{ number_format($r['lost']) }}</span>
                        </td>
                        <td class="text-center">{{ number_format($r['calls']) }}</td>
                        <td class="text-center text-muted small">{{ $r['avg_talk_time'] }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $fuColor }}-subtle text-{{ $fuColor }}">{{ $r['followup_rate'] }}%</span>
                        </td>
                        <td class="text-center">
                            @if ($r['pending_followups'] > 0)
                                <span class="badge bg-danger">{{ $r['pending_followups'] }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $convColor }}-subtle text-{{ $convColor }} fw-semibold">{{ $r['conversion_rate'] }}%</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px;border-radius:4px">
                                    <div class="progress-bar" role="progressbar"
                                        style="width:{{ min(100, $score) }}%;background:{{ $scoreColor }}"
                                        aria-valuenow="{{ $score }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="fw-bold small" style="color:{{ $scoreColor }};min-width:36px">{{ $score }}</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center py-5 text-muted">
                            <span class="material-icons d-block mb-2" style="font-size:40px;opacity:.3">bar_chart</span>
                            No manager data found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Score legend --}}
<div class="d-flex gap-3 mt-3 small text-muted">
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#10b981;margin-right:4px"></span>Score ≥ 70: High</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#f59e0b;margin-right:4px"></span>Score 40–69: Medium</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#ef4444;margin-right:4px"></span>Score &lt; 40: Needs Attention</span>
    <span class="ms-3 text-muted">Score = Conversion (40%) + Followup rate (35%) + Call activity (25%)</span>
</div>

<script>
(function () {
    function _init() {
    const rows = @json($rows);
    const names = rows.map(r => r.name);

    // Pipeline bar chart
    new Chart(document.getElementById('pipelineChart'), {
        type: 'bar',
        data: {
            labels: names,
            datasets: [
                { label: 'Assigned',  data: rows.map(r => r.assigned),  backgroundColor: '#6366f1', borderRadius: 4 },
                { label: 'Converted', data: rows.map(r => r.converted), backgroundColor: '#10b981', borderRadius: 4 },
                { label: 'Active',    data: rows.map(r => r.active),    backgroundColor: '#06b6d4', borderRadius: 4 },
                { label: 'Lost',      data: rows.map(r => r.lost),      backgroundColor: '#ef4444', borderRadius: 4 },
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // Conversion rate horizontal bar
    new Chart(document.getElementById('conversionChart'), {
        type: 'bar',
        data: {
            labels: names,
            datasets: [{
                label: 'Conversion %',
                data: rows.map(r => r.conversion_rate),
                backgroundColor: rows.map(r => r.conversion_rate >= 50 ? '#10b981' : r.conversion_rate >= 25 ? '#f59e0b' : '#ef4444'),
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } }
            }
        }
    });

    // Monthly trend
    const monthLabels   = @json($monthLabels);
    const monthAssigned = @json($monthAssigned);
    const monthConverted = @json($monthConverted);

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [
                {
                    label: 'Assigned',
                    data: monthAssigned,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.12)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 4,
                },
                {
                    label: 'Converted',
                    data: monthConverted,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.10)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 4,
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    } // end _init
    if (typeof Chart !== 'undefined') {
        _init();
    } else {
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        s.onload = _init;
        document.head.appendChild(s);
    }
})();
</script>
@endsection
