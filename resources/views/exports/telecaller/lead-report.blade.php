<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; background: #fff; }
    .header { background: #6366f1; color: #fff; padding: 14px 20px; margin-bottom: 14px; }
    .header h1 { font-size: 16px; font-weight: 700; margin-bottom: 3px; }
    .header p  { font-size: 10px; opacity: .85; }
    .filter-bar { background: #f1f5f9; border-left: 3px solid #6366f1; padding: 7px 14px; margin: 0 20px 14px; font-size: 10px; color: #334155; }
    .filter-bar strong { color: #6366f1; }
    table { width: calc(100% - 40px); margin: 0 20px 14px; border-collapse: collapse; }
    thead th { background: #6366f1; color: #fff; font-weight: 700; padding: 7px 8px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody tr:hover { background: #ede9fe; }
    tbody td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 8px; font-size: 8px; font-weight: 700; }
    .badge-new          { background: #e0e7ff; color: #3730a3; }
    .badge-contacted    { background: #cffafe; color: #0e7490; }
    .badge-interested   { background: #d1fae5; color: #065f46; }
    .badge-converted    { background: #fef3c7; color: #92400e; }
    .badge-not_interested { background: #fee2e2; color: #991b1b; }
    .badge-lost         { background: #f1f5f9; color: #64748b; }
    .quota-management   { background: #fef3c7; color: #92400e; }
    .quota-counselling  { background: #ede9fe; color: #5b21b6; }
    .count-row { background: #f8fafc; font-weight: 700; font-size: 10px; }
    .footer { text-align: center; margin-top: 14px; color: #94a3b8; font-size: 9px; padding: 0 20px; }
</style>
</head>
<body>

<div class="header">
    <h1>Lead Report</h1>
    <p>{{ $userName }} &nbsp;|&nbsp; {{ $period }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
</div>

<div class="filter-bar">
    <strong>Filters applied:</strong> {{ $filterDesc }}
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Total leads:</strong> {{ count($leads) }}
</div>

@if($leads->isEmpty())
<p style="padding: 20px; text-align:center; color:#94a3b8;">No leads match the selected filters.</p>
@else
<table>
    <thead>
        <tr>
            <th style="width:7%">Code</th>
            <th style="width:16%">Name</th>
            <th style="width:12%">Phone</th>
            <th style="width:7%">Gender</th>
            <th style="width:17%">Enquired Course</th>
            <th style="width:17%">Final Course</th>
            <th style="width:10%">Quota</th>
            <th style="width:8%">Status</th>
            <th style="width:6%">Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($leads as $l)
        @php
            $statusClass = 'badge-' . ($l->status ?? 'new');
            $quotaClass  = $l->quota ? 'quota-' . $l->quota : '';
        @endphp
        <tr>
            <td style="font-family:monospace;color:#64748b;font-size:8.5px;">{{ $l->lead_code }}</td>
            <td style="font-weight:600;">{{ $l->name }}</td>
            <td>{{ $l->phone }}</td>
            <td style="text-transform:capitalize;">{{ $l->gender ?: '-' }}</td>
            <td>{{ $l->enrolledCourse->name ?? '-' }}</td>
            <td style="font-weight:600;color:#065f46;">{{ $l->finalCourse->name ?? '-' }}</td>
            <td>
                @if($l->quota)
                <span class="badge {{ $quotaClass }}" style="text-transform:capitalize;">{{ $l->quota }}</span>
                @else
                <span style="color:#94a3b8;">-</span>
                @endif
            </td>
            <td>
                <span class="badge {{ $statusClass }}" style="text-transform:capitalize;">
                    {{ str_replace('_', ' ', $l->status) }}
                </span>
            </td>
            <td style="color:#64748b;font-size:8.5px;">{{ $l->created_at->format('d M Y') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">Exported from Insight Tech CRM &mdash; Confidential</div>
</body>
</html>
