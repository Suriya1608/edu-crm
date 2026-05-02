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
    .dir-in  { background: #1e293b; color: #fff; }
    .dir-out { background: #6366f1; color: #fff; }
    .st-completed { background: #d1fae5; color: #065f46; }
    .st-missed, .st-failed, .st-busy { background: #fee2e2; color: #991b1b; }
    .st-inprogress, .st-answered { background: #e0f2fe; color: #0369a1; }
    .st-ringing { background: #fef3c7; color: #92400e; }
    .st-other { background: #f1f5f9; color: #475569; }
    .footer { text-align: center; margin-top: 16px; color: #94a3b8; font-size: 9px; padding: 0 20px; }
</style>
</head>
<body>
<div class="header">
    <h1>{{ $title }} Export</h1>
    <p>{{ $userName }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
</div>
<div class="total">Total Records: {{ count($calls) }}</div>
<table>
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:16%">Date</th>
            <th style="width:20%">Lead</th>
            <th style="width:12%">Phone</th>
            <th style="width:9%">Direction</th>
            <th style="width:10%">Status</th>
            <th style="width:10%">Duration</th>
            <th style="width:18%">Outcome</th>
        </tr>
    </thead>
    <tbody>
        @forelse($calls as $call)
        @php
            $stSlug = match(strtolower($call['status'])) {
                'completed' => 'completed',
                'missed', 'failed', 'busy', 'no-answer', 'canceled' => 'missed',
                'in-progress', 'answered' => 'inprogress',
                'ringing' => 'ringing',
                default => 'other',
            };
        @endphp
        <tr>
            <td>{{ $call['sno'] }}</td>
            <td>{{ $call['date'] }}</td>
            <td>{{ $call['lead_name'] }}<br><span style="color:#64748b;font-size:9px;">{{ $call['lead_code'] }}</span></td>
            <td>{{ $call['phone'] }}</td>
            <td><span class="badge dir-{{ $call['direction'] === 'inbound' ? 'in' : 'out' }}">{{ ucfirst($call['direction']) }}</span></td>
            <td><span class="badge st-{{ $stSlug }}">{{ ucfirst($call['status']) }}</span></td>
            <td style="font-weight:600;">{{ $call['duration'] }}</td>
            <td>{{ $call['outcome'] ?: '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;padding:20px;color:#94a3b8;">No records found.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">Exported from Insight Tech CRM &mdash; Confidential</div>
</body>
</html>
