<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size:9px; color:#1e293b; background:#fff; }

.hdr { background:#6366f1; color:#fff; padding:10px 16px 10px; margin-bottom:10px; }
.hdr-title { font-size:15px; font-weight:700; margin-bottom:2px; }
.hdr-sub   { font-size:8px; opacity:.85; }

/* Summary grid — 2 columns of key:value rows */
.summary-wrap { margin:0 12px 10px; display:block; }
.summary-title { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6366f1; margin-bottom:4px; }
.kpi-grid { width:100%; }
.kpi-row  { display:block; margin-bottom:2px; }
.kpi-cell { display:inline-block; width:24%; vertical-align:top; background:#f8fafc; border:1px solid #e2e8f0; border-radius:3px; padding:4px 7px; margin-right:1%; }
.kpi-val  { display:block; font-size:13px; font-weight:700; color:#6366f1; line-height:1.2; }
.kpi-lbl  { display:block; font-size:7px; color:#64748b; text-transform:uppercase; letter-spacing:.4px; margin-top:1px; }
.kpi-green { color:#10b981; }
.kpi-cyan  { color:#06b6d4; }
.kpi-amber { color:#f59e0b; }
.kpi-red   { color:#ef4444; }
.kpi-purple{ color:#8b5cf6; }

/* Table */
.tbl-title { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6366f1; margin:0 12px 4px; }
table { width:calc(100% - 24px); margin:0 12px; border-collapse:collapse; }
thead th { background:#0f172a; color:#fff; font-weight:700; padding:5px 5px; font-size:7.5px; text-transform:uppercase; letter-spacing:.3px; text-align:center; border:none; }
thead th:first-child { text-align:left; border-radius:2px 0 0 0; }
thead th:last-child  { border-radius:0 2px 0 0; }
tbody tr:nth-child(even) { background:#f8fafc; }
tbody td { padding:4px 5px; font-size:8px; border-bottom:1px solid #f1f5f9; text-align:center; vertical-align:middle; }
tbody td:first-child { text-align:left; }
tbody td:nth-child(2) { font-weight:700; }

/* Grade badge */
.grade { display:inline-block; width:16px; height:16px; border-radius:3px; font-size:8px; font-weight:700; line-height:16px; text-align:center; }
.grade-A { background:#d1fae5; color:#065f46; }
.grade-B { background:#dbeafe; color:#1e40af; }
.grade-C { background:#fef3c7; color:#92400e; }
.grade-D { background:#fee2e2; color:#991b1b; }

/* Score bar */
.bar-wrap { background:#e2e8f0; border-radius:3px; height:6px; width:50px; display:inline-block; vertical-align:middle; margin-right:3px; }
.bar-fill { border-radius:3px; height:6px; }

/* rank */
.rank-1 { color:#f59e0b; font-weight:700; }
.rank-2 { color:#64748b; font-weight:700; }
.rank-3 { color:#b45309; font-weight:700; }

.footer { text-align:center; margin-top:10px; color:#94a3b8; font-size:7px; padding-bottom:6px; }
.legend { margin:6px 12px 0; font-size:7px; color:#64748b; }
.dot { display:inline-block; width:8px; height:8px; border-radius:2px; margin-right:3px; vertical-align:middle; }
</style>
</head>
<body>

<div class="hdr">
    <div class="hdr-title">Telecaller Performance Report</div>
    <div class="hdr-sub">Period: {{ $periodLabel }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</div>
</div>

{{-- KPI Summary --}}
<div class="summary-wrap">
    <div class="summary-title">Key Performance Indicators</div>
    <div class="kpi-grid">
        <div class="kpi-row">
            @php $summaryItems = array_chunk(array_filter(array_values(array_map(null, array_keys($summary), array_values($summary))), fn($p) => !in_array($p[0], ['Period','Generated'])), 4); @endphp
            @foreach($summaryItems as $chunk)
            <div style="margin-bottom:3px">
                @foreach($chunk as [$lbl, $val])
                @php
                    $cls = '';
                    if(str_contains($lbl,'Converted') || str_contains($lbl,'Top')) $cls = 'kpi-green';
                    elseif(str_contains($lbl,'Call') || str_contains($lbl,'Talk')) $cls = 'kpi-cyan';
                    elseif(str_contains($lbl,'Pending')) $cls = 'kpi-red';
                    elseif(str_contains($lbl,'Avg') || str_contains($lbl,'Followup')) $cls = 'kpi-amber';
                    elseif(str_contains($lbl,'Telecaller') || str_contains($lbl,'Score')) $cls = 'kpi-purple';
                @endphp
                <div class="kpi-cell">
                    <span class="kpi-val {{ $cls }}">{{ $val }}</span>
                    <span class="kpi-lbl">{{ $lbl }}</span>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Performance Table --}}
<div class="tbl-title">Telecaller Rankings</div>
<table>
    <thead>
        <tr>
            <th style="width:22px">#</th>
            <th style="text-align:left;width:80px">Telecaller</th>
            <th style="width:18px">Grade</th>
            <th>Assigned</th>
            <th>Conv.</th>
            <th>Active</th>
            <th>Lost</th>
            <th>Calls</th>
            <th>Answered</th>
            <th>Missed</th>
            <th>Answer %</th>
            <th>Avg Talk</th>
            <th>Talk Time</th>
            <th>Calls/Lead</th>
            <th>Followup %</th>
            <th>Pending F/U</th>
            <th>Conv %</th>
            <th style="width:80px">Score</th>
        </tr>
    </thead>
    <tbody>
    @foreach($rows as $i => $r)
    @php
        $rank  = $i + 1;
        $score = $r['efficiency_score'];
        $barColor = $score >= 70 ? '#10b981' : ($score >= 40 ? '#f59e0b' : '#ef4444');
        $rankCls  = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : ''));
    @endphp
    <tr>
        <td class="{{ $rankCls }}" style="text-align:center">#{{ $rank }}</td>
        <td style="text-align:left">{{ $r['name'] }}</td>
        <td style="text-align:center">
            <span class="grade grade-{{ $r['grade'] }}">{{ $r['grade'] }}</span>
        </td>
        <td>{{ $r['assigned'] }}</td>
        <td style="color:#10b981;font-weight:700">{{ $r['converted'] }}</td>
        <td style="color:#6366f1">{{ $r['active'] }}</td>
        <td style="color:#ef4444">{{ $r['lost'] }}</td>
        <td style="font-weight:700">{{ $r['calls'] }}</td>
        <td style="color:#10b981">{{ $r['answered'] }}</td>
        <td style="color:#ef4444">{{ $r['missed'] }}</td>
        <td>{{ $r['answer_rate'] }}%</td>
        <td>{{ $r['avg_talk_time'] }}</td>
        <td>{{ $r['total_talk_mins'] }}m</td>
        <td>{{ $r['calls_per_lead'] }}</td>
        <td>{{ $r['followup_rate'] }}%</td>
        <td>{{ $r['pending_followups'] > 0 ? $r['pending_followups'] : '—' }}</td>
        <td style="font-weight:700">{{ $r['conversion_rate'] }}%</td>
        <td>
            <div class="bar-wrap"><div class="bar-fill" style="width:{{ min(100,$score) }}%;background:{{ $barColor }}"></div></div>
            <span style="color:{{ $barColor }};font-weight:700">{{ $score }}</span>
        </td>
    </tr>
    @endforeach
    </tbody>
</table>

<div class="legend" style="margin-top:6px">
    <span class="dot" style="background:#10b981"></span>Score &ge; 70: High Performer (A)&nbsp;&nbsp;
    <span class="dot" style="background:#f59e0b"></span>Score 40&ndash;69: Average (B/C)&nbsp;&nbsp;
    <span class="dot" style="background:#ef4444"></span>Score &lt; 40: Needs Attention (D)&nbsp;&nbsp;
    &nbsp;&nbsp;Score = Conv.(40%) + Followup(35%) + Answer Rate(25%)
</div>

<div class="footer">
    Edu-CRM &nbsp;&bull;&nbsp; Telecaller Performance Report &nbsp;&bull;&nbsp; {{ $generatedAt }}
</div>
</body>
</html>
