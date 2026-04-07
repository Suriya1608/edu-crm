@extends('emails.layout')

@section('email-title')Telecaller Daily Summary — {{ $reportDate }}@endsection

@section('header-subtitle')
<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:500;color:rgba(255,255,255,0.80);letter-spacing:.3px;display:block;margin-top:8px;">
    Daily Performance Report &mdash; {{ $reportDate }}
</span>
@endsection

@section('extra-styles')
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr { background-color: #c62828; }
        .data-table thead th { padding: 10px 12px; text-align: left; font-weight: 600; color: #ffffff; font-family: Arial, Helvetica, sans-serif; }
        .data-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        .data-table tbody td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; font-family: Arial, Helvetica, sans-serif; color: #374151; }
        .data-table .total-row td { font-weight: 700; background-color: #fff5f5; border-top: 2px solid #c62828; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-red   { background: #fee2e2; color: #dc2626; }
@endsection

@section('content')
<p style="font-size:15px;color:#334155;margin:0 0 6px 0;">Hi <strong>{{ $managerName }}</strong>,</p>
<p style="font-size:15px;color:#334155;margin:0 0 24px 0;">Here&rsquo;s your team&rsquo;s performance summary for today.</p>

<table class="data-table" role="presentation">
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
@endsection

@section('footer-note')
<tr>
    <td align="center" style="padding-bottom:10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">
        This is an automated report from your CRM system. Do not reply to this email.
    </td>
</tr>
@endsection
