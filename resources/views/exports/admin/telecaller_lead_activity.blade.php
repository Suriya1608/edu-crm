<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size:10px; color:#1e293b; background:#fff; }

/* ── Header ── */
.hdr { background:#6366f1; color:#fff; padding:12px 16px; margin-bottom:12px; }
.hdr-title { font-size:15px; font-weight:700; margin-bottom:2px; }
.hdr-sub   { font-size:9px; opacity:.85; }

/* ── Telecaller block ── */
.tc-wrap { margin:0 12px 16px; }
.tc-name { background:#0f172a; color:#fff; padding:6px 10px; font-size:11px; font-weight:700; }

/* Stat row — 5 inline-block cells */
.stat-row { border:1px solid #e2e8f0; border-top:none; background:#f8fafc; padding:6px 0; }
.stat-cell {
    display:inline-block;
    width:19%;
    text-align:center;
    vertical-align:top;
    border-right:1px solid #e2e8f0;
    padding:3px 0;
}
.stat-cell:last-child { border-right:none; }
.stat-val  { display:block; font-size:13px; font-weight:700; color:#6366f1; }
.stat-lbl  { display:block; font-size:8px; color:#64748b; margin-top:1px; }
.stat-conv { color:#10b981; }
.stat-call { color:#06b6d4; }
.stat-msg  { color:#25d366; }
.stat-meet { color:#f59e0b; }

/* ── Lead block ── */
.lead-wrap { border:1px solid #e2e8f0; border-top:none; }
.lead-hdr  { background:#f1f5f9; padding:5px 8px; border-bottom:1px solid #e2e8f0; }
.lead-code { font-size:9px; font-weight:700; color:#6366f1; }
.lead-name { font-size:11px; font-weight:700; color:#0f172a; margin-left:4px; }
.lead-meta { font-size:8px; color:#64748b; margin-top:2px; }
.lead-body { padding:4px 8px 6px; }

/* badges */
.badge { display:inline-block; padding:1px 5px; border-radius:6px; font-size:8px; font-weight:700; }
.b-converted { background:#d1fae5; color:#065f46; }
.b-active    { background:#dbeafe; color:#1e40af; }
.b-lost      { background:#fee2e2; color:#991b1b; }
.b-new       { background:#fef3c7; color:#92400e; }
.b-other     { background:#f1f5f9; color:#475569; }
.b-final     { background:#d1fae5; color:#065f46; }
.b-date      { background:#f1f5f9; color:#475569; }
.b-calls     { background:#e0f9ff; color:#06b6d4; }
.b-msgs      { background:#dcfce7; color:#16a34a; }
.b-meets     { background:#fef9c3; color:#b45309; }

/* ── Section labels ── */
.sec-lbl { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6366f1; margin:6px 0 3px; }

/* ── Tables ── */
table { width:100%; border-collapse:collapse; margin-bottom:3px; }
thead th { background:#f1f5f9; color:#374151; font-weight:700; padding:4px 6px; font-size:8px; text-transform:uppercase; letter-spacing:.3px; border-bottom:1px solid #e2e8f0; text-align:left; }
tbody td { padding:3px 6px; font-size:9px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
tbody tr:last-child td { border-bottom:none; }

.dir-out { color:#6366f1; font-weight:600; }
.dir-in  { color:#10b981; font-weight:600; }
.empty   { color:#94a3b8; font-style:italic; font-size:8px; padding:2px 0; }

/* ── Footer ── */
.footer { text-align:center; margin-top:12px; color:#94a3b8; font-size:8px; padding-bottom:8px; }
</style>
</head>
<body>

<div class="hdr">
    <div class="hdr-title">Telecaller Lead Activity Report</div>
    <div class="hdr-sub">Period: {{ $periodLabel }} &nbsp;|&nbsp; Generated: {{ $generatedAt }}</div>
</div>

@forelse ($telecallers as $tc)
@php
    $leads         = collect($tc['leads']);
    $totalCalls    = $leads->sum('call_count');
    $totalMsgs     = $leads->sum('msg_count');
    $totalMeetings = $leads->sum('meeting_count');
    $converted     = $leads->where('status', 'converted')->count();
@endphp
<div class="tc-wrap">
    <div class="tc-name">{{ $tc['name'] }}</div>

    {{-- Stat row --}}
    <div class="stat-row">
        <div class="stat-cell">
            <span class="stat-val">{{ $leads->count() }}</span>
            <span class="stat-lbl">Leads</span>
        </div>
        <div class="stat-cell">
            <span class="stat-val stat-conv">{{ $converted }}</span>
            <span class="stat-lbl">Converted</span>
        </div>
        <div class="stat-cell">
            <span class="stat-val stat-call">{{ $totalCalls }}</span>
            <span class="stat-lbl">Calls</span>
        </div>
        <div class="stat-cell">
            <span class="stat-val stat-msg">{{ $totalMsgs }}</span>
            <span class="stat-lbl">Messages</span>
        </div>
        <div class="stat-cell">
            <span class="stat-val stat-meet">{{ $totalMeetings }}</span>
            <span class="stat-lbl">Meetings</span>
        </div>
    </div>

    {{-- Lead rows --}}
    <div class="lead-wrap">
    @forelse ($tc['leads'] as $lead)
    @php
        $stCls = match($lead['status']) {
            'converted' => 'b-converted',
            'active'    => 'b-active',
            'lost'      => 'b-lost',
            'new'       => 'b-new',
            default     => 'b-other',
        };
        $hasCalls    = count($lead['calls'])    > 0;
        $hasMsgs     = count($lead['messages']) > 0;
        $hasMeetings = count($lead['meetings']) > 0;
    @endphp

    <div class="lead-hdr">
        <span class="lead-code">{{ $lead['lead_code'] }}</span>
        <span class="lead-name">{{ $lead['name'] }}</span>
        &nbsp;<span class="badge {{ $stCls }}">{{ ucfirst($lead['status']) }}</span>
        @if($lead['final_course'] !== '—')
            &nbsp;<span class="badge b-final">Final: {{ $lead['final_course'] }}</span>
        @endif
        &nbsp;<span class="badge b-date">{{ $lead['created_at'] }}</span>
        &nbsp;<span class="badge b-calls">{{ $lead['call_count'] }} calls</span>
        &nbsp;<span class="badge b-msgs">{{ $lead['msg_count'] }} msgs</span>
        &nbsp;<span class="badge b-meets">{{ $lead['meeting_count'] }} meetings</span>
        <div class="lead-meta">
            {{ $lead['phone'] }}
            @if($lead['source'])  | Source: {{ $lead['source'] }} @endif
            | Course: {{ $lead['course'] }}
        </div>
    </div>

    <div class="lead-body">

        {{-- CALLS --}}
        <div class="sec-lbl">Call History</div>
        @if($hasCalls)
        <table>
            <thead>
                <tr>
                    <th style="width:22%">Date &amp; Time</th>
                    <th style="width:16%">Direction</th>
                    <th style="width:18%">Status</th>
                    <th style="width:18%">Outcome</th>
                    <th style="width:12%">Duration</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lead['calls'] as $c)
                <tr>
                    <td>{{ $c['date'] }}</td>
                    <td class="{{ $c['direction'] === 'outbound' ? 'dir-out' : 'dir-in' }}">{{ ucfirst($c['direction']) }}</td>
                    <td>{{ ucfirst($c['status']) }}</td>
                    <td>{{ ucfirst($c['outcome']) }}</td>
                    <td>{{ $c['duration'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No calls recorded.</div>
        @endif

        {{-- MESSAGES --}}
        <div class="sec-lbl">WhatsApp Messages</div>
        @if($hasMsgs)
        <table>
            <thead>
                <tr>
                    <th style="width:22%">Date &amp; Time</th>
                    <th style="width:16%">Direction</th>
                    <th style="width:12%">Type</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lead['messages'] as $m)
                <tr>
                    <td>{{ $m['date'] }}</td>
                    <td class="{{ $m['direction'] === 'outbound' ? 'dir-out' : 'dir-in' }}">{{ ucfirst($m['direction']) }}</td>
                    <td>{{ ucfirst($m['type']) }}</td>
                    <td>{{ mb_strimwidth($m['body'], 0, 130, '...') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No messages recorded.</div>
        @endif

        {{-- MEETINGS --}}
        <div class="sec-lbl">Meetings</div>
        @if($hasMeetings)
        <table>
            <thead>
                <tr>
                    <th style="width:28%">Title</th>
                    <th style="width:22%">Date &amp; Time</th>
                    <th style="width:14%">Type</th>
                    <th style="width:14%">Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lead['meetings'] as $mt)
                <tr>
                    <td>{{ $mt['title'] }}</td>
                    <td>{{ $mt['time'] }}</td>
                    <td>{{ ucfirst($mt['type']) }}</td>
                    <td>{{ ucfirst($mt['status']) }}</td>
                    <td>{{ mb_strimwidth($mt['notes'], 0, 80, '...') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty">No meetings recorded.</div>
        @endif

    </div>{{-- /lead-body --}}

    @empty
    <div style="padding:8px;color:#94a3b8;font-style:italic;font-size:9px">No leads found for this period.</div>
    @endforelse
    </div>{{-- /lead-wrap --}}
</div>
@empty
<p style="text-align:center;color:#94a3b8;padding:20px">No data found.</p>
@endforelse

<div class="footer">
    Edu-CRM &nbsp;&bull;&nbsp; Telecaller Lead Activity Report &nbsp;&bull;&nbsp; {{ $generatedAt }}
</div>
</body>
</html>
