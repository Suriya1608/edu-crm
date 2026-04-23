<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

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
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        $conversionPercent = $totalAssigned > 0
            ? round(($converted / $totalAssigned) * 100, 1)
            : 0.0;

        // Lead status distribution (all time assigned, grouped by status)
        $leadStatusRows = Lead::where('assigned_to', $userId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // ── Followup stats ─────────────────────────────────────────────────
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

        // ── Response time ──────────────────────────────────────────────────
        $responseSeconds  = $this->averageResponseTimeSeconds($userId, $start, $end);
        $responseTimeLabel = $this->formatSeconds($responseSeconds);

        // ── Daily breakdown ────────────────────────────────────────────────
        $dailyBreakdown = CallLog::selectRaw(
                'DATE(created_at) as day, COUNT(*) as calls, COALESCE(SUM(duration),0) as talk_seconds'
            )
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn($row) => [
                'day'       => Carbon::parse($row->day)->format('d M Y'),
                'calls'     => (int) $row->calls,
                'talk_time' => gmdate('H:i:s', max(0, (int) $row->talk_seconds)),
                'talk_secs' => (int) $row->talk_seconds,
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

        // ── Productivity score (0–100) ─────────────────────────────────────
        // Weighted: calls 40%, conversion 30%, followups 20%, response speed 10%
        $callScore       = min(100, $callsHandled * 5);           // 20 calls = 100
        $convScore       = min(100, $conversionPercent * 1.0);
        $followupScore   = min(100, $followupsCompleted * 10);    // 10 followups = 100
        $responseScore   = $responseSeconds > 0
            ? max(0, 100 - round($responseSeconds / 3600 * 50))   // 2h response = 0
            : 0;

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

            // Core metrics
            'callsHandled'       => $callsHandled,
            'talkTimeLabel'      => $talkTimeLabel,
            'talkMinutes'        => $talkMinutes,
            'avgCallDuration'    => $avgCallDuration,
            'conversionPercent'  => number_format($conversionPercent, 1),
            'totalAssigned'      => $totalAssigned,
            'followupsCompleted' => $followupsCompleted,
            'pendingFollowups'   => $pendingFollowups,
            'responseTimeLabel'  => $responseTimeLabel,

            // Breakdowns
            'outcomeBreakdown'   => $outcomeBreakdown,
            'leadStatusRows'     => $leadStatusRows,
            'dailyBreakdown'     => $dailyBreakdown->values(),
            'hourlyBreakdown'    => $hourlyBreakdown,
            'bestDay'            => $bestDay,

            // Score
            'productivityScore'  => $productivityScore,
        ]);
    }

    private function averageResponseTimeSeconds(int $userId, Carbon $start, Carbon $end): int
    {
        $firstActivityPerLead = DB::table('lead_activities')
            ->selectRaw('lead_id, MIN(created_at) as first_activity_at')
            ->where('user_id', $userId)
            ->groupBy('lead_id');

        $row = DB::table('leads')
            ->joinSub($firstActivityPerLead, 'fa', fn($j) => $j->on('fa.lead_id', '=', 'leads.id'))
            ->where('leads.assigned_to', $userId)
            ->whereBetween('leads.created_at', [$start, $end])
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, leads.created_at, fa.first_activity_at)) as avg_seconds')
            ->first();

        return (int) round((float) ($row->avg_seconds ?? 0));
    }

    private function formatSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
