<?php

namespace App\Http\Controllers\ReportViewer;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');
        if (!in_array($period, ['today', 'week', 'month'], true)) {
            $period = 'month';
        }

        $now     = now();
        $startAt = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week'  => $now->copy()->startOfWeek(),
            default => $now->copy()->startOfMonth(),
        };

        // ── People counts ──────────────────────────────────────────────────
        $totalManagers    = User::where('role', 'manager')->where('status', 1)->count();
        $totalTelecallers = User::where('role', 'telecaller')->where('status', 1)->count();

        // ── Org-wide lead counts (no manager scoping) ──────────────────────
        $leadsToday = Lead::whereDate('created_at', $now->toDateString())->count();
        $leadsWeek  = Lead::where('created_at', '>=', $now->copy()->startOfWeek())->count();
        $leadsMonth = Lead::where('created_at', '>=', $now->copy()->startOfMonth())->count();

        // ── All-time lead assignment breakdown ─────────────────────────────
        $totalLeadsAll   = Lead::count();
        $assignedLeads   = Lead::whereNotNull('assigned_to')->count();
        $unassignedLeads = Lead::whereNull('assigned_to')->count();
        $convertedLeads  = Lead::where('status', 'converted')->count();
        $contactedLeads  = Lead::whereIn('id', CallLog::select('lead_id')->distinct())->count();

        // ── Call stats ─────────────────────────────────────────────────────
        $callsQ               = CallLog::where('created_at', '>=', $startAt);
        $totalCallsMade       = (clone $callsQ)->count();
        $totalCallDurationSec = (int) (clone $callsQ)->sum('duration');
        $answeredCalls        = (clone $callsQ)->whereNotNull('answered_at')->count();
        $avgCallDurationSec   = (int) (clone $callsQ)->avg('duration');

        $callStatusBreakdown = (clone $callsQ)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => ['status' => $r->status ?: 'Unknown', 'total' => (int) $r->total])
            ->values();

        $callOutcomes = (clone $callsQ)
            ->whereNotNull('outcome')
            ->where('outcome', '!=', '')
            ->select('outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('outcome')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => ['outcome' => $r->outcome, 'total' => (int) $r->total])
            ->values();

        // ── Conversion rate ────────────────────────────────────────────────
        $periodTotal     = Lead::where('created_at', '>=', $startAt)->count();
        $periodConverted = Lead::where('created_at', '>=', $startAt)->where('status', 'converted')->count();
        $conversionRate  = $periodTotal > 0 ? round(($periodConverted / $periodTotal) * 100, 2) : 0.0;

        // ── Lead source breakdown ──────────────────────────────────────────
        $leadSource = Lead::select('source', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $startAt)
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => ['source' => $r->source ?: 'Unknown', 'total' => (int) $r->total])
            ->values();

        // ── Missed follow-ups ──────────────────────────────────────────────
        $missedFollowups = Followup::whereDate('next_followup', '<', $now->toDateString())->count();

        // ── Manager performance summary ────────────────────────────────────
        $managerStats = User::where('role', 'manager')
            ->where('status', 1)
            ->get(['id', 'name'])
            ->map(function ($manager) use ($startAt) {
                $leadQ          = Lead::where('assigned_by', $manager->id)->where('created_at', '>=', $startAt);
                $total          = (clone $leadQ)->count();
                $assignedToTc   = (clone $leadQ)->whereNotNull('assigned_to')->count();
                $unassigned     = $total - $assignedToTc;
                $converted      = (clone $leadQ)->where('status', 'converted')->count();
                $rate           = $total > 0 ? round(($converted / $total) * 100, 1) : 0.0;

                return [
                    'id'              => $manager->id,
                    'name'            => $manager->name,
                    'total_leads'     => $total,
                    'assigned_leads'  => $assignedToTc,
                    'unassigned_leads'=> $unassigned,
                    'converted_leads' => $converted,
                    'conversion_rate' => $rate,
                ];
            })
            ->sortByDesc('total_leads')
            ->values();

        // ── Telecaller performance summary ─────────────────────────────────
        $telecallerStats = User::where('role', 'telecaller')
            ->where('status', 1)
            ->get(['id', 'name'])
            ->map(function ($tc) use ($startAt) {
                $assigned    = Lead::where('assigned_to', $tc->id)->where('created_at', '>=', $startAt)->count();
                $converted   = Lead::where('assigned_to', $tc->id)->where('created_at', '>=', $startAt)->where('status', 'converted')->count();
                $calls       = CallLog::where('user_id', $tc->id)->where('created_at', '>=', $startAt)->count();
                $talkTimeSec = (int) CallLog::where('user_id', $tc->id)->where('created_at', '>=', $startAt)->sum('duration');
                $rate        = $assigned > 0 ? round(($converted / $assigned) * 100, 1) : 0.0;

                return [
                    'id'              => $tc->id,
                    'name'            => $tc->name,
                    'assigned_count'  => $assigned,
                    'converted_count' => $converted,
                    'total_calls'     => $calls,
                    'talk_time_sec'   => $talkTimeSec,
                    'conversion_rate' => $rate,
                ];
            })
            ->sortByDesc('total_calls')
            ->take(10)
            ->values();

        // ── Course performance ─────────────────────────────────────────────
        $courseStats = Lead::join('courses', 'leads.course_id', '=', 'courses.id')
            ->where('leads.created_at', '>=', $startAt)
            ->selectRaw("courses.name as course, COUNT(*) as total, SUM(leads.status = 'converted') as conversions")
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'course'      => $row->course,
                'total'       => (int) $row->total,
                'conversions' => (int) $row->conversions,
                'rate'        => $row->total > 0 ? round($row->conversions / $row->total * 100, 1) : 0,
            ])
            ->values();

        // ── Status breakdown ───────────────────────────────────────────────
        $statusBreakdown = Lead::where('created_at', '>=', $startAt)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => ['status' => $r->status, 'total' => (int) $r->total])
            ->values();

        return Inertia::render('ReportViewer/Dashboard', [
            'period'               => $period,
            // People
            'totalManagers'        => $totalManagers,
            'totalTelecallers'     => $totalTelecallers,
            // Lead period counts
            'leadsToday'           => $leadsToday,
            'leadsWeek'            => $leadsWeek,
            'leadsMonth'           => $leadsMonth,
            // Lead all-time breakdown
            'totalLeadsAll'        => $totalLeadsAll,
            'assignedLeads'        => $assignedLeads,
            'unassignedLeads'      => $unassignedLeads,
            'contactedLeads'       => $contactedLeads,
            'convertedLeads'       => $convertedLeads,
            // Call stats
            'totalCallsMade'       => $totalCallsMade,
            'totalCallDurationSec' => $totalCallDurationSec,
            'answeredCalls'        => $answeredCalls,
            'avgCallDurationSec'   => $avgCallDurationSec,
            'callStatusBreakdown'  => $callStatusBreakdown,
            'callOutcomes'         => $callOutcomes,
            // Other
            'conversionRate'       => $conversionRate,
            'missedFollowups'      => $missedFollowups,
            'leadSource'           => $leadSource,
            'managerStats'         => $managerStats,
            'telecallerStats'      => $telecallerStats,
            'courseStats'          => $courseStats,
            'statusBreakdown'      => $statusBreakdown,
            'reportsUrl' => [
                'telecallerPerformance' => route('report_viewer.reports.telecaller-performance'),
                'managerPerformance'    => route('report_viewer.reports.manager-performance'),
                'conversion'            => route('report_viewer.reports.conversion'),
                'leadSource'            => route('report_viewer.reports.lead-source'),
                'period'                => route('report_viewer.reports.period'),
                'callEfficiency'        => route('report_viewer.reports.call-efficiency'),
                'responseTime'          => route('report_viewer.reports.response-time'),
            ],
        ]);
    }
}
