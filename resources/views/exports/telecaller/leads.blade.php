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
    .meta { display: flex; justify-content: space-between; padding: 0 20px 12px; color: #64748b; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; margin: 0 20px; width: calc(100% - 40px); }
    thead th { background: #f1f5f9; color: #374151; font-weight: 700; padding: 8px 10px; text-align: left; border-bottom: 2px solid #6366f1; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; font-size: 10px; }
    .badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
    .badge-new { background: #dbeafe; color: #1d4ed8; }
    .badge-interested { background: #d1fae5; color: #065f46; }
    .badge-follow-up { background: #fef3c7; color: #92400e; }
    .badge-contacted { background: #e0e7ff; color: #3730a3; }
    .badge-not-interested { background: #fee2e2; color: #991b1b; }
    .badge-converted { background: #d1fae5; color: #065f46; }
    .badge-lost { background: #fee2e2; color: #991b1b; }
    .footer { text-align: center; margin-top: 16px; color: #94a3b8; font-size: 9px; padding: 0 20px; }
    .total { padding: 8px 20px; font-size: 11px; font-weight: 700; color: #374151; }
</style>
</head>
<body>
<div class="header">
    <h1>My Leads Export</h1>
    <p>{{ $userName }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</p>
</div>
<div class="total">Total Records: {{ count($leads) }}</div>
<table>
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:10%">Lead Code</th>
            <th style="width:18%">Name</th>
            <th style="width:12%">Phone</th>
            <th style="width:18%">Email</th>
            <th style="width:15%">Course</th>
            <th style="width:10%">Status</th>
            <th style="width:12%">Created</th>
        </tr>
    </thead>
    <tbody>
        @forelse($leads as $lead)
        @php $slug = strtolower(str_replace(' ', '-', $lead['status'])); @endphp
        <tr>
            <td>{{ $lead['sno'] }}</td>
            <td>{{ $lead['lead_code'] }}</td>
            <td>{{ $lead['name'] }}</td>
            <td>{{ $lead['phone'] }}</td>
            <td>{{ $lead['email'] ?: '—' }}</td>
            <td>{{ $lead['course'] }}</td>
            <td><span class="badge badge-{{ $slug }}">{{ $lead['status'] }}</span></td>
            <td>{{ $lead['created_at'] }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;padding:20px;color:#94a3b8;">No records found.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">Exported from Insight Tech CRM &mdash; Confidential</div>
</body>
</html>
