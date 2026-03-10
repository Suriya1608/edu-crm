<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telecaller Daily Summary</title>
    <style>
        body { margin: 0; padding: 0; background: #f6f7f8; font-family: Arial, Helvetica, sans-serif; color: #0f172a; }
        .wrapper { max-width: 640px; margin: 32px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .header { background: #137fec; color: #ffffff; padding: 28px 32px; }
        .header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
        .header p  { margin: 0; font-size: 14px; opacity: 0.88; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; margin-bottom: 20px; color: #334155; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead tr { background: #137fec; color: #fff; }
        thead th { padding: 10px 12px; text-align: left; font-weight: 600; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; }
        .footer { background: #f1f5f9; padding: 18px 32px; text-align: center; font-size: 12px; color: #64748b; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-red   { background: #fee2e2; color: #dc2626; }
        .total-row td { font-weight: 700; background: #eff6ff; border-top: 2px solid #137fec; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Telecaller Daily Summary</h1>
            <p>Report for {{ $reportDate }}</p>
        </div>

        <div class="body">
            <p class="greeting">Hi <strong>{{ $managerName }}</strong>,</p>
            <p class="greeting" style="margin-top:-10px;">Here's your team's performance summary for today.</p>

            <table>
                <thead>
                    <tr>
                        <th>Telecaller</th>
                        <th>Calls Made</th>
                        <th>Talk Time</th>
                        <th>Conversions</th>
                        <th>Follow-ups Done</th>
                        <th>Follow-ups Missed</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalCalls        = 0;
                        $totalTalkSeconds  = 0;
                        $totalConversions  = 0;
                        $totalFuDone       = 0;
                        $totalFuMissed     = 0;
                    @endphp

                    @foreach($rows as $row)
                        @php
                            $totalCalls       += $row['calls_made'];
                            $totalTalkSeconds += $row['talk_time_seconds'];
                            $totalConversions += $row['conversions'];
                            $totalFuDone      += $row['followups_done'];
                            $totalFuMissed    += $row['followups_missed'];
                            $mins = intdiv($row['talk_time_seconds'], 60);
                            $secs = $row['talk_time_seconds'] % 60;
                        @endphp
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['calls_made'] }}</td>
                            <td>{{ $mins }}m {{ $secs }}s</td>
                            <td>
                                @if($row['conversions'] > 0)
                                    <span class="badge badge-green">{{ $row['conversions'] }}</span>
                                @else
                                    0
                                @endif
                            </td>
                            <td>{{ $row['followups_done'] }}</td>
                            <td>
                                @if($row['followups_missed'] > 0)
                                    <span class="badge badge-red">{{ $row['followups_missed'] }}</span>
                                @else
                                    0
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    @php
                        $totalMins = intdiv($totalTalkSeconds, 60);
                        $totalSecs = $totalTalkSeconds % 60;
                    @endphp
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td>{{ $totalCalls }}</td>
                        <td>{{ $totalMins }}m {{ $totalSecs }}s</td>
                        <td>{{ $totalConversions }}</td>
                        <td>{{ $totalFuDone }}</td>
                        <td>{{ $totalFuMissed }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="footer">
            This is an automated report from your CRM system. Do not reply to this email.
        </div>
    </div>
</body>
</html>
