<?php

namespace App\Http\Controllers\Manager;

use App\Helpers\IdHasher;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class TelecallerPerformanceController extends Controller
{
    public function show(Request $request, string $hash)
    {
        $id = IdHasher::decode($hash);
        abort_if($id === null, 404);

        $managerId = Auth::id();

        // Verify this telecaller has leads assigned by this manager
        $telecaller = User::where('role', 'telecaller')
            ->where('id', $id)
            ->whereExists(fn($q) => $q->from('leads')
                ->whereColumn('leads.assigned_to', 'users.id')
                ->where('leads.assigned_by', $managerId))
            ->firstOrFail();

        // Date range — default to current month
        $fromDate = $request->filled('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $toDate = $request->filled('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        if ($toDate->lt($fromDate)) {
            $toDate = $fromDate->copy()->endOfDay();
        }

        return $this->renderPerformance($telecaller, $fromDate, $toDate);
    }

    private function renderPerformance(User $telecaller, Carbon $start, Carbon $end)
    {
        $userId    = $telecaller->id;
        $managerId = Auth::id();

        // ── Core call stats ────────────────────────────────────────────────
        $callsBase = CallLog::where('user_id', $userId)->whereBetween('created_at', [$start, $end]);

        $callsHandled    = (clone $callsBase)->count();
        $talkSeconds     = (int) (clone $callsBase)->sum('duration');
        $talkTimeLabel   = gmdate('H:i:s', max(0, $talkSeconds));
        $talkMinutes     = round($talkSeconds / 60, 1);
        $avgCallDuration = $callsHandled > 0
            ? gmdate('i:s', (int) round($talkSeconds / $callsHandled))
            : '00:00';

        // ── Inbound / Outbound split ───────────────────────────────────────
        $directionRows = (clone $callsBase)
            ->selectRaw('COALESCE(direction, "outbound") as direction, COUNT(*) as cnt, COALESCE(SUM(duration),0) as talk_secs')
            ->groupBy(DB::raw('COALESCE(direction, "outbound")'))
            ->get()->keyBy('direction');

        $inboundCount     = (int) ($directionRows['inbound']->cnt        ?? 0);
        $outboundCount    = (int) ($directionRows['outbound']->cnt       ?? 0);
        $inboundTalkSecs  = (int) ($directionRows['inbound']->talk_secs  ?? 0);
        $outboundTalkSecs = (int) ($directionRows['outbound']->talk_secs ?? 0);

        // ── Missed calls ───────────────────────────────────────────────────
        $missedStatuses = ['missed', 'no-answer', 'busy', 'canceled'];
        $missedCalls    = (clone $callsBase)->whereIn('status', $missedStatuses)->count();
        $missedRate     = $inboundCount > 0 ? round(($missedCalls / $inboundCount) * 100, 1) : 0.0;

        // ── WhatsApp ───────────────────────────────────────────────────────
        $waSent = WhatsAppMessage::whereNotNull('lead_id')
            ->whereHas('lead', fn($q) => $q->where('assigned_to', $userId))
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $waReceived = WhatsAppMessage::whereNotNull('lead_id')
            ->whereHas('lead', fn($q) => $q->where('assigned_to', $userId))
            ->where('direction', 'inbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $waTotal = $waSent + $waReceived;

        // ── Outcome breakdown ──────────────────────────────────────────────
        $outcomeRows = (clone $callsBase)
            ->selectRaw('outcome, COUNT(*) as cnt')
            ->whereNotNull('outcome')
            ->groupBy('outcome')
            ->pluck('cnt', 'outcome')
            ->toArray();

        $outcomeBreakdown = array_merge([
            'interested'      => 0,
            'not_interested'  => 0,
            'call_back_later' => 0,
            'switched_off'    => 0,
            'wrong_number'    => 0,
        ], $outcomeRows);

        // ── Lead stats ─────────────────────────────────────────────────────
        $leadsBase = Lead::where('assigned_to', $userId)->where('assigned_by', $managerId);

        $totalAssigned = (clone $leadsBase)->whereBetween('created_at', [$start, $end])->count();
        $converted     = (clone $leadsBase)->where('status', 'converted')->whereBetween('created_at', [$start, $end])->count();

        $conversionPercent = $totalAssigned > 0
            ? round(($converted / $totalAssigned) * 100, 1)
            : 0.0;

        $leadStatusRows = (clone $leadsBase)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // ── Followup stats ─────────────────────────────────────────────────
        $followupsScheduled = Followup::where('user_id', $userId)
            ->whereBetween('next_followup', [$start->toDateString(), $end->toDateString()])
            ->count();

        $followupsCompleted = 0;
        $pendingFollowups   = 0;

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
        $responseSeconds   = $this->averageResponseTimeSeconds($userId, $start, $end);
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

        $bestDay = $dailyBreakdown->sortByDesc('calls')->first();

        // ── Hourly heatmap ─────────────────────────────────────────────────
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

        // ── Call target ────────────────────────────────────────────────────
        $totalLeadsEver    = Lead::where('assigned_to', $userId)->count();
        $uniqueLeadsCalled = (clone $callsBase)->whereNotNull('lead_id')->distinct('lead_id')->count('lead_id');
        $callTarget        = $totalLeadsEver;
        $callTargetPct     = $callTarget > 0
            ? min(100, (int) round(($uniqueLeadsCalled / $callTarget) * 100))
            : 0;

        // ── Productivity score ─────────────────────────────────────────────
        $workingDays   = $this->workingDays($start, $end);
        $callScore     = min(100, (int) round($callsHandled / (20 * $workingDays) * 100));
        $convScore     = min(100, $conversionPercent * 1.0);
        $followupScore = min(100, (int) round($followupsCompleted / (10 * $workingDays) * 100));
        $responseScore = $responseSeconds > 0
            ? max(0, 100 - round($responseSeconds / 3600 * 50))
            : 50;

        $productivityScore = (int) round(
            $callScore * 0.4 + $convScore * 0.3 + $followupScore * 0.2 + $responseScore * 0.1
        );

        return Inertia::render('Manager/Telecallers/Performance', [
            'telecaller'             => ['id' => $telecaller->id, 'encoded_id' => IdHasher::encode($telecaller->id), 'name' => $telecaller->name],
            'dateFrom'               => $start->format('Y-m-d'),
            'dateTo'                 => $end->format('Y-m-d'),
            'period'                 => $start->format('d M Y') . ' – ' . $end->format('d M Y'),

            'callsHandled'           => $callsHandled,
            'talkTimeLabel'          => $talkTimeLabel,
            'talkMinutes'            => $talkMinutes,
            'avgCallDuration'        => $avgCallDuration,
            'conversionPercent'      => number_format($conversionPercent, 1),
            'totalAssigned'          => $totalAssigned,
            'followupsCompleted'     => $followupsCompleted,
            'followupsScheduled'     => $followupsScheduled,
            'followupCompletionRate' => $followupCompletionRate,
            'pendingFollowups'       => $pendingFollowups,
            'responseTimeLabel'      => $responseTimeLabel,

            'waSent'                 => $waSent,
            'waReceived'             => $waReceived,
            'waTotal'                => $waTotal,

            'missedCalls'            => $missedCalls,
            'missedRate'             => $missedRate,
            'inboundCount'           => $inboundCount,
            'outboundCount'          => $outboundCount,
            'inboundTalkSecs'        => $inboundTalkSecs,
            'outboundTalkSecs'       => $outboundTalkSecs,

            'outcomeBreakdown'       => $outcomeBreakdown,
            'leadStatusRows'         => $leadStatusRows,
            'dailyBreakdown'         => $dailyBreakdown->values(),
            'hourlyBreakdown'        => $hourlyBreakdown,
            'bestDay'                => $bestDay,

            'productivityScore'      => $productivityScore,
            'callTarget'             => $callTarget,
            'callTargetPct'          => $callTargetPct,
            'uniqueLeadsCalled'      => $uniqueLeadsCalled,
            'totalLeadsEver'         => $totalLeadsEver,
        ]);
    }

    private function averageResponseTimeSeconds(int $userId, Carbon $start, Carbon $end): int
    {
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
        return sprintf('%02d:%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60), $seconds % 60);
    }

    private function workingDays(Carbon $start, Carbon $end): int
    {
        $days    = 0;
        $current = $start->copy()->startOfDay();
        $endDay  = $end->copy()->startOfDay();
        while ($current->lte($endDay)) {
            if ($current->isWeekday()) $days++;
            $current->addDay();
        }
        return max(1, $days);
    }
}
