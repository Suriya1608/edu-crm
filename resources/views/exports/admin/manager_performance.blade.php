<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size:9px; color:#1e293b; background:#fff; }

.hdr { background:#6366f1; color:#fff; padding:10px 16px; margin-bottom:10px; }
.hdr-title { font-size:15px; font-weight:700; margin-bottom:2px; }
.hdr-sub   { font-size:8px; opacity:.85; }

/* KPI cards */
.kpi-wrap  { margin:0 12px 10px; }
.kpi-title { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6366f1; margin-bottom:4px; }
.kpi-row   { margin-bottom:3px; }
.kpi-cell  { display:inline-block; width:23.5%; vertical-align:top; background:#f8fafc; border:1px solid #e2e8f0; border-radius:3px; padding:4px 7px; margin-right:.5%; }
.kpi-val   { display:block; font-size:13px; font-weight:700; color:#6366f1; line-height:1.2; }
.kpi-lbl   { display:block; font-size:7px; color:#64748b; text-transform:uppercase; letter-spacing:.4px; margin-top:1px; }
.kv-green  { color:#10b981; } .kv-cyan { color:#06b6d4; } .kv-amber { color:#f59e0b; }
.kv-red    { color:#ef4444; } .kv-purple{ color:#8b5cf6; }

/* Tables */
.tbl-title { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6366f1; margin:0 12px 4px; }
table { width:calc(100% - 24px); margin:0 12px; border-collapse:collapse; }
thead th { background:#0f172a; color:#fff; font-weight:700; padding:4px 4px; font-size:7px; text-transform:uppercase; letter-spacing:.3px; text-align:center; }
thead th:first-child { text-align:left; }
tbody tr:nth-child(even) { background:#f8fafc; }
tbody td { padding:3px 4px; font-size:8px; border-bottom:1px solid #f1f5f9; text-align:center; vertical-align:top; }
tbody td:first-child { text-align:left; }
tbody td:nth-child(2) { font-weight:700; }

/* Grade badge */
.grade { display:inline-block; width:14px; height:14px; border-radius:3px; font-size:7px; font-weight:700; line-height:14px; text-align:center; }
.g-A { background:#d1fae5; color:#065f46; } .g-B { background:#dbeafe; color:#1e40af; }
.g-C { background:#fef3c7; color:#92400e; } .g-D { background:#fee2e2; color:#991b1b; }

/* Score bar */
.bar-wrap { background:#e2e8f0; border-radius:3px; height:5px; width:40px; display:inline-block; vertical-align:middle; margin-right:2px; }
.bar-fill { border-radius:3px; height:5px; }

/* Telecaller sub-table */
.sub-wrap { margin:4px 12px 4px 24px; }
.sub-lbl  { font-size:7px; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.4px; margin-bottom:2px; }
.sub-table { width:100%; border-collapse:collapse; }
.sub-table th { background:#6366f115; color:#6366f1; font-size:6.5px; font-weight:700; padding:3px 4px; text-align:center; text-transform:uppercase; border-bottom:1px solid #e2e8f0; }
.sub-table th:first-child { text-align:left; }
.sub-table td { font-size:7.5px; padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:center; }
.sub-table td:first-child { text-align:left; }

.rank-1 { color:#f59e0b; font-weight:700; }
.rank-2 { color:#64748b; font-weight:700; }
.rank-3 { color:#b45309; font-weight:700; }
.footer { text-align:center; margin-top:10px; color:#94a3b8; font-size:7px; padding-bottom:6px; }
.legend { margin:4px 12px 0; font-size:7px; color:#64748b; }
.dot { display:inline-block; width:7px; height:7px; border-radius:2px; margin-right:2px; vertical-align:middle; }
</style>
</head>
<body>

<div class="hdr">
    <div class="hdr-title">Manager Performance Report</div>
    <div class="hdr-sub">Period: {{ $periodLabel }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</div>
</div>

{{-- KPI Summary --}}
<div class="kpi-wrap">
    <div class="kpi-title">Key Performance Indicators</div>
    @php
        $kpiItems = array_filter(array_map(null, array_keys($summary), array_values($summary)), fn($p) => !in_array($p[0], ['Period','Generated']));
        $kpiChunks = array_chunk(array_values($kpiItems), 4);
    @endphp
    @foreach($kpiChunks as $chunk)
    <div class="kpi-row">
        @foreach($chunk as [$lbl, $val])
        @php
            $cls = '';
            if(str_contains($lbl,'Converted') || str_contains($lbl,'Top')) $cls='kv-green';
            elseif(str_contains($lbl,'Call') || str_contains($lbl,'Talk')) $cls='kv-cyan';
            elseif(str_contains($lbl,'Pending')) $cls='kv-red';
            elseif(str_contains($lbl,'Avg') || str_contains($lbl,'Followup') || str_contains($lbl,'Meet')) $cls='kv-amber';
            elseif(str_contains($lbl,'Manager')) $cls='kv-purple';
        @endphp
        <div class="kpi-cell">
            <span class="kpi-val {{ $cls }}">{{ $val }}</span>
            <span class="kpi-lbl">{{ $lbl }}</span>
        </div>
        @endforeach
    </div>
    @endforeach
</div>

{{-- Rankings Table --}}
<div class="tbl-title">Manager Rankings &amp; Detailed Metrics</div>
<table>
    <thead>
        <tr>
            <th style="width:18px">#</th>
            <th style="text-align:left;width:70px">Manager</th>
            <th style="width:16px">Gr.</th>
            <th>Team</th>
            <th>Assigned</th>
            <th>Conv.</th>
            <th>Active</th>
            <th>Lost</th>
            <th>Calls</th>
            <th>In</th>
            <th>Out</th>
            <th>Miss.</th>
            <th>Ans%</th>
            <th>Talk</th>
            <th>Meet.</th>
            <th>Msgs</th>
            <th>F/U%</th>
            <th>Pend.</th>
            <th>Conv%</th>
            <th style="width:70px">Score</th>
        </tr>
    </thead>
    <tbody>
    @foreach($rows as $i => $r)
    @php
        $rank = $i + 1;
        $score = $r['performance_score'];
        $barColor = $score >= 70 ? '#10b981' : ($score >= 40 ? '#f59e0b' : '#ef4444');
        $rankCls  = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : ''));
    @endphp
    <tr>
        <td class="{{ $rankCls }}" style="text-align:center">#{{ $rank }}</td>
        <td style="text-align:left">{{ $r['name'] }}</td>
        <td style="text-align:center"><span class="grade g-{{ $r['grade'] }}">{{ $r['grade'] }}</span></td>
        <td>{{ $r['team_size'] }}</td>
        <td>{{ $r['assigned'] }}</td>
        <td style="color:#10b981;font-weight:700">{{ $r['converted'] }}</td>
        <td style="color:#6366f1">{{ $r['active'] }}</td>
        <td style="color:#ef4444">{{ $r['lost'] }}</td>
        <td style="font-weight:700">{{ $r['calls'] }}</td>
        <td style="color:#10b981">{{ $r['calls_inbound'] }}</td>
        <td style="color:#6366f1">{{ $r['calls_outbound'] }}</td>
        <td style="color:#ef4444">{{ $r['calls_missed'] }}</td>
        <td>{{ $r['answer_rate'] }}%</td>
        <td>{{ $r['total_talk_mins'] }}m</td>
        <td>{{ $r['meetings'] }}</td>
        <td>{{ $r['messages'] }}</td>
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

<div class="legend" style="margin-top:5px">
    <span class="dot" style="background:#10b981"></span>Score &ge; 70: High (A) &nbsp;&nbsp;
    <span class="dot" style="background:#f59e0b"></span>Score 40&ndash;69: Average (B/C) &nbsp;&nbsp;
    <span class="dot" style="background:#ef4444"></span>Score &lt; 40: Needs Attention (D) &nbsp;&nbsp;&nbsp;
    Score = Conversion(40%) + Followup(35%) + Answer Rate(25%)
</div>

<div class="footer">Edu-CRM &nbsp;&bull;&nbsp; Manager Performance Report &nbsp;&bull;&nbsp; {{ $generatedAt }}</div>
</body>
</html>
