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

class PerformanceController extends Controller
{
    public function daily()
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();
        return $this->renderPerformance('Daily Performance', 'daily', $start, $end);
    }

    public function weekly()
    {
        $start = now()->startOfWeek(Carbon::MONDAY);
        $end = now()->endOfWeek(Carbon::SUNDAY);
        return $this->renderPerformance('Weekly Performance', 'weekly', $start, $end);
    }

    public function monthly()
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        return $this->renderPerformance('Monthly Summary', 'monthly', $start, $end);
    }

    private function renderPerformance(string $title, string $scope, Carbon $start, Carbon $end)
    {
        $userId = Auth::id();

        $callsHandled = CallLog::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $totalAssignedLeadsInWindow = Lead::where('assigned_to', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $convertedLeadsInWindow = Lead::where('assigned_to', $userId)
            ->where('status', 'converted')
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        $conversionPercent = $totalAssignedLeadsInWindow > 0
            ? round(($convertedLeadsInWindow / $totalAssignedLeadsInWindow) * 100, 2)
            : 0.0;

        $followupCompletedQuery = Followup::where('user_id', $userId);
        if (Schema::hasColumn('followups', 'completed_at')) {
            $followupCompletedQuery->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$start, $end]);
        } else {
            $followupCompletedQuery->whereRaw('1=0');
        }
        $followupsCompleted = $followupCompletedQuery->count();

        $responseSeconds = $this->averageResponseTimeSeconds($userId, $start, $end);
        $responseTimeLabel = $this->formatSeconds($responseSeconds);

        $dailyBreakdown = CallLog::selectRaw('DATE(created_at) as day, COUNT(*) as calls, COALESCE(SUM(duration),0) as talk_seconds')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        return view('telecaller.performance.index', compact(
            'title',
            'scope',
            'start',
            'end',
            'callsHandled',
            'conversionPercent',
            'followupsCompleted',
            'responseSeconds',
            'responseTimeLabel',
            'dailyBreakdown'
        ));
    }

    private function averageResponseTimeSeconds(int $userId, Carbon $start, Carbon $end): int
    {
        $firstActivityPerLead = DB::table('lead_activities')
            ->selectRaw('lead_id, MIN(created_at) as first_activity_at')
            ->where('user_id', $userId)
            ->groupBy('lead_id');

        $row = DB::table('leads')
            ->joinSub($firstActivityPerLead, 'fa', function ($join) {
                $join->on('fa.lead_id', '=', 'leads.id');
            })
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

