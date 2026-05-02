<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1e293b; padding: 24px; }
        h2 { font-size: 15px; font-weight: 700; color: #4f46e5; margin-bottom: 4px; }
        .subtitle { font-size: 10px; color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        thead tr th {
            background: #4f46e5;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 7px 8px;
            text-align: left;
            border: 1px solid #4338ca;
        }
        tbody tr td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 10.5px;
            color: #0f172a;
        }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .footer { margin-top: 14px; font-size: 9.5px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <div class="subtitle">Generated on {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; Total: {{ count($rows) }} records</div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headers) }}" style="text-align:center;color:#64748b;">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Edu CRM &mdash; Confidential</div>
</body>
</html>
