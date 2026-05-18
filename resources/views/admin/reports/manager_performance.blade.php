@extends('layouts.app')

@section('page_title', 'Manager Performance')

@section('content')
@php $rp = Auth::user()->role === 'report_viewer' ? 'report_viewer' : 'admin'; @endphp
<style>
.kpi-card { background:#fff; border-radius:14px; padding:18px 20px; box-shadow:0 1px 6px rgba(15,23,42,.07); height:100%; position:relative; overflow:hidden; }
.kpi-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; }
.kpi-value { font-size:1.65rem; font-weight:800; color:#0f172a; line-height:1.1; }
.kpi-label { font-size:0.7rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.6px; margin-top:4px; }
.kpi-sub   { font-size:0.75rem; color:#64748b; margin-top:3px; }
.chart-card  { background:#fff; border-radius:14px; padding:20px; box-shadow:0 1px 6px rgba(15,23,42,.07); height:100%; }
.chart-card h3 { font-size:.875rem; font-weight:700; color:#0f172a; margin:0 0 2px; }
.chart-card p  { font-size:.75rem; color:#94a3b8; margin:0 0 14px; }
.grade-pill { width:28px; height:28px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:800; }
.g-A { background:#d1fae5; color:#065f46; } .g-B { background:#dbeafe; color:#1e40af; }
.g-C { background:#fef3c7; color:#92400e; } .g-D { background:#fee2e2; color:#991b1b; }
.mgr-table thead th { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.4px; background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:9px 8px; }
.mgr-table tbody td { padding:9px 8px; border-bottom:1px solid #f8fafc; vertical-align:middle; font-size:.82rem; }
.mgr-table tbody tr:hover td { background:#fafbff; }
/* telecaller sub-rows */
.tc-sub-row td { background:#f8fafc; font-size:.75rem; border-bottom:1px solid #f1f5f9; padding:6px 8px; }
.tc-sub-row td:first-child { padding-left:56px; }
.tc-sub-header td { background:#f1f5f9; font-size:.7rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.4px; padding:5px 8px; border-bottom:1px solid #e2e8f0; }
.tc-sub-header td:first-child { padding-left:56px; }
.toggle-btn { background:none; border:none; padding:0; cursor:pointer; }
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
            <label class="form-label fw-semibold small">Manager</label>
            <select name="manager" class="form-select form-select-sm">
                <option value="all">All Managers</option>
                @foreach (($filterOptions['managers'] ?? collect()) as $mgr)
                    <option value="{{ $mgr->id }}" {{ (string)($filters['manager'] ?? 'all') === (string)$mgr->id ? 'selected' : '' }}>{{ $mgr->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm w-100">
                <span class="material-icons me-1" style="font-size:14px;vertical-align:-2px">filter_alt</span>Apply
            </button>
            <a href="{{ route($rp . '.reports.manager-performance') }}" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
    </form>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-sm btn-outline-success"
            href="{{ route($rp . '.reports.export', ['report' => 'manager-performance', 'format' => 'excel'] + request()->query()) }}">
            <span class="material-icons me-1" style="font-size:14px;vertical-align:-2px">file_download</span>Export Excel
        </a>
        <a class="btn btn-sm btn-primary"
            href="{{ route($rp . '.reports.export', ['report' => 'manager-performance', 'format' => 'pdf'] + request()->query()) }}"
            target="_blank">
            <span class="material-icons me-1" style="font-size:14px;vertical-align:-2px">picture_as_pdf</span>Export PDF
        </a>
    </div>
</div>

{{-- ═══ KPI ROW 1 ═══ --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#6366f118">
                <span class="material-icons" style="color:#6366f1;font-size:20px">manage_accounts</span>
            </div>
            <div class="kpi-value" style="color:#6366f1">{{ $summary['total_managers'] }}</div>
            <div class="kpi-label">Active Managers</div>
            <div class="kpi-sub">
                <span style="color:#10b981;font-weight:700">{{ $summary['top_manager'] }}</span>
                <span class="text-muted"> top performer</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#06b6d418">
                <span class="material-icons" style="color:#06b6d4;font-size:20px">people</span>
            </div>
            <div class="kpi-value" style="color:#06b6d4">{{ number_format($summary['total_leads']) }}</div>
            <div class="kpi-label">Total Leads Assigned</div>
            <div class="kpi-sub">
                <span style="color:#10b981;font-weight:600">{{ number_format($summary['total_converted']) }} converted</span>
                &nbsp;·&nbsp;
                <span style="color:#6366f1;font-weight:600">{{ $summary['avg_conversion'] }}% avg</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#10b98118">
                <span class="material-icons" style="color:#10b981;font-size:20px">call</span>
            </div>
            <div class="kpi-value" style="color:#10b981">{{ number_format($summary['total_calls']) }}</div>
            <div class="kpi-label">Total Calls</div>
            <div class="kpi-sub">
                <span style="color:#f59e0b;font-weight:600">{{ $summary['total_talk_fmt'] }}</span> talk time
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f59e0b18">
                <span class="material-icons" style="color:#f59e0b;font-size:20px">trending_up</span>
            </div>
            <div class="kpi-value" style="color:#f59e0b">{{ $summary['avg_conversion'] }}%</div>
            <div class="kpi-label">Avg Conversion Rate</div>
            <div class="kpi-sub">
                answer rate: <span style="color:#6366f1;font-weight:600">{{ $summary['avg_answer_rate'] }}%</span>
            </div>
        </div>
    </div>
</div>

{{-- ═══ KPI ROW 2 ═══ --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#8b5cf618">
                <span class="material-icons" style="color:#8b5cf6;font-size:20px">event_repeat</span>
            </div>
            <div class="kpi-value" style="color:#8b5cf6">{{ $summary['avg_followup_rate'] }}%</div>
            <div class="kpi-label">Avg Follow-up Rate</div>
            <div class="kpi-sub">
                <span style="color:#ef4444;font-weight:600">{{ number_format($summary['total_pending_fu']) }}</span> overdue
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#06b6d418">
                <span class="material-icons" style="color:#06b6d4;font-size:20px">event</span>
            </div>
            <div class="kpi-value" style="color:#06b6d4">{{ number_format($summary['total_meetings']) }}</div>
            <div class="kpi-label">Total Meetings</div>
            <div class="kpi-sub">scheduled across all teams</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#25d36618">
                <span class="material-icons" style="color:#25d366;font-size:20px">chat</span>
            </div>
            <div class="kpi-value" style="color:#25d366">{{ number_format($summary['total_messages']) }}</div>
            <div class="kpi-label">WhatsApp Messages</div>
            <div class="kpi-sub">sent &amp; received on leads</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f59e0b18">
                <span class="material-icons" style="color:#f59e0b;font-size:20px">workspace_premium</span>
            </div>
            <div class="kpi-value" style="color:#f59e0b;font-size:1.25rem">{{ $summary['top_manager'] }}</div>
            <div class="kpi-label">Top Performer</div>
            <div class="kpi-sub">Score: <strong style="color:#10b981">{{ $summary['top_score'] }}</strong></div>
        </div>
    </div>
</div>

{{-- ═══ CHARTS ROW 1 ═══ --}}
<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="chart-card">
            <h3>Lead Pipeline by Manager</h3>
            <p>Assigned vs Converted vs Active vs Lost</p>
            <div style="height:260px"><canvas id="pipelineChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card">
            <h3>Performance Distribution</h3>
            <p>Manager score segmentation</p>
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
    <div class="col-md-4">
        <div class="chart-card">
            <h3>Conversion Rate</h3>
            <p>Per manager — leads converted %</p>
            <div style="height:240px"><canvas id="convChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card">
            <h3>Call Breakdown</h3>
            <p>Inbound / Outbound / Missed per manager</p>
            <div style="height:240px"><canvas id="callChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card">
            <h3>Follow-up &amp; Score</h3>
            <p>Followup rate vs performance score</p>
            <div style="height:240px"><canvas id="fuScoreChart"></canvas></div>
        </div>
    </div>
</div>

{{-- ═══ CHARTS ROW 3 ═══ --}}
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="chart-card">
            <h3>Meetings &amp; Messages</h3>
            <p>Meeting count and WhatsApp messages per manager</p>
            <div style="height:220px"><canvas id="meetMsgChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="chart-card">
            <h3>Monthly Lead &amp; Call Trend</h3>
            <p>Assigned, Converted &amp; Calls — last 6 months</p>
            <div style="height:220px"><canvas id="trendChart"></canvas></div>
        </div>
    </div>
</div>

{{-- ═══ PERFORMANCE TABLE ═══ --}}
<div class="custom-table">
    <div class="table-header">
        <h3>
            <span class="material-icons me-2" style="vertical-align:-5px;font-size:20px">leaderboard</span>
            Manager Performance Breakdown
        </h3>
        <span class="badge bg-light text-dark" style="font-size:11px">{{ $rows->count() }} managers</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle mgr-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>Manager</th>
                    <th class="text-center">Grade</th>
                    <th class="text-center">Team</th>
                    <th class="text-center">Assigned</th>
                    <th class="text-center">Conv.</th>
                    <th class="text-center">Active</th>
                    <th class="text-center">Lost</th>
                    <th class="text-center">Calls</th>
                    <th class="text-center">In/Out/Miss</th>
                    <th class="text-center">Talk Time</th>
                    <th class="text-center">Meetings</th>
                    <th class="text-center">Messages</th>
                    <th class="text-center">Followup %</th>
                    <th class="text-center">Pending</th>
                    <th class="text-center">Avg Response</th>
                    <th class="text-center">Conv %</th>
                    <th style="min-width:150px">Score</th>
                    <th class="text-center">Team Detail</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $r)
                @php
                    $rank       = $i + 1;
                    $score      = $r['performance_score'];
                    $scoreColor = $score >= 70 ? '#10b981' : ($score >= 40 ? '#f59e0b' : '#ef4444');
                    $convColor  = $r['conversion_rate'] >= 50 ? 'success' : ($r['conversion_rate'] >= 25 ? 'warning' : 'danger');
                    $fuColor    = $r['followup_rate'] >= 70 ? 'success' : ($r['followup_rate'] >= 40 ? 'warning' : 'danger');
                    $rankIcons  = ['workspace_premium','military_tech','emoji_events'];
                    $rankColors = ['#f59e0b','#94a3b8','#b45309'];
                    $tcRows     = $r['telecaller_breakdown'] ?? [];
                    $collapseId = 'tc-' . $r['id'];
                @endphp
                <tr>
                    <td class="text-center">
                        @if($rank <= 3)
                            <span class="material-icons" style="font-size:20px;color:{{ $rankColors[$rank-1] }}">{{ $rankIcons[$rank-1] }}</span>
                        @else
                            <span class="fw-bold text-muted small">#{{ $rank }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold" style="color:#0f172a">{{ $r['name'] }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $r['total_talk_fmt'] }} talk</div>
                    </td>
                    <td class="text-center">
                        <span class="grade-pill g-{{ $r['grade'] }}">{{ $r['grade'] }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size:11px">
                            <span class="material-icons" style="font-size:11px;vertical-align:-2px">person</span>
                            {{ $r['team_size'] }}
                        </span>
                    </td>
                    <td class="text-center fw-semibold">{{ number_format($r['assigned']) }}</td>
                    <td class="text-center"><span class="fw-bold" style="color:#10b981">{{ number_format($r['converted']) }}</span></td>
                    <td class="text-center"><span style="color:#6366f1">{{ number_format($r['active']) }}</span></td>
                    <td class="text-center"><span style="color:#ef4444">{{ number_format($r['lost']) }}</span></td>
                    <td class="text-center fw-semibold">{{ number_format($r['calls']) }}</td>
                    <td class="text-center" style="font-size:.78rem">
                        <span style="color:#10b981">{{ $r['calls_inbound'] }}</span>
                        <span class="text-muted">/</span>
                        <span style="color:#6366f1">{{ $r['calls_outbound'] }}</span>
                        <span class="text-muted">/</span>
                        <span style="color:#ef4444">{{ $r['calls_missed'] }}</span>
                    </td>
                    <td class="text-center">
                        <div style="font-size:.78rem;font-weight:600;color:#0f172a">{{ $r['total_talk_mins'] }}m</div>
                        <div style="font-size:.7rem;color:#94a3b8">avg {{ $r['avg_talk_time'] }}</div>
                    </td>
                    <td class="text-center">
                        <span class="badge" style="background:#06b6d415;color:#06b6d4;font-size:11px">{{ $r['meetings'] }}</span>
                        @if($r['meetings_done'] > 0)
                        <div style="font-size:.68rem;color:#94a3b8">{{ $r['meetings_done'] }} done</div>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge" style="background:#25d36615;color:#25d366;font-size:11px">{{ $r['messages'] }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-{{ $fuColor }}-subtle text-{{ $fuColor }}" style="font-size:10px">{{ $r['followup_rate'] }}%</span>
                    </td>
                    <td class="text-center">
                        @if($r['pending_followups'] > 0)
                            <span class="badge bg-danger" style="font-size:10px">{{ $r['pending_followups'] }}</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-secondary" style="font-size:10px">{{ $r['avg_response_fmt'] }}</span>
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
                        @if(count($tcRows) > 0)
                        <div role="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $collapseId }}"
                            aria-expanded="false"
                            style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#6366f1;font-weight:600">
                            <span class="material-icons tc-chevron" style="font-size:16px;transition:transform .25s">expand_more</span>
                            {{ count($tcRows) }} agent{{ count($tcRows) > 1 ? 's' : '' }}
                        </div>
                        @else
                        <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>

                {{-- Telecaller sub-rows (collapsed) --}}
                @if(count($tcRows) > 0)
                <tr class="collapse" id="{{ $collapseId }}">
                    <td colspan="19" style="padding:0;background:#f8fafc">
                        <div style="padding:0 16px 12px 56px">
                            <div style="font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;padding:8px 0 4px">
                                <span class="material-icons" style="font-size:13px;vertical-align:-2px;color:#6366f1">groups</span>
                                Telecaller Breakdown under {{ $r['name'] }}
                            </div>
                            <table class="table table-sm mb-0" style="font-size:.78rem">
                                <thead style="background:#f1f5f9">
                                    <tr>
                                        <th class="text-muted fw-semibold" style="font-size:.68rem">Telecaller</th>
                                        <th class="text-center text-muted fw-semibold" style="font-size:.68rem">Leads</th>
                                        <th class="text-center text-muted fw-semibold" style="font-size:.68rem">Converted</th>
                                        <th class="text-center text-muted fw-semibold" style="font-size:.68rem">Conv %</th>
                                        <th class="text-center text-muted fw-semibold" style="font-size:.68rem">Calls</th>
                                        <th class="text-center text-muted fw-semibold" style="font-size:.68rem">Answered</th>
                                        <th class="text-center text-muted fw-semibold" style="font-size:.68rem">Missed</th>
                                        <th class="text-center text-muted fw-semibold" style="font-size:.68rem">Followup %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tcRows as $tc)
                                    <tr>
                                        <td class="fw-semibold" style="color:#0f172a">{{ $tc['name'] }}</td>
                                        <td class="text-center">{{ $tc['leads'] }}</td>
                                        <td class="text-center" style="color:#10b981;font-weight:700">{{ $tc['converted'] }}</td>
                                        <td class="text-center">
                                            <span style="color:{{ $tc['conversion_rate'] >= 25 ? '#10b981' : '#f59e0b' }};font-weight:600">{{ $tc['conversion_rate'] }}%</span>
                                        </td>
                                        <td class="text-center fw-semibold">{{ $tc['calls'] }}</td>
                                        <td class="text-center" style="color:#10b981">{{ $tc['answered'] }}</td>
                                        <td class="text-center" style="color:#ef4444">{{ $tc['missed'] }}</td>
                                        <td class="text-center">
                                            <span class="badge" style="background:#06b6d415;color:#06b6d4;font-size:10px">{{ $tc['followup_rate'] }}%</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="19" class="text-center py-5 text-muted">
                        <span class="material-icons d-block mb-2" style="font-size:40px;opacity:.3">bar_chart</span>
                        No manager data found for the selected filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Legend --}}
<div class="d-flex flex-wrap gap-3 mt-3 small text-muted align-items-center">
    <span><span class="grade-pill g-A me-1" style="width:20px;height:20px;font-size:10px">A</span>Score ≥ 70: High</span>
    <span><span class="grade-pill g-B me-1" style="width:20px;height:20px;font-size:10px">B</span>Score 40–69: Average</span>
    <span><span class="grade-pill g-C me-1" style="width:20px;height:20px;font-size:10px">C</span>Score 20–39: Below Avg</span>
    <span><span class="grade-pill g-D me-1" style="width:20px;height:20px;font-size:10px">D</span>Score &lt; 20: Needs Attention</span>
    <span class="ms-auto" style="font-size:.7rem">Score = Conversion(40%) + Followup(35%) + Answer Rate(25%)</span>
</div>

<script>
(function () {
    function _init() {
        const rows  = @json($rows);
        const dist  = @json($perfDist);
        const mLbl  = @json($monthLabels);
        const mAsgn = @json($monthAssigned);
        const mConv = @json($monthConverted);
        const mCall = @json($monthCalls);
        const names = rows.map(r => r.name);
        const GRID  = { color:'rgba(0,0,0,.04)' };
        const TICK  = { color:'#94a3b8', font:{ size:10 } };

        /* 1 Pipeline */
        new Chart(document.getElementById('pipelineChart'), {
            type:'bar',
            data:{ labels:names, datasets:[
                { label:'Assigned',  data:rows.map(r=>r.assigned),  backgroundColor:'#6366f1', borderRadius:4 },
                { label:'Converted', data:rows.map(r=>r.converted), backgroundColor:'#10b981', borderRadius:4 },
                { label:'Active',    data:rows.map(r=>r.active),    backgroundColor:'#06b6d4', borderRadius:4 },
                { label:'Lost',      data:rows.map(r=>r.lost),      backgroundColor:'#ef4444', borderRadius:4 },
            ]},
            options:{ maintainAspectRatio:false,
                plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } },
                scales:{ y:{ beginAtZero:true, ticks:{...TICK,precision:0}, grid:GRID }, x:{ ticks:TICK, grid:{display:false} } }
            }
        });

        /* 2 Distribution doughnut */
        new Chart(document.getElementById('distChart'), {
            type:'doughnut',
            data:{ labels:['High (A)','Average (B/C)','Needs Attention (D)'],
                datasets:[{ data:[dist.high,dist.average,dist.low],
                    backgroundColor:['#10b981','#f59e0b','#ef4444'], borderWidth:0, hoverOffset:6 }]
            },
            options:{ maintainAspectRatio:false, cutout:'65%',
                plugins:{ legend:{display:false},
                    tooltip:{ callbacks:{ label: ctx => ` ${ctx.label}: ${ctx.raw} manager(s)` } } }
            }
        });

        /* 3 Conversion horizontal bar */
        new Chart(document.getElementById('convChart'), {
            type:'bar',
            data:{ labels:names, datasets:[{
                label:'Conversion %', data:rows.map(r=>r.conversion_rate),
                backgroundColor:rows.map(r=>r.conversion_rate>=50?'#10b981':r.conversion_rate>=25?'#f59e0b':'#ef4444'),
                borderRadius:4
            }]},
            options:{ indexAxis:'y', maintainAspectRatio:false,
                plugins:{ legend:{display:false} },
                scales:{ x:{ beginAtZero:true, max:100, ticks:{...TICK,callback:v=>v+'%'}, grid:GRID },
                    y:{ ticks:TICK, grid:{display:false} } }
            }
        });

        /* 4 Call breakdown stacked bar */
        new Chart(document.getElementById('callChart'), {
            type:'bar',
            data:{ labels:names, datasets:[
                { label:'Inbound',  data:rows.map(r=>r.calls_inbound),  backgroundColor:'#10b981', borderRadius:2 },
                { label:'Outbound', data:rows.map(r=>r.calls_outbound), backgroundColor:'#6366f1', borderRadius:2 },
                { label:'Missed',   data:rows.map(r=>r.calls_missed),   backgroundColor:'#ef4444', borderRadius:2 },
            ]},
            options:{ maintainAspectRatio:false, scales:{
                x:{ stacked:true, ticks:TICK, grid:{display:false} },
                y:{ stacked:true, beginAtZero:true, ticks:{...TICK,precision:0}, grid:GRID }
            }, plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } } }
        });

        /* 5 Follow-up rate vs Score */
        new Chart(document.getElementById('fuScoreChart'), {
            type:'bar',
            data:{ labels:names, datasets:[
                { label:'Followup %', data:rows.map(r=>r.followup_rate), backgroundColor:'rgba(99,102,241,.7)', borderRadius:4, yAxisID:'y' },
                { label:'Score',      data:rows.map(r=>r.performance_score), type:'line',
                    borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,.15)',
                    borderWidth:2, pointRadius:4, tension:.35, fill:true, yAxisID:'y1' },
            ]},
            options:{ maintainAspectRatio:false,
                plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } },
                scales:{
                    y:  { beginAtZero:true, max:100, ticks:{...TICK,callback:v=>v+'%'}, grid:GRID, position:'left' },
                    y1: { beginAtZero:true, max:100, ticks:TICK, grid:{display:false}, position:'right' },
                    x:  { ticks:TICK, grid:{display:false} }
                }
            }
        });

        /* 6 Meetings & Messages */
        new Chart(document.getElementById('meetMsgChart'), {
            type:'bar',
            data:{ labels:names, datasets:[
                { label:'Meetings', data:rows.map(r=>r.meetings),  backgroundColor:'#06b6d4', borderRadius:4 },
                { label:'Messages', data:rows.map(r=>r.messages),  backgroundColor:'#25d366', borderRadius:4 },
            ]},
            options:{ maintainAspectRatio:false,
                plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } },
                scales:{ y:{ beginAtZero:true, ticks:{...TICK,precision:0}, grid:GRID }, x:{ ticks:TICK, grid:{display:false} } }
            }
        });

        /* 7 Monthly trend */
        new Chart(document.getElementById('trendChart'), {
            type:'line',
            data:{ labels:mLbl, datasets:[
                { label:'Assigned',  data:mAsgn, borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,.10)', borderWidth:2, tension:.35, fill:true,  pointRadius:4 },
                { label:'Converted', data:mConv, borderColor:'#10b981', backgroundColor:'rgba(16,185,129,.08)', borderWidth:2, tension:.35, fill:true,  pointRadius:4 },
                { label:'Calls',     data:mCall, borderColor:'#06b6d4', borderWidth:2, borderDash:[4,3],        tension:.35, fill:false, pointRadius:3 },
            ]},
            options:{ maintainAspectRatio:false,
                plugins:{ legend:{ position:'bottom', labels:{ font:{ size:11 } } } },
                scales:{ y:{ beginAtZero:true, ticks:{...TICK,precision:0}, grid:GRID }, x:{ ticks:TICK, grid:{display:false} } }
            }
        });

        /* Chevron rotation on collapse toggle */
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(el => {
            const target = document.querySelector(el.dataset.bsTarget);
            if (!target) return;
            target.addEventListener('show.bs.collapse', () => {
                el.querySelector('.tc-chevron').style.transform = 'rotate(180deg)';
            });
            target.addEventListener('hide.bs.collapse', () => {
                el.querySelector('.tc-chevron').style.transform = 'rotate(0deg)';
            });
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
