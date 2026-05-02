<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Exports\ArrayExport;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\WhatsAppMessage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PerformanceController extends Controller
{
    public function daily()
    {
        $start = now()->startOfDay();
        $end   = now()->endOfDay();
        return $this->renderPerformance('Daily Performance', 'daily', $start, $end);
    }

    public function weekly()
    {
        $start = now()->startOfWeek(Carbon::MONDAY);
        $end   = now()->endOfWeek(Carbon::SUNDAY);
        return $this->renderPerformance('Weekly Performance', 'weekly', $start, $end);
    }

    public function monthly()
    {
        $start = now()->startOfMonth();
        $end   = now()->endOfMonth();
        return $this->renderPerformance('Monthly Summary', 'monthly', $start, $end);
    }

    public function export(Request $request, string $scope)
    {
        $format = $request->input('format', 'excel');

        [$start, $end, $title] = match ($scope) {
            'weekly'  => [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY), 'Weekly Performance'],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth(), 'Monthly Summary'],
            default   => [now()->startOfDay(), now()->endOfDay(), 'Daily Performance'],
        };

        $userId = Auth::id();
        $user   = Auth::user();

        $callsBase = CallLog::where('user_id', $userId)->whereBetween('created_at', [$start, $end]);

        $callsHandled    = (clone $callsBase)->count();
        $talkSeconds     = (int) (clone $callsBase)->sum('duration');
        $avgCallDuration = $callsHandled > 0 ? gmdate('i:s', (int) round($talkSeconds / $callsHandled)) : '00:00';

        $outcomeLabels = [
            'interested'      => 'Interested',
            'not_interested'  => 'Not Interested',
            'call_back_later' => 'Call Back Later',
            'switched_off'    => 'Switched Off',
            'wrong_number'    => 'Wrong Number',
        ];

        $outcomeBreakdown = (clone $callsBase)
            ->selectRaw('outcome, COUNT(*) as cnt')
            ->whereNotNull('outcome')
            ->groupBy('outcome')
            ->pluck('cnt', 'outcome')
            ->toArray();

        $missedCalls = (clone $callsBase)->whereIn('status', ['missed', 'no-answer', 'busy', 'canceled'])->count();

        $totalAssigned = Lead::where('assigned_to', $userId)->count();

        $converted = Lead::where('assigned_to', $userId)
            ->where('status', 'converted')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $conversionPercent = $totalAssigned > 0 ? round(($converted / $totalAssigned) * 100, 1) : 0.0;

        $followupsCompleted = Schema::hasColumn('followups', 'completed_at')
            ? Followup::where('user_id', $userId)->whereNotNull('completed_at')->whereBetween('completed_at', [$start, $end])->count()
            : 0;

        $dailyBreakdown = CallLog::selectRaw('DATE(created_at) as day, COUNT(*) as calls, COALESCE(SUM(duration),0) as talk_secs, COUNT(CASE WHEN duration > 0 THEN 1 END) as answered_calls')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn($r) => [
                'day'       => Carbon::parse($r->day)->format('d M Y'),
                'calls'     => (int) $r->calls,
                'talk_time' => gmdate('H:i:s', max(0, (int) $r->talk_secs)),
                'avg'       => $r->answered_calls > 0 ? gmdate('i:s', (int) round($r->talk_secs / $r->answered_calls)) : '00:00',
            ]);

        $meta = [
            'userName'    => $user->name,
            'period'      => $start->format('d M Y') . ' – ' . $end->format('d M Y'),
            'generatedAt' => now()->format('d M Y, h:i A'),
            'title'       => $title,
            'summary' => [
                'Calls Handled'      => $callsHandled,
                'Total Talk Time'    => gmdate('H:i:s', max(0, $talkSeconds)),
                'Avg Call Duration'  => $avgCallDuration,
                'Conversion Rate'    => $conversionPercent . '%',
                'Followups Done'     => $followupsCompleted,
                'Missed Calls'       => $missedCalls,
            ],
            'outcomeBreakdown' => collect($outcomeBreakdown)->mapWithKeys(fn($cnt, $key) => [$outcomeLabels[$key] ?? $key => $cnt])->toArray(),
            'dailyBreakdown'   => $dailyBreakdown->values()->toArray(),
        ];

        $filename = 'performance-' . $scope . '-' . now()->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.telecaller.performance', $meta)->setPaper('a4', 'portrait');
            return $pdf->download($filename . '.pdf');
        }

        // Excel — two sections: summary sheet rows + daily breakdown rows
        $rows = [];
        foreach ($meta['summary'] as $label => $value) {
            $rows[] = [$label, $value];
        }
        $rows[] = ['', ''];
        $rows[] = ['Outcome', 'Count'];
        foreach ($meta['outcomeBreakdown'] as $label => $cnt) {
            $rows[] = [$label, $cnt];
        }
        $rows[] = ['', ''];
        $rows[] = ['Date', 'Calls', 'Talk Time', 'Avg/Call'];
        foreach ($meta['dailyBreakdown'] as $row) {
            $rows[] = [$row['day'], $row['calls'], $row['talk_time'], $row['avg']];
        }

        return Excel::download(new ArrayExport($rows, ['Metric', 'Value'], $title), $filename . '.xlsx');
    }

    private function prevPeriod(string $scope): array
    {
        return match ($scope) {
            'daily'   => [now()->subDay()->startOfDay(),   now()->subDay()->endOfDay()],
            'weekly'  => [now()->subWeek()->startOfWeek(Carbon::MONDAY), now()->subWeek()->endOfWeek(Carbon::SUNDAY)],
            'monthly' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            default   => [now()->subDay()->startOfDay(),   now()->subDay()->endOfDay()],
        };
    }

    private function trend(float $current, float $previous): array
    {
        if ($previous == 0 && $current == 0) return ['pct' => null, 'dir' => 'flat'];
        if ($previous == 0)                  return ['pct' => null, 'dir' => 'new'];
        $pct = round((($current - $previous) / $previous) * 100, 1);
        return [
            'pct' => abs($pct),
            'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'),
        ];
    }

    private function renderPerformance(string $title, string $scope, Carbon $start, Carbon $end)
    {
        $userId = Auth::id();

        // ── Core call stats ────────────────────────────────────────────────
        $callsBase = CallLog::where('user_id', $userId)->whereBetween('created_at', [$start, $end]);

        $callsHandled = (clone $callsBase)->count();

        $talkSeconds   = (int) (clone $callsBase)->sum('duration');
        $talkTimeLabel = gmdate('H:i:s', max(0, $talkSeconds));
        $talkMinutes   = round($talkSeconds / 60, 1);

        $avgCallDuration = $callsHandled > 0
            ? gmdate('i:s', (int) round($talkSeconds / $callsHandled))
            : '00:00';

        // ── Inbound / Outbound split ───────────────────────────────────────
        $directionRows = (clone $callsBase)
            ->selectRaw('COALESCE(direction, "outbound") as direction, COUNT(*) as cnt, COALESCE(SUM(duration),0) as talk_secs')
            ->groupBy(DB::raw('COALESCE(direction, "outbound")'))
            ->get()
            ->keyBy('direction');

        $inboundCount    = (int) ($directionRows['inbound']->cnt       ?? 0);
        $outboundCount   = (int) ($directionRows['outbound']->cnt      ?? 0);
        $inboundTalkSecs = (int) ($directionRows['inbound']->talk_secs  ?? 0);
        $outboundTalkSecs= (int) ($directionRows['outbound']->talk_secs ?? 0);

        // ── Missed calls ───────────────────────────────────────────────────
        $missedStatuses = ['missed', 'no-answer', 'busy', 'canceled'];

        $missedCalls = (clone $callsBase)
            ->whereIn('status', $missedStatuses)
            ->count();

        // Missed call rate = missed inbound / total inbound (%)
        $missedRate = $inboundCount > 0
            ? round(($missedCalls / $inboundCount) * 100, 1)
            : 0.0;

        // ── WhatsApp activity ──────────────────────────────────────────────
        // Messages on leads assigned to this user
        $waLeadSent = WhatsAppMessage::whereNotNull('lead_id')
            ->whereHas('lead', fn($q) => $q->where('assigned_to', $userId))
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $waLeadReceived = WhatsAppMessage::whereNotNull('lead_id')
            ->whereHas('lead', fn($q) => $q->where('assigned_to', $userId))
            ->where('direction', 'inbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // Messages on campaign contacts assigned to this user
        $waCampaignSent = WhatsAppMessage::whereNotNull('campaign_contact_id')
            ->whereExists(fn($q) => $q->from('campaign_contacts')
                ->whereColumn('campaign_contacts.id', 'whatsapp_messages.campaign_contact_id')
                ->where('campaign_contacts.assigned_to', $userId))
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $waCampaignReceived = WhatsAppMessage::whereNotNull('campaign_contact_id')
            ->whereExists(fn($q) => $q->from('campaign_contacts')
                ->whereColumn('campaign_contacts.id', 'whatsapp_messages.campaign_contact_id')
                ->where('campaign_contacts.assigned_to', $userId))
            ->where('direction', 'inbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $waSent     = $waLeadSent     + $waCampaignSent;
        $waReceived = $waLeadReceived + $waCampaignReceived;
        $waTotal    = $waSent + $waReceived;

        // ── Outcome breakdown ──────────────────────────────────────────────
        $outcomeRows = (clone $callsBase)
            ->selectRaw('outcome, COUNT(*) as cnt')
            ->whereNotNull('outcome')
            ->groupBy('outcome')
            ->pluck('cnt', 'outcome')
            ->toArray();

        $allOutcomes = [
            'interested'      => 0,
            'not_interested'  => 0,
            'call_back_later' => 0,
            'switched_off'    => 0,
            'wrong_number'    => 0,
        ];
        $outcomeBreakdown = array_merge($allOutcomes, $outcomeRows);

        // ── Lead stats ─────────────────────────────────────────────────────
        $leadsBase = Lead::where('assigned_to', $userId);

        $totalAssigned = (clone $leadsBase)->whereBetween('created_at', [$start, $end])->count();

        $converted = (clone $leadsBase)
            ->where('status', 'converted')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $conversionPercent = $totalAssigned > 0
            ? round(($converted / $totalAssigned) * 100, 1)
            : 0.0;

        // Lead status distribution scoped to the selected period
        $leadStatusRows = Lead::where('assigned_to', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // ── Followup stats ─────────────────────────────────────────────────
        $followupsCompleted = 0;
        $followupsScheduled = 0;
        $pendingFollowups   = 0;

        // Scheduled = followups whose due date falls within the period
        $followupsScheduled = Followup::where('user_id', $userId)
            ->whereBetween('next_followup', [$start->toDateString(), $end->toDateString()])
            ->count();

        if (Schema::hasColumn('followups', 'completed_at')) {
            $followupsCompleted = Followup::where('user_id', $userId)
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$start, $end])
                ->count();

            $pendingFollowups = Followup::where('user_id', $userId)
                ->whereNull('completed_at')
                ->where('next_followup', '<=', now()->toDateString())
                ->count();
        }

        $followupCompletionRate = $followupsScheduled > 0
            ? round(($followupsCompleted / $followupsScheduled) * 100, 1)
            : null;

        // ── Response time ──────────────────────────────────────────────────
        $responseSeconds  = $this->averageResponseTimeSeconds($userId, $start, $end);
        $responseTimeLabel = $this->formatSeconds($responseSeconds);

        // ── Daily breakdown ────────────────────────────────────────────────
        $dailyBreakdown = CallLog::selectRaw(
                'DATE(created_at) as day, COUNT(*) as calls, COALESCE(SUM(duration),0) as talk_seconds, COUNT(CASE WHEN duration > 0 THEN 1 END) as answered_calls'
            )
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn($row) => [
                'day'            => Carbon::parse($row->day)->format('d M Y'),
                'calls'          => (int) $row->calls,
                'talk_time'      => gmdate('H:i:s', max(0, (int) $row->talk_seconds)),
                'talk_secs'      => (int) $row->talk_seconds,
                'answered_calls' => (int) $row->answered_calls,
            ]);

        // Best day
        $bestDay = $dailyBreakdown->sortByDesc('calls')->first();

        // ── Hourly call heatmap (today / this week) ────────────────────────
        $hourlyData = CallLog::selectRaw('HOUR(created_at) as hr, COUNT(*) as cnt')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->pluck('cnt', 'hr')
            ->toArray();

        $hourlyBreakdown = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyBreakdown[] = ['hour' => $h, 'calls' => (int) ($hourlyData[$h] ?? 0)];
        }

        // ── Call target (unique leads contacted, not total calls) ──────────
        $totalLeadsEver = Lead::where('assigned_to', $userId)->count();

        // Count distinct leads actually called in this period (ignore repeat calls to same lead)
        $uniqueLeadsCalled = (clone $callsBase)
            ->whereNotNull('lead_id')
            ->distinct('lead_id')
            ->count('lead_id');

        // Target = call every assigned lead at least once (always achievable)
        $callTarget    = $totalLeadsEver;
        $callTargetPct = $callTarget > 0
            ? min(100, (int) round(($uniqueLeadsCalled / $callTarget) * 100))
            : 0;

        // ── Previous period comparison ─────────────────────────────────────
        [$prevStart, $prevEnd] = $this->prevPeriod($scope);

        $prevCalls = CallLog::where('user_id', $userId)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $prevTalkSecs  = (int) CallLog::where('user_id', $userId)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('duration');
        $prevTalkMinutes = round($prevTalkSecs / 60, 1);

        $prevAssigned = Lead::where('assigned_to', $userId)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();
        $prevConverted = Lead::where('assigned_to', $userId)
            ->where('status', 'converted')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();
        $prevConvPct = $prevAssigned > 0 ? round(($prevConverted / $prevAssigned) * 100, 1) : 0.0;

        $prevFollowups = 0;
        if (Schema::hasColumn('followups', 'completed_at')) {
            $prevFollowups = Followup::where('user_id', $userId)
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$prevStart, $prevEnd])
                ->count();
        }

        $prevMissedCalls = CallLog::where('user_id', $userId)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->whereIn('status', $missedStatuses)
            ->count();

        $prevWaTotal =
            WhatsAppMessage::whereNotNull('lead_id')
                ->whereHas('lead', fn($q) => $q->where('assigned_to', $userId))
                ->whereBetween('created_at', [$prevStart, $prevEnd])
                ->count()
            + WhatsAppMessage::whereNotNull('campaign_contact_id')
                ->whereExists(fn($q) => $q->from('campaign_contacts')
                    ->whereColumn('campaign_contacts.id', 'whatsapp_messages.campaign_contact_id')
                    ->where('campaign_contacts.assigned_to', $userId))
                ->whereBetween('created_at', [$prevStart, $prevEnd])
                ->count();

        $prevLabel = match ($scope) {
            'daily'   => 'yesterday',
            'weekly'  => 'last week',
            'monthly' => 'last month',
            default   => 'previous period',
        };

        $trends = [
            'calls'       => $this->trend($callsHandled,            $prevCalls),
            'talkTime'    => $this->trend($talkMinutes,              $prevTalkMinutes),
            'conversion'  => $this->trend((float) $conversionPercent, $prevConvPct),
            'followups'   => $this->trend($followupsCompleted,       $prevFollowups),
            'missedCalls' => $this->trend($missedCalls,              $prevMissedCalls),
            'waMessages'  => $this->trend($waTotal,                  $prevWaTotal),
        ];

        // ── Productivity score (0–100) ─────────────────────────────────────
        // Weighted: calls 40%, conversion 30%, followups 20%, response speed 10%
        // Targets scale by working days so the bar doesn't saturate on a single day
        // of a weekly/monthly view (base: 20 calls/day, 10 followups/day)
        $workingDays   = $this->workingDays($start, $end);
        $callScore     = min(100, (int) round($callsHandled       / (20 * $workingDays) * 100));
        $convScore     = min(100, $conversionPercent * 1.0);
        $followupScore = min(100, (int) round($followupsCompleted / (10 * $workingDays) * 100));
        $responseScore = $responseSeconds > 0
            ? max(0, 100 - round($responseSeconds / 3600 * 50))   // 2h response = 0
            : 50; // neutral when no response data yet

        $productivityScore = (int) round(
            $callScore * 0.4 +
            $convScore * 0.3 +
            $followupScore * 0.2 +
            $responseScore * 0.1
        );

        return Inertia::render('Telecaller/Performance/Index', [
            'title'              => $title,
            'scope'              => $scope,
            'period'             => $start->format('d M Y') . ' – ' . $end->format('d M Y'),
            'dateFrom'           => $start->format('Y-m-d'),
            'dateTo'             => $end->format('Y-m-d'),

            // Core metrics
            'callsHandled'       => $callsHandled,
            'talkTimeLabel'      => $talkTimeLabel,
            'talkMinutes'        => $talkMinutes,
            'avgCallDuration'    => $avgCallDuration,
            'conversionPercent'  => number_format($conversionPercent, 1),
            'totalAssigned'      => $totalAssigned,
            'followupsCompleted'     => $followupsCompleted,
            'followupsScheduled'     => $followupsScheduled,
            'followupCompletionRate' => $followupCompletionRate,
            'pendingFollowups'       => $pendingFollowups,
            'responseTimeLabel'  => $responseTimeLabel,

            // WhatsApp
            'waSent'             => $waSent,
            'waReceived'         => $waReceived,
            'waTotal'            => $waTotal,

            // Direction split
            'missedCalls'        => $missedCalls,
            'missedRate'         => $missedRate,
            'inboundCount'       => $inboundCount,
            'outboundCount'      => $outboundCount,
            'inboundTalkSecs'    => $inboundTalkSecs,
            'outboundTalkSecs'   => $outboundTalkSecs,

            // Breakdowns
            'outcomeBreakdown'   => $outcomeBreakdown,
            'leadStatusRows'     => $leadStatusRows,
            'dailyBreakdown'     => $dailyBreakdown->values(),
            'hourlyBreakdown'    => $hourlyBreakdown,
            'bestDay'            => $bestDay,

            // Score
            'productivityScore'  => $productivityScore,

            // Target
            'callTarget'         => $callTarget,
            'callTargetPct'      => $callTargetPct,
            'uniqueLeadsCalled'  => $uniqueLeadsCalled,
            'totalLeadsEver'     => $totalLeadsEver,

            // Trends
            'trends'             => $trends,
            'prevPeriodLabel'    => $prevLabel,
        ]);
    }

    private function averageResponseTimeSeconds(int $userId, Carbon $start, Carbon $end): int
    {
        // Response time = first outbound call_log.created_at − lead.created_at
        // (leads have no assigned_at column; created_at is the assignment moment)
        $firstCallPerLead = DB::table('call_logs')
            ->selectRaw('lead_id, MIN(created_at) as first_call_at')
            ->where('user_id', $userId)
            ->whereNotNull('lead_id')
            ->groupBy('lead_id');

        $row = DB::table('leads')
            ->joinSub($firstCallPerLead, 'fc', fn($j) => $j->on('fc.lead_id', '=', 'leads.id'))
            ->where('leads.assigned_to', $userId)
            ->whereBetween('leads.created_at', [$start, $end])
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, leads.created_at, fc.first_call_at)) as avg_seconds')
            ->first();

        return (int) round(max(0, (float) ($row->avg_seconds ?? 0)));
    }

    private function formatSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    private function workingDays(Carbon $start, Carbon $end): int
    {
        $days    = 0;
        $current = $start->copy()->startOfDay();
        $endDay  = $end->copy()->startOfDay();
        while ($current->lte($endDay)) {
            if ($current->isWeekday()) {
                $days++;
            }
            $current->addDay();
        }
        return max(1, $days);
    }
}
