@extends('layouts.app')

@section('page_title', 'Telecaller Performance')

@section('content')
@php $rp = Auth::user()->role === 'report_viewer' ? 'report_viewer' : 'admin'; @endphp
<style>
.kpi-card {
    background:#fff;
    border-radius:14px;
    padding:18px 20px;
    box-shadow:0 1px 6px rgba(15,23,42,.07);
    height:100%;
    position:relative;
    overflow:hidden;
}
.kpi-card::before {
    content:'';
    position:absolute;
    top:0; left:0; right:0;
    height:3px;
    border-radius:14px 14px 0 0;
}
.kpi-icon {
    width:40px; height:40px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    margin-bottom:12px;
}
.kpi-value  { font-size:1.75rem; font-weight:800; color:#0f172a; line-height:1.1; }
.kpi-label  { font-size:0.7rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.6px; margin-top:4px; }
.kpi-sub    { font-size:0.75rem; color:#64748b; margin-top:3px; }
.kpi-delta  { font-size:0.72rem; font-weight:600; margin-top:4px; }

.chart-card { background:#fff; border-radius:14px; padding:20px; box-shadow:0 1px 6px rgba(15,23,42,.07); height:100%; }
.chart-card h3 { font-size:.875rem; font-weight:700; color:#0f172a; margin:0 0 2px; }
.chart-card p  { font-size:.75rem; color:#94a3b8; margin:0 0 14px; }

.tc-table thead th { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:9px 10px; }
.tc-table tbody td { padding:10px 10px; border-bottom:1px solid #f8fafc; vertical-align:middle; font-size:.825rem; }
.tc-table tbody tr:hover { background:#fafbff; }

.grade-pill { width:28px; height:28px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:800; }
.g-A { background:#d1fae5; color:#065f46; }
.g-B { background:#dbeafe; color:#1e40af; }
.g-C { background:#fef3c7; color:#92400e; }
.g-D { background:#fee2e2; color:#991b1b; }
</style>

{{-- ═══ FILTER BAR ═══ --}}
<div class="chart-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold small">Time Period</label>
            <select name="date_range" class="form-select form-select-sm">
                <option value="7"       {{ ($filters['date_range'] ?? '30') === '7'       ? 'selected' : '' }}>Last 7 Days</option>
                <option value="30"      {{ ($filters['date_range'] ?? '30') === '30'      ? 'selected' : '' }}>Last 30 Days</option>
                <option value="90"      {{ ($filters['date_range'] ?? '30') === '90'      ? 'selected' : '' }}>Last 90 Days</option>
                <option value="quarter" {{ ($filters['date_range'] ?? '30') === 'quarter' ? 'selected' : '' }}>This Quarter</option>
                <option value="year"    {{ ($filters['date_range'] ?? '30') === 'year'    ? 'selected' : '' }}>This Year</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold small">Lead Source</label>
            <select name="source" class="form-select form-select-sm">
                <option value="all">All Sources</option>
                @foreach (($filterOptions['sources'] ?? collect()) as $src)
                    <option value="{{ $src }}" {{ ($filters['source'] ?? 'all') === $src ? 'selected' : '' }}>{{ $src }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold small">Telecaller</label>
            <select name="telecaller" class="form-select form-select-sm">
                <option value="all">All Telecallers</option>
                @foreach (($filterOptions['telecallers'] ?? collect()) as $tc)
                    <option value="{{ $tc->id }}" {{ (string)($filters['telecaller'] ?? 'all') === (string)$tc->id ? 'selected' : '' }}>{{ $tc->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm w-100">
                <span class="material-icons me-1" style="font-size:14px;vertical-align:-2px">filter_alt</span>Apply
            </button>
            <a href="{{ route($rp . '.reports.telecaller-performance') }}" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
    </form>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route($rp . '.reports.telecaller-lead-activity', request()->query()) }}"
            class="btn btn-sm btn-outline-primary">
            <span class="material-icons me-1" style="font-size:14px;vertical-align:-2px">manage_search</span>Lead Activity
        </a>
        <a class="btn btn-sm btn-outline-success"
            href="{{ route($rp . '.reports.export', ['report' => 'telecaller-performance', 'format' => 'excel'] + request()->query()) }}">
            <span class="material-icons me-1" style="font-size:14px;vertical-align:-2px">file_download</span>Export Excel
        </a>
        <a class="btn btn-sm btn-primary"
            href="{{ route($rp . '.reports.export', ['report' => 'telecaller-performance', 'format' => 'pdf'] + request()->query()) }}"
            target="_blank">
            <span class="material-icons me-1" style="font-size:14px;vertical-align:-2px">picture_as_pdf</span>Export PDF
        </a>
    </div>
</div>

{{-- ═══ KPI CARDS — ROW 1 ═══ --}}
<div class="row g-3 mb-3">
    {{-- Active Telecallers --}}
    <div class="col-6 col-md-3">
        <div class="kpi-card" style="--accent:#6366f1">
            <div class="kpi-icon" style="background:#6366f118">
                <span class="material-icons" style="color:#6366f1;font-size:20px">support_agent</span>
            </div>
            <div class="kpi-value" style="color:#6366f1">{{ $summary['total_telecallers'] }}</div>
            <div class="kpi-label">Active Telecallers</div>
            <div class="kpi-sub">
                <span style="color:#10b981;font-weight:700">{{ $summary['top_performer'] }}</span>
                <span class="text-muted"> top performer</span>
            </div>
            <style>.kpi-card:nth-child(1)::before{background:#6366f1}</style>
        </div>
    </div>

    {{-- Total Calls --}}
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#06b6d418">
                <span class="material-icons" style="color:#06b6d4;font-size:20px">call</span>
            </div>
            <div class="kpi-value" style="color:#06b6d4">{{ number_format($summary['total_calls']) }}</div>
            <div class="kpi-label">Total Calls Made</div>
            <div class="kpi-sub">
                <span style="color:#10b981;font-weight:600">{{ number_format($summary['total_answered']) }} answered</span>
                &nbsp;·&nbsp;
                <span style="color:#ef4444;font-weight:600">{{ number_format($summary['total_missed']) }} missed</span>
            </div>
        </div>
    </div>

    {{-- Total Converted --}}
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#10b98118">
                <span class="material-icons" style="color:#10b981;font-size:20px">verified</span>
            </div>
            <div class="kpi-value" style="color:#10b981">{{ number_format($summary['total_converted']) }}</div>
            <div class="kpi-label">Total Converted</div>
            <div class="kpi-sub">
                out of <strong>{{ number_format($summary['total_assigned']) }}</strong> assigned
                &nbsp;·&nbsp;
                <span style="color:#6366f1;font-weight:600">{{ $summary['avg_conversion_rate'] }}% avg</span>
            </div>
        </div>
    </div>

    {{-- Total Talk Time --}}
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f59e0b18">
                <span class="material-icons" style="color:#f59e0b;font-size:20px">timer</span>
            </div>
            <div class="kpi-value" style="color:#f59e0b">{{ $summary['total_talk_fmt'] }}</div>
            <div class="kpi-label">Total Talk Time</div>
            <div class="kpi-sub">{{ number_format($summary['total_talk_mins']) }} minutes total</div>
        </div>
    </div>
</div>

{{-- ═══ KPI CARDS — ROW 2 ═══ --}}
<div class="row g-3 mb-3">
    {{-- Avg Answer Rate --}}
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#8b5cf618">
                <span class="material-icons" style="color:#8b5cf6;font-size:20px">call_made</span>
            </div>
            <div class="kpi-value" style="color:#8b5cf6">{{ $summary['avg_answer_rate'] }}%</div>
            <div class="kpi-label">Avg Answer Rate</div>
            <div class="kpi-sub">
                @php $ar = $summary['avg_answer_rate']; @endphp
                <span style="color:{{ $ar >= 75 ? '#10b981' : ($ar >= 50 ? '#f59e0b' : '#ef4444') }};font-weight:600">
                    {{ $ar >= 75 ? 'Excellent' : ($ar >= 50 ? 'Average' : 'Needs Improvement') }}
                </span>
            </div>
        </div>
    </div>

    {{-- Avg Followup Rate --}}
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#06b6d418">
                <span class="material-icons" style="color:#06b6d4;font-size:20px">event_repeat</span>
            </div>
            <div class="kpi-value" style="color:#06b6d4">{{ $summary['avg_followup_rate'] }}%</div>
            <div class="kpi-label">Avg Follow-up Rate</div>
            <div class="kpi-sub">follow-up completion across team</div>
        </div>
    </div>

    {{-- Pending Follow-ups --}}
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#ef444418">
                <span class="material-icons" style="color:#ef4444;font-size:20px">pending_actions</span>
            </div>
            <div class="kpi-value" style="color:#ef4444">{{ number_format($summary['total_pending_fu']) }}</div>
            <div class="kpi-label">Pending Follow-ups</div>
            <div class="kpi-sub">overdue across all telecallers</div>
        </div>
    </div>

    {{-- Top Performer score --}}
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f59e0b18">
                <span class="material-icons" style="color:#f59e0b;font-size:20px">workspace_premium</span>
            </div>
            <div class="kpi-value" style="color:#f59e0b;font-size:1.3rem">{{ $summary['top_performer'] }}</div>
            <div class="kpi-label">Top Performer</div>
            <div class="kpi-sub">
                Efficiency score: <strong style="color:#10b981">{{ $summary['top_score'] }}</strong>
            </div>
        </div>
    </div>
</div>

{{-- ═══ CHARTS ROW 1 ═══ --}}
<div class="row g-3 mb-3">
    {{-- Call Activity --}}
    <div class="col-md-8">
        <div class="chart-card">
            <h3>Call Activity by Telecaller</h3>
            <p>Answered vs Missed call breakdown per agent</p>
            <div style="height:260px"><canvas id="callChart"></canvas></div>
        </div>
    </div>
    {{-- Performance Distribution --}}
    <div class="col-md-4">
        <div class="chart-card">
            <h3>Performance Distribution</h3>
            <p>Team score segmentation</p>
            <div style="height:200px"><canvas id="distChart"></canvas></div>
            <div class="d-flex justify-content-center gap-3 mt-2" style="font-size:.72rem">
                <span><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:#10b981;margin-right:3px"></span>High (A)</span>
                <span><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:#f59e0b;margin-right:3px"></span>Average (B/C)</span>
                <span><span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:#ef4444;margin-right:3px"></span>Needs Attention (D)</span>
            </div>
        </div>
    </div>
</div>

{{-- ═══ CHARTS ROW 2 ═══ --}}
<div class="row g-3 mb-3">
    {{-- Conversion Rate --}}
    <div class="col-md-4">
        <div class="chart-card">
            <h3>Conversion Rate</h3>
            <p>Per telecaller — leads converted %</p>
            <div style="height:240px"><canvas id="convChart"></canvas></div>
        </div>
    </div>
    {{-- Follow-up Rate --}}
    <div class="col-md-4">
        <div class="chart-card">
            <h3>Follow-up Completion Rate</h3>
            <p>% of scheduled follow-ups completed</p>
            <div style="height:240px"><canvas id="fuChart"></canvas></div>
        </div>
    </div>
    {{-- Efficiency Score --}}
    <div class="col-md-4">
        <div class="chart-card">
            <h3>Efficiency Score Ranking</h3>
            <p>Composite score out of 100</p>
            <div style="height:240px"><canvas id="effChart"></canvas></div>
        </div>
    </div>
</div>

{{-- ═══ CHARTS ROW 3 ═══ --}}
<div class="row g-3 mb-3">
    {{-- Talk Time --}}
    <div class="col-md-6">
        <div class="chart-card">
            <h3>Total Talk Time by Telecaller</h3>
            <p>Minutes spent on answered calls</p>
            <div style="height:240px"><canvas id="talkChart"></canvas></div>
        </div>
    </div>
    {{-- Monthly Trend --}}
    <div class="col-md-6">
        <div class="chart-card">
            <h3>Monthly Lead &amp; Call Trend</h3>
            <p>Assigned, Converted &amp; Calls — last 6 months</p>
            <div style="height:240px"><canvas id="trendChart"></canvas></div>
        </div>
    </div>
</div>

{{-- ═══ PERFORMANCE TABLE ═══ --}}
<div class="custom-table">
    <div class="table-header">
        <h3>
            <span class="material-icons me-2" style="vertical-align:-5px;font-size:20px">leaderboard</span>
            Telecaller Performance Breakdown
        </h3>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark" style="font-size:11px">{{ $rows->count() }} telecallers</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle tc-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>Telecaller</th>
                    <th class="text-center" style="width:44px">Grade</th>
                    <th class="text-center">Assigned</th>
                    <th class="text-center">Conv.</th>
                    <th class="text-center">Active</th>
                    <th class="text-center">Lost</th>
                    <th class="text-center">Calls</th>
                    <th class="text-center">Answered</th>
                    <th class="text-center">Missed</th>
                    <th class="text-center">Answer %</th>
                    <th class="text-center">Avg Talk</th>
                    <th class="text-center">Calls/Lead</th>
                    <th class="text-center">Followup %</th>
                    <th class="text-center">Pending</th>
                    <th class="text-center">Conv %</th>
                    <th style="min-width:150px">Score</th>
                    <th class="text-center">Activity</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $r)
                @php
                    $rank       = $i + 1;
                    $score      = $r['efficiency_score'];
                    $scoreColor = $score >= 70 ? '#10b981' : ($score >= 40 ? '#f59e0b' : '#ef4444');
                    $convColor  = $r['conversion_rate'] >= 50 ? 'success' : ($r['conversion_rate'] >= 25 ? 'warning' : 'danger');
                    $fuColor    = $r['followup_rate'] >= 70 ? 'success' : ($r['followup_rate'] >= 40 ? 'warning' : 'danger');
                    $ansColor   = $r['answer_rate'] >= 75 ? 'success' : ($r['answer_rate'] >= 50 ? 'warning' : 'danger');
                    $rankIcon   = ['workspace_premium','military_tech','emoji_events'];
                    $rankColor  = ['#f59e0b','#94a3b8','#b45309'];
                @endphp
                <tr>
                    <td class="text-center">
                        @if ($rank <= 3)
                            <span class="material-icons" style="font-size:20px;color:{{ $rankColor[$rank-1] }}">{{ $rankIcon[$rank-1] }}</span>
                        @else
                            <span class="fw-bold text-muted small">#{{ $rank }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold" style="color:#0f172a">{{ $r['name'] }}</div>
                        <div class="text-muted" style="font-size:0.72rem">{{ number_format($r['total_talk_mins']) }} min talk</div>
                    </td>
                    <td class="text-center">
                        <span class="grade-pill g-{{ $r['grade'] }}">{{ $r['grade'] }}</span>
                    </td>
                    <td class="text-center fw-semibold">{{ number_format($r['assigned']) }}</td>
                    <td class="text-center"><span class="fw-bold" style="color:#10b981">{{ number_format($r['converted']) }}</span></td>
                    <td class="text-center"><span style="color:#6366f1">{{ number_format($r['active']) }}</span></td>
                    <td class="text-center"><span style="color:#ef4444">{{ number_format($r['lost']) }}</span></td>
                    <td class="text-center fw-semibold">{{ number_format($r['calls']) }}</td>
                    <td class="text-center" style="color:#10b981">{{ number_format($r['answered']) }}</td>
                    <td class="text-center" style="color:#ef4444">{{ number_format($r['missed']) }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $ansColor }}-subtle text-{{ $ansColor }}" style="font-size:10px">{{ $r['answer_rate'] }}%</span>
                    </td>
                    <td class="text-center text-muted small">{{ $r['avg_talk_time'] }}</td>
                    <td class="text-center">
                        <span class="badge bg-light text-secondary" style="font-size:10px">{{ $r['calls_per_lead'] }}x</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-{{ $fuColor }}-subtle text-{{ $fuColor }}" style="font-size:10px">{{ $r['followup_rate'] }}%</span>
                    </td>
                    <td class="text-center">
                        @if ($r['pending_followups'] > 0)
                            <span class="badge bg-danger" style="font-size:10px">{{ $r['pending_followups'] }}</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-{{ $convColor }}-subtle text-{{ $convColor }} fw-bold" style="font-size:10px">{{ $r['conversion_rate'] }}%</span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:8px;border-radius:4px">
                                <div class="progress-bar" style="width:{{ min(100,$score) }}%;background:{{ $scoreColor }};border-radius:4px"></div>
                            </div>
                            <span class="fw-bold small" style="color:{{ $scoreColor }};min-width:30px">{{ $score }}</span>
                        </div>
                    </td>
                    <td class="text-center">
                        <a href="{{ route($rp . '.reports.telecaller-lead-activity', array_merge(request()->query(), ['telecaller' => $r['id']])) }}"
                            class="btn btn-sm btn-outline-primary" style="font-size:11px;padding:3px 8px"
                            title="View lead-by-lead activity">
                            <span class="material-icons" style="font-size:13px;vertical-align:-2px">open_in_new</span>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="18" class="text-center py-5 text-muted">
                        <span class="material-icons d-block mb-2" style="font-size:40px;opacity:.3">bar_chart</span>
                        No telecaller data found for the selected filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Score legend --}}
<div class="d-flex flex-wrap gap-3 mt-3 small text-muted align-items-center">
    <span><span class="grade-pill g-A me-1" style="width:20px;height:20px;font-size:10px">A</span>Score ≥ 70: High Performer</span>
    <span><span class="grade-pill g-B me-1" style="width:20px;height:20px;font-size:10px">B</span>Score 40–69: Average</span>
    <span><span class="grade-pill g-C me-1" style="width:20px;height:20px;font-size:10px">C</span>Score 20–39: Below Average</span>
    <span><span class="grade-pill g-D me-1" style="width:20px;height:20px;font-size:10px">D</span>Score &lt; 20: Needs Attention</span>
    <span class="ms-auto text-muted" style="font-size:0.7rem">Score = Conversion (40%) + Followup rate (35%) + Answer rate (25%)</span>
</div>

<script>
(function () {
    function _init() {
        const rows        = @json($rows);
        const dist        = @json($perfDist);
        const monthLabels = @json($monthLabels);
        const monthAsgn   = @json($monthAssigned);
        const monthConv   = @json($monthConverted);
        const monthCalls  = @json($monthCalls);

        const names = rows.map(r => r.name);
        const GRID  = { color: 'rgba(0,0,0,.04)' };
        const TICK  = { color: '#94a3b8', font: { size: 10 } };

        /* ── 1. Call Activity ── */
        new Chart(document.getElementById('callChart'), {
            type: 'bar',
            data: {
                labels: names,
                datasets: [
                    { label: 'Answered', data: rows.map(r => r.answered), backgroundColor: '#10b981', borderRadius: 4 },
                    { label: 'Missed',   data: rows.map(r => r.missed),   backgroundColor: '#ef4444', borderRadius: 4 },
                ]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, ticks: { ...TICK, precision: 0 }, grid: GRID },
                    x: { ticks: TICK, grid: { display: false } }
                }
            }
        });

        /* ── 2. Performance Distribution doughnut ── */
        new Chart(document.getElementById('distChart'), {
            type: 'doughnut',
            data: {
                labels: ['High (A)', 'Average (B/C)', 'Needs Attention (D)'],
                datasets: [{
                    data: [dist.high, dist.average, dist.low],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} telecaller(s)` } }
                }
            }
        });

        /* ── 3. Conversion Rate ── */
        new Chart(document.getElementById('convChart'), {
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
                    x: { beginAtZero: true, max: 100, ticks: { ...TICK, callback: v => v + '%' }, grid: GRID },
                    y: { ticks: TICK, grid: { display: false } }
                }
            }
        });

        /* ── 4. Follow-up Rate ── */
        new Chart(document.getElementById('fuChart'), {
            type: 'bar',
            data: {
                labels: names,
                datasets: [{
                    label: 'Followup %',
                    data: rows.map(r => r.followup_rate),
                    backgroundColor: rows.map(r => r.followup_rate >= 70 ? '#06b6d4' : r.followup_rate >= 40 ? '#8b5cf6' : '#94a3b8'),
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, max: 100, ticks: { ...TICK, callback: v => v + '%' }, grid: GRID },
                    y: { ticks: TICK, grid: { display: false } }
                }
            }
        });

        /* ── 5. Efficiency Score ── */
        new Chart(document.getElementById('effChart'), {
            type: 'bar',
            data: {
                labels: names,
                datasets: [{
                    label: 'Efficiency Score',
                    data: rows.map(r => r.efficiency_score),
                    backgroundColor: rows.map(r =>
                        r.efficiency_score >= 70 ? 'rgba(16,185,129,.85)' :
                        r.efficiency_score >= 40 ? 'rgba(245,158,11,.85)' : 'rgba(239,68,68,.85)'
                    ),
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, max: 100, ticks: TICK, grid: GRID },
                    y: { ticks: TICK, grid: { display: false } }
                }
            }
        });

        /* ── 6. Talk Time ── */
        new Chart(document.getElementById('talkChart'), {
            type: 'bar',
            data: {
                labels: names,
                datasets: [{
                    label: 'Talk Time (min)',
                    data: rows.map(r => r.total_talk_mins),
                    backgroundColor: 'rgba(99,102,241,.8)',
                    borderRadius: 4,
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { ...TICK, callback: v => v + 'm' }, grid: GRID },
                    x: { ticks: TICK, grid: { display: false } }
                }
            }
        });

        /* ── 7. Monthly Trend ── */
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Assigned',
                        data: monthAsgn,
                        borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.10)',
                        borderWidth: 2, tension: 0.35, fill: true, pointRadius: 4,
                    },
                    {
                        label: 'Converted',
                        data: monthConv,
                        borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.08)',
                        borderWidth: 2, tension: 0.35, fill: true, pointRadius: 4,
                    },
                    {
                        label: 'Calls',
                        data: monthCalls,
                        borderColor: '#06b6d4', backgroundColor: 'transparent',
                        borderWidth: 2, borderDash: [4,3], tension: 0.35, fill: false, pointRadius: 3,
                    },
                ]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, ticks: { ...TICK, precision: 0 }, grid: GRID },
                    x: { ticks: TICK, grid: { display: false } }
                }
            }
        });
    }

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
