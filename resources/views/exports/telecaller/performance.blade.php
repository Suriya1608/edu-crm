<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
    .header { background: #6366f1; color: #fff; padding: 16px 20px; margin-bottom: 16px; }
    .header h1 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    .header p { font-size: 11px; opacity: .85; }
    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #6366f1; margin: 14px 20px 6px; }
    .summary-grid { display: table; width: calc(100% - 40px); margin: 0 20px 12px; border-collapse: collapse; }
    .summary-row { display: table-row; }
    .summary-cell { display: table-cell; padding: 7px 12px; border: 1px solid #e2e8f0; font-size: 11px; }
    .summary-cell.label { font-weight: 700; background: #f8fafc; width: 55%; color: #374151; }
    .summary-cell.value { color: #0f172a; font-weight: 700; }
    table { width: calc(100% - 40px); margin: 0 20px 14px; border-collapse: collapse; }
    thead th { background: #f1f5f9; color: #374151; font-weight: 700; padding: 7px 10px; text-align: left; border-bottom: 2px solid #6366f1; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
    .bar-wrap { background: #e2e8f0; border-radius: 4px; height: 7px; width: 80px; display: inline-block; vertical-align: middle; margin-left: 6px; }
    .bar-fill { background: #6366f1; border-radius: 4px; height: 7px; }
    .footer { text-align: center; margin-top: 16px; color: #94a3b8; font-size: 9px; padding: 0 20px; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $title }} Report</h1>
    <p>{{ $userName }} &nbsp;|&nbsp; {{ $period }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
</div>

{{-- Summary --}}
<div class="section-title">Performance Summary</div>
<div class="summary-grid">
    @foreach($summary as $label => $value)
    <div class="summary-row">
        <div class="summary-cell label">{{ $label }}</div>
        <div class="summary-cell value">{{ $value }}</div>
    </div>
    @endforeach
</div>

{{-- Outcome breakdown --}}
@if(!empty($outcomeBreakdown))
@php $totalOutcome = array_sum($outcomeBreakdown); @endphp
<div class="section-title">Call Outcome Breakdown</div>
<table>
    <thead>
        <tr>
            <th style="width:55%">Outcome</th>
            <th style="width:20%">Count</th>
            <th style="width:25%">Share</th>
        </tr>
    </thead>
    <tbody>
        @foreach($outcomeBreakdown as $label => $cnt)
        @php $pct = $totalOutcome > 0 ? round(($cnt / $totalOutcome) * 100) : 0; @endphp
        <tr>
            <td>{{ $label }}</td>
            <td style="font-weight:700;">{{ $cnt }}</td>
            <td>
                {{ $pct }}%
                <span class="bar-wrap"><span class="bar-fill" style="width:{{ $pct }}%;"></span></span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Daily breakdown --}}
@if(!empty($dailyBreakdown))
<div class="section-title">Daily Call Activity</div>
<table>
    <thead>
        <tr>
            <th style="width:35%">Date</th>
            <th style="width:20%">Calls</th>
            <th style="width:25%">Talk Time</th>
            <th style="width:20%">Avg/Call</th>
        </tr>
    </thead>
    <tbody>
        @foreach($dailyBreakdown as $row)
        <tr>
            <td style="font-weight:600;">{{ $row['day'] }}</td>
            <td>{{ $row['calls'] }}</td>
            <td style="font-family:monospace;">{{ $row['talk_time'] }}</td>
            <td style="font-family:monospace;color:#64748b;">{{ $row['avg'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">Exported from Insight Tech CRM &mdash; Confidential</div>
</body>
</html>
