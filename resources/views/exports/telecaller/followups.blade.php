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
    .total { padding: 8px 20px; font-size: 11px; font-weight: 700; color: #374151; }
    table { width: calc(100% - 40px); margin: 0 20px; border-collapse: collapse; }
    thead th { background: #f1f5f9; color: #374151; font-weight: 700; padding: 8px 10px; text-align: left; border-bottom: 2px solid #6366f1; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; font-size: 10px; }
    .badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
    .badge-today { background: #e0f2fe; color: #0369a1; }
    .badge-overdue { background: #fee2e2; color: #991b1b; }
    .badge-upcoming { background: #d1fae5; color: #065f46; }
    .badge-completed { background: #f1f5f9; color: #475569; }
    .footer { text-align: center; margin-top: 16px; color: #94a3b8; font-size: 9px; padding: 0 20px; }
</style>
</head>
<body>
<div class="header">
    <h1>{{ $title }} Export</h1>
    <p>{{ $userName }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
</div>
<div class="total">Total Records: {{ count($followups) }}</div>
<table>
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:14%">Date</th>
            <th style="width:10%">Time</th>
            <th style="width:20%">Lead</th>
            <th style="width:13%">Phone</th>
            <th style="width:28%">Remarks</th>
            <th style="width:10%">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($followups as $fu)
        <tr>
            <td>{{ $fu['sno'] }}</td>
            <td>{{ $fu['date'] }}</td>
            <td>{{ $fu['time'] ?: '—' }}</td>
            <td>{{ $fu['lead_name'] }}<br><span style="color:#64748b;font-size:9px;">{{ $fu['lead_code'] }}</span></td>
            <td>{{ $fu['phone'] ?: '—' }}</td>
            <td>{{ $fu['remarks'] ?: '—' }}</td>
            <td><span class="badge badge-{{ $fu['scope'] }}">{{ ucfirst($fu['scope']) }}</span></td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No records found.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">Exported from Insight Tech CRM &mdash; Confidential</div>
</body>
</html>
