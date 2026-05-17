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
    .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: 700; }
    .badge-green { background: #d1fae5; color: #065f46; }
    .badge-amber { background: #fef3c7; color: #92400e; }
    .badge-purple { background: #ede9fe; color: #5b21b6; }
    .footer { text-align: center; margin-top: 16px; color: #94a3b8; font-size: 9px; padding: 0 20px; }
    .two-col { display: table; width: calc(100% - 40px); margin: 0 20px 14px; }
    .col-half { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
    .col-half:last-child { padding-right: 0; padding-left: 10px; }
    .col-half table { width: 100%; margin: 0; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $title }} Report</h1>
    <p>{{ $userName }} &nbsp;|&nbsp; {{ $period }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
</div>

{{-- Performance Summary --}}
<div class="section-title">Performance Summary</div>
<div class="summary-grid">
    @foreach($summary as $label => $value)
    <div class="summary-row">
        <div class="summary-cell label">{{ $label }}</div>
        <div class="summary-cell value">{{ $value }}</div>
    </div>
    @endforeach
</div>

{{-- Call Outcome Breakdown --}}
@if(!empty($outcomeBreakdown))
@php $totalOutcome = array_sum($outcomeBreakdown); @endphp
<div class="section-title">Call Outcome Breakdown</div>
<table>
    <thead>
        <tr>
            <th style="width:50%">Outcome</th>
            <th style="width:15%">Count</th>
            <th style="width:35%">Share</th>
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

{{-- Course-wise + Final Course (side by side) --}}
@if(!empty($courseWiseBreakdown) || !empty($finalCourseBreakdown))
<div class="two-col">
    @if(!empty($courseWiseBreakdown))
    <div class="col-half">
        <div class="section-title" style="margin-left:0;">Enquired Course Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th style="width:55%">Course</th>
                    <th style="width:20%">Enquiries</th>
                    <th style="width:25%">Converted</th>
                </tr>
            </thead>
            <tbody>
                @foreach($courseWiseBreakdown as $r)
                <tr>
                    <td>{{ $r['course'] }}</td>
                    <td style="font-weight:700;">{{ $r['enquiries'] }}</td>
                    <td><span class="badge badge-green">{{ $r['conversions'] }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @if(!empty($finalCourseBreakdown))
    <div class="col-half">
        <div class="section-title" style="margin-left:0;">Final Selected Course</div>
        <table>
            <thead>
                <tr>
                    <th style="width:70%">Course</th>
                    <th style="width:30%">Conversions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($finalCourseBreakdown as $r)
                <tr>
                    <td>{{ $r['course'] }}</td>
                    <td style="font-weight:700; color:#065f46;">{{ $r['count'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif

{{-- Gender + Quota (side by side) --}}
@if(!empty($genderBreakdown) || !empty($quotaBreakdown))
<div class="two-col">
    @if(!empty($genderBreakdown))
    @php $gTotal = array_sum(array_column($genderBreakdown, 'total')); @endphp
    <div class="col-half">
        <div class="section-title" style="margin-left:0;">Gender-wise Analysis</div>
        <table>
            <thead>
                <tr>
                    <th style="width:35%">Gender</th>
                    <th style="width:20%">Total</th>
                    <th style="width:20%">Conv.</th>
                    <th style="width:25%">Share</th>
                </tr>
            </thead>
            <tbody>
                @foreach($genderBreakdown as $r)
                @php $pct = $gTotal > 0 ? round(($r['total'] / $gTotal) * 100) : 0; @endphp
                <tr>
                    <td style="font-weight:600;">{{ $r['gender'] }}</td>
                    <td>{{ $r['total'] }}</td>
                    <td><span class="badge badge-green">{{ $r['conversions'] }}</span></td>
                    <td>{{ $pct }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @if(!empty($quotaBreakdown))
    <div class="col-half">
        <div class="section-title" style="margin-left:0;">Quota Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th style="width:40%">Quota</th>
                    <th style="width:20%">Total</th>
                    <th style="width:20%">Conv.</th>
                    <th style="width:20%">Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotaBreakdown as $r)
                @php $rate = $r['total'] > 0 ? round(($r['conversions'] / $r['total']) * 100) : 0; @endphp
                <tr>
                    <td style="font-weight:600;">{{ $r['quota'] }}</td>
                    <td>{{ $r['total'] }}</td>
                    <td>{{ $r['conversions'] }}</td>
                    <td><span class="badge badge-amber">{{ $rate }}%</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif

{{-- Converted Leads --}}
@if(!empty($convertedLeads))
<div class="section-title">Converted Lead Details</div>
<table>
    <thead>
        <tr>
            <th style="width:10%">Code</th>
            <th style="width:20%">Name</th>
            <th style="width:14%">Phone</th>
            <th style="width:8%">Gender</th>
            <th style="width:16%">Enquired Course</th>
            <th style="width:16%">Final Course</th>
            <th style="width:10%">Quota</th>
            <th style="width:6%">Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($convertedLeads as $l)
        <tr>
            <td style="font-family:monospace;font-size:9px;color:#64748b;">{{ $l['lead_code'] }}</td>
            <td style="font-weight:600;">{{ $l['name'] }}</td>
            <td>{{ $l['phone'] }}</td>
            <td>{{ $l['gender'] }}</td>
            <td>{{ $l['enquired_course'] }}</td>
            <td style="color:#065f46;font-weight:600;">{{ $l['final_course'] }}</td>
            <td><span class="badge badge-purple">{{ $l['quota'] }}</span></td>
            <td style="font-size:9px;color:#64748b;">{{ $l['date'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Daily Call Activity --}}
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
