<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Followup;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function home(Request $request)
    {
        $filters = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $managerId       = Auth::id();
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $leadBase = Lead::query()->whereBetween('created_at', [$startAt, $endAt])->where('assigned_by', $managerId);
        if ($filters['source'] !== 'all') {
            $leadBase->where('source', $filters['source']);
        }
        if ($filters['telecaller'] !== 'all') {
            $leadBase->where('assigned_to', (int) $filters['telecaller']);
        }

        $totalLeads     = (clone $leadBase)->count();
        $contactedLeads = (clone $leadBase)->whereIn('status', ['contacted', 'interested', 'converted', 'follow_up'])->count();
        $convertedLeads = (clone $leadBase)->where('status', 'converted')->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 2) : 0;

        $activeTelecallers = User::where('role', 'telecaller')
            ->whereIn('id', $myTelecallerIds)
            ->where('status', 1)
            ->when(Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at'), function ($q) {
                $q->where('is_online', 1)->where('last_seen_at', '>=', now()->subSeconds(60));
            })
            ->count();

        $funnel = [
            'new'        => (clone $leadBase)->whereIn('status', ['new', 'assigned'])->count(),
            'contacted'  => (clone $leadBase)->where('status', 'contacted')->count(),
            'interested' => (clone $leadBase)->where('status', 'interested')->count(),
            'converted'  => (clone $leadBase)->where('status', 'converted')->count(),
        ];

        $sourceRows = (clone $leadBase)->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $telecallerRows = $this->telecallerPerformanceRows($startAt, $endAt, $filters);

        return Inertia::render('Manager/Reports/Home', [
            'filters'           => $filters,
            'filterOptions'     => $filterOptions,
            'totalLeads'        => $totalLeads,
            'contactedLeads'    => $contactedLeads,
            'convertedLeads'    => $convertedLeads,
            'conversionRate'    => $conversionRate,
            'activeTelecallers' => $activeTelecallers,
            'funnel'            => $funnel,
            'sourceRows'        => $sourceRows,
            'telecallerRows'    => $telecallerRows,
        ]);
    }

    public function telecallerPerformance(Request $request)
    {
        $filters       = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $rows = $this->telecallerPerformanceRows($startAt, $endAt, $filters);

        $aggStats = [
            'total_telecallers' => $rows->count(),
            'total_calls'       => $rows->sum('calls'),
            'total_talk_time'   => $this->formatDuration((int) $rows->sum('total_duration_secs')),
            'total_converted'   => $rows->sum('converted'),
            'total_whatsapp'    => $rows->sum('whatsapp_sent'),
            'total_missed'      => $rows->sum('calls_missed'),
        ];

        return Inertia::render('Manager/Reports/TelecallerPerformance', compact('filters', 'filterOptions', 'rows', 'aggStats'));
    }

    public function conversion(Request $request)
    {
        $filters       = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $managerId       = Auth::id();
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $base = Lead::query()->whereBetween('created_at', [$startAt, $endAt])->where('assigned_by', $managerId);
        if ($filters['source'] !== 'all') {
            $base->where('source', $filters['source']);
        }
        if ($filters['telecaller'] !== 'all') {
            $base->where('assigned_to', (int) $filters['telecaller']);
        }

        $totalLeads     = (clone $base)->count();
        $convertedLeads = (clone $base)->where('status', 'converted')->count();
        $overallRate    = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 2) : 0;

        $statusRows = (clone $base)->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $funnel = [
            'new'        => (clone $base)->whereIn('status', ['new', 'assigned'])->count(),
            'contacted'  => (clone $base)->where('status', 'contacted')->count(),
            'interested' => (clone $base)->where('status', 'interested')->count(),
            'converted'  => $convertedLeads,
        ];

        $sourceRows = (clone $base)
            ->select('source', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted"))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(function ($r) {
                $r->rate = $r->total > 0 ? round(($r->converted / $r->total) * 100, 2) : 0;
                return $r;
            });

        $teleRows = User::where('role', 'telecaller')
            ->whereIn('id', $myTelecallerIds)
            ->withCount([
                'assignedLeads as total_leads' => function ($q) use ($startAt, $endAt, $filters, $managerId) {
                    $q->whereBetween('created_at', [$startAt, $endAt])->where('assigned_by', $managerId);
                    if ($filters['source'] !== 'all') {
                        $q->where('source', $filters['source']);
                    }
                },
                'assignedLeads as converted_leads' => function ($q) use ($startAt, $endAt, $filters, $managerId) {
                    $q->whereBetween('created_at', [$startAt, $endAt])->where('assigned_by', $managerId)->where('status', 'converted');
                    if ($filters['source'] !== 'all') {
                        $q->where('source', $filters['source']);
                    }
                },
                'assignedLeads as interested_leads' => function ($q) use ($startAt, $endAt, $filters, $managerId) {
                    $q->whereBetween('created_at', [$startAt, $endAt])->where('assigned_by', $managerId)->where('status', 'interested');
                    if ($filters['source'] !== 'all') {
                        $q->where('source', $filters['source']);
                    }
                },
            ])
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('id', (int) $filters['telecaller']))
            ->get()
            ->map(function ($u) use ($startAt, $endAt, $managerId, $filters) {
                $rate    = $u->total_leads > 0 ? round(($u->converted_leads / $u->total_leads) * 100, 2) : 0;
                $leadIds = Lead::where('assigned_to', $u->id)->where('assigned_by', $managerId)
                    ->whereBetween('created_at', [$startAt, $endAt])
                    ->when($filters['source'] !== 'all', fn($q) => $q->where('source', $filters['source']))
                    ->pluck('id');
                $attended = $leadIds->isNotEmpty()
                    ? CallLog::whereIn('lead_id', $leadIds)->whereBetween('created_at', [$startAt, $endAt])->distinct('lead_id')->count('lead_id')
                    : 0;
                return [
                    'name'       => $u->name,
                    'total'      => $u->total_leads,
                    'attended'   => $attended,
                    'interested' => $u->interested_leads,
                    'converted'  => $u->converted_leads,
                    'rate'       => $rate,
                ];
            })
            ->sortByDesc('rate')
            ->values();

        // Enquired course breakdown — all leads grouped by the course they enquired about
        $courseMap = Course::pluck('name', 'id');
        $enquiredCourseRows = (clone $base)
            ->select('course_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted"))
            ->groupBy('course_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($r) use ($courseMap) {
                $r->course_name = $courseMap[$r->course_id] ?? 'Unknown';
                $r->rate = $r->total > 0 ? round(($r->converted / $r->total) * 100, 2) : 0;
                return $r;
            });

        // Final selected course breakdown — converted leads grouped by final enrolled course
        $finalCourseRows = (clone $base)
            ->where('status', 'converted')
            ->select('final_course_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN quota = 'management' THEN 1 ELSE 0 END) as management_count"), DB::raw("SUM(CASE WHEN quota = 'counselling' THEN 1 ELSE 0 END) as counselling_count"))
            ->groupBy('final_course_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($r) use ($courseMap) {
                $r->course_name = $courseMap[$r->final_course_id] ?? 'Unknown';
                return $r;
            });

        return Inertia::render('Manager/Reports/Conversion', compact(
            'filters', 'filterOptions', 'statusRows', 'teleRows',
            'totalLeads', 'convertedLeads', 'overallRate', 'funnel', 'sourceRows',
            'enquiredCourseRows', 'finalCourseRows'
        ));
    }

    public function sourcePerformance(Request $request)
    {
        $filters       = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $rows = Lead::query()
            ->whereBetween('created_at', [$startAt, $endAt])
            ->where('assigned_by', Auth::id())
            ->select(
                'source',
                DB::raw('COUNT(*) as total_leads'),
                DB::raw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_leads")
            )
            ->groupBy('source')
            ->orderByDesc('total_leads')
            ->get()
            ->map(function ($r) {
                $r->conversion_rate = $r->total_leads > 0 ? round(($r->converted_leads / $r->total_leads) * 100, 2) : 0;
                return $r;
            });

        return Inertia::render('Manager/Reports/SourcePerformance', compact('filters', 'filterOptions', 'rows'));
    }

    public function period(Request $request)
    {
        $filters       = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $managerId = Auth::id();

        $daily = Lead::query()
            ->whereBetween('created_at', [$startAt, $endAt])->where('assigned_by', $managerId)
            ->selectRaw('DATE(created_at) as period_date, COUNT(*) as total, SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('period_date')
            ->orderBy('period_date')
            ->get();

        $weekly = Lead::query()
            ->whereBetween('created_at', [$startAt, $endAt])->where('assigned_by', $managerId)
            ->selectRaw('YEARWEEK(created_at, 1) as period_week, COUNT(*) as total, SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('period_week')
            ->orderBy('period_week')
            ->get();

        $monthly = Lead::query()
            ->whereBetween('created_at', [$startAt, $endAt])->where('assigned_by', $managerId)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period_month, COUNT(*) as total, SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->get();

        return Inertia::render('Manager/Reports/Period', compact('filters', 'filterOptions', 'daily', 'weekly', 'monthly'));
    }

    public function responseTime(Request $request)
    {
        $filters       = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $leads = Lead::query()
            ->with(['assignedUser'])
            ->whereBetween('created_at', [$startAt, $endAt])
            ->where('assigned_by', Auth::id())
            ->when($filters['source'] !== 'all', fn($q) => $q->where('source', $filters['source']))
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('assigned_to', (int) $filters['telecaller']))
            ->latest('id')
            ->limit(200)
            ->get();

        $leadIds = $leads->pluck('id');
        $firstResponses = LeadActivity::query()
            ->whereIn('lead_id', $leadIds)
            ->whereNotNull('user_id')
            ->whereNotIn('type', ['assignment'])
            ->select('lead_id', DB::raw('MIN(created_at) as first_response_at'))
            ->groupBy('lead_id')
            ->pluck('first_response_at', 'lead_id');

        $rows = $leads->map(function ($lead) use ($firstResponses) {
            $first   = $firstResponses[$lead->id] ?? null;
            $minutes = $first ? $lead->created_at->diffInMinutes($first) : null;
            return [
                'lead_code'         => $lead->lead_code,
                'lead_name'         => $lead->name,
                'telecaller'        => $lead->assignedUser?->name ?? 'Unassigned',
                'created_at'        => $lead->created_at,
                'first_response_at' => $first,
                'response_minutes'  => $minutes,
            ];
        });

        $avgResponse = round($rows->whereNotNull('response_minutes')->avg('response_minutes') ?? 0, 2);

        return Inertia::render('Manager/Reports/ResponseTime', compact('filters', 'filterOptions', 'rows', 'avgResponse'));
    }

    public function callEfficiency(Request $request)
    {
        $filters       = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $managerId       = Auth::id();
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $callBase = CallLog::query()
            ->whereBetween('created_at', [$startAt, $endAt])
            ->whereNotNull('user_id')
            ->whereIn('user_id', $myTelecallerIds);
        if ($filters['telecaller'] !== 'all') {
            $callBase->where('user_id', (int) $filters['telecaller']);
        }

        $rows = (clone $callBase)
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_calls"),
                DB::raw("SUM(CASE WHEN status IN ('no-answer','busy','failed','canceled') THEN 1 ELSE 0 END) as missed_calls"),
                DB::raw('COALESCE(SUM(duration), 0) as total_duration'),
                DB::raw('COALESCE(AVG(NULLIF(duration,0)), 0) as avg_duration')
            )
            ->groupBy('user_id')
            ->get()
            ->map(function ($r) {
                $telecaller = User::find($r->user_id);
                $r->telecaller_name = $telecaller?->name ?? 'N/A';
                $r->completion_rate = $r->total_calls > 0 ? round(($r->completed_calls / $r->total_calls) * 100, 2) : 0;
                return $r;
            });

        return Inertia::render('Manager/Reports/CallEfficiency', compact('filters', 'filterOptions', 'rows'));
    }

    // ─── Individual Telecaller Detail Dashboard ───────────────────────────────

    public function telecallerDetail(Request $request)
    {
        $filters       = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $managerId    = Auth::id();
        $telecallerId = $filters['telecaller'] !== 'all' ? (int) $filters['telecaller'] : null;

        if (!$telecallerId) {
            return redirect()->route('manager.reports.telecaller-performance');
        }

        $telecaller = User::findOrFail($telecallerId);

        // ── Lead base (all time for status breakdown) ──────────────────────
        $leadBase = Lead::where('assigned_to', $telecallerId)->where('assigned_by', $managerId);
        if ($filters['source'] !== 'all') {
            $leadBase->where('source', $filters['source']);
        }
        $allLeadIds = (clone $leadBase)->pluck('id');

        // Period-specific lead metrics
        $periodLeads = (clone $leadBase)->whereBetween('created_at', [$startAt, $endAt]);
        $assigned    = (clone $periodLeads)->count();
        $converted   = (clone $periodLeads)->where('status', 'converted')->count();
        $conversionRate = $assigned > 0 ? round(($converted / $assigned) * 100, 1) : 0;

        // ── Call stats (single aggregation query) ─────────────────────────
        $callBase = CallLog::where('user_id', $telecallerId)->whereBetween('created_at', [$startAt, $endAt]);
        if ($filters['call_type'] !== 'all') {
            $callBase->where('direction', $filters['call_type']);
        }

        $callStats = (clone $callBase)->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN direction = "inbound"  THEN 1 ELSE 0 END) as inbound_cnt,
             SUM(CASE WHEN direction = "outbound" THEN 1 ELSE 0 END) as outbound_cnt,
             SUM(CASE WHEN status IN ("no-answer","busy","failed","canceled") THEN 1 ELSE 0 END) as missed_cnt,
             SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as connected_cnt,
             COALESCE(SUM(duration), 0) as total_secs,
             COALESCE(SUM(CASE WHEN direction = "inbound"  THEN duration ELSE 0 END), 0) as inbound_secs,
             COALESCE(SUM(CASE WHEN direction = "outbound" THEN duration ELSE 0 END), 0) as outbound_secs,
             COALESCE(AVG(NULLIF(duration,0)), 0) as avg_secs'
        )->first();

        $totalCalls     = (int) ($callStats->total        ?? 0);
        $callsInbound   = (int) ($callStats->inbound_cnt  ?? 0);
        $callsOutbound  = (int) ($callStats->outbound_cnt ?? 0);
        $callsMissed    = (int) ($callStats->missed_cnt   ?? 0);
        $callsConnected = (int) ($callStats->connected_cnt ?? 0);
        $totalSecs      = (int) ($callStats->total_secs   ?? 0);
        $inboundSecs    = (int) ($callStats->inbound_secs ?? 0);
        $outboundSecs   = (int) ($callStats->outbound_secs ?? 0);
        $avgSecs        = (float) ($callStats->avg_secs   ?? 0);

        // Leads attended (distinct leads with at least one call in period)
        $leadsAttended = $allLeadIds->isNotEmpty()
            ? (clone $callBase)->whereIn('lead_id', $allLeadIds)->distinct('lead_id')->count('lead_id')
            : 0;

        // ── Campaign stats ─────────────────────────────────────────────────
        $ccQuery = CampaignContact::where('assigned_to', $telecallerId);
        if ($filters['campaign'] !== 'all') {
            $ccQuery->where('campaign_id', (int) $filters['campaign']);
        }
        $campaignCalls     = (clone $ccQuery)->sum('call_count');
        $campaignConverted = (clone $ccQuery)->where('status', 'converted')->count();

        // ── WhatsApp stats ─────────────────────────────────────────────────
        $whatsappSentLeads = $allLeadIds->isNotEmpty()
            ? WhatsAppMessage::whereIn('lead_id', $allLeadIds)->where('direction', 'outbound')->whereBetween('created_at', [$startAt, $endAt])->count()
            : 0;
        $ccIds = (clone $ccQuery)->pluck('id');
        $whatsappSentCampaign = $ccIds->isNotEmpty()
            ? WhatsAppMessage::whereIn('campaign_contact_id', $ccIds)->where('direction', 'outbound')->whereBetween('created_at', [$startAt, $endAt])->count()
            : 0;
        $whatsappReceived = $allLeadIds->isNotEmpty()
            ? WhatsAppMessage::whereIn('lead_id', $allLeadIds)->where('direction', 'inbound')->whereBetween('created_at', [$startAt, $endAt])->count()
            : 0;

        // ── Followups ──────────────────────────────────────────────────────
        $followupsPending = Followup::where('user_id', $telecallerId)->whereNull('completed_at')->count();
        $followupsDone    = Followup::where('user_id', $telecallerId)->whereNotNull('completed_at')->whereBetween('completed_at', [$startAt, $endAt])->count();

        // ── Lead status breakdown ─────────────────────────────────────────
        $leadStatusBreakdown = (clone $leadBase)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->orderByDesc('total')
            ->get();

        // ── Outcome breakdown ─────────────────────────────────────────────
        $outcomeBreakdown = (clone $callBase)
            ->whereNotNull('outcome')
            ->select('outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('outcome')->orderByDesc('total')
            ->get();

        // ── Avg response time (minutes from lead creation → first call) ───
        $firstCallMap = (clone $callBase)
            ->whereIn('lead_id', $allLeadIds)
            ->select('lead_id', DB::raw('MIN(created_at) as first_call_at'))
            ->groupBy('lead_id')
            ->pluck('first_call_at', 'lead_id');
        $avgResponseMins = 0;
        if ($firstCallMap->isNotEmpty()) {
            $leadsForResp = Lead::whereIn('id', $firstCallMap->keys())->get(['id', 'created_at']);
            $diffs        = $leadsForResp->map(function ($l) use ($firstCallMap) {
                $fc = $firstCallMap[$l->id] ?? null;
                return $fc ? $l->created_at->diffInMinutes($fc) : null;
            })->filter()->values();
            $avgResponseMins = $diffs->isNotEmpty() ? (int) round($diffs->avg()) : 0;
        }

        // ── Per-lead call detail (expandable rows) ────────────────────────
        $callLogs = (clone $callBase)
            ->whereIn('lead_id', $allLeadIds)
            ->with('lead:id,lead_code,name,phone,status')
            ->orderBy('lead_id')->orderBy('created_at')
            ->get();

        $leadCallData = $callLogs->groupBy('lead_id')->map(function ($lc) {
            $lead     = $lc->first()->lead;
            $totalDur = (int) $lc->sum('duration');
            return [
                'lead_id'              => $lead?->id,
                'lead_code'            => $lead?->lead_code ?? '-',
                'lead_name'            => $lead?->name ?? 'Unknown',
                'phone'                => $lead?->phone ?? '-',
                'lead_status'          => $lead?->status ?? '-',
                'total_calls'          => $lc->count(),
                'total_duration'       => $totalDur,
                'total_duration_label' => $this->formatDuration($totalDur),
                'answered'             => $lc->where('status', 'completed')->count(),
                'missed'               => $lc->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled'])->count(),
                'last_call_at'         => $lc->max('created_at'),
                'calls'                => $lc->map(fn($c) => [
                    'id'             => $c->id,
                    'called_at'      => $c->created_at?->format('d M Y, H:i'),
                    'duration'       => (int) ($c->duration ?? 0),
                    'duration_label' => $this->formatDuration((int) ($c->duration ?? 0)),
                    'status'         => $c->status,
                    'outcome'        => $c->outcome,
                    'direction'      => $c->direction,
                ])->values(),
            ];
        })->sortByDesc('total_calls')->values();

        $periodLabel = match ($filters['date_range']) {
            '1'       => 'Today',
            'week'    => 'This Week',
            'month'   => 'This Month',
            '7'       => 'Last 7 Days',
            '90'      => 'Last 90 Days',
            'quarter' => 'This Quarter',
            'year'    => 'This Year',
            default   => 'Last 30 Days',
        };

        return Inertia::render('Manager/Reports/TelecallerCallDetail', [
            'filters'             => $filters,
            'filterOptions'       => $filterOptions,
            'telecaller'          => ['id' => $telecaller->id, 'name' => $telecaller->name],
            'periodLabel'         => $periodLabel,
            'metrics'             => [
                'total_calls'        => $totalCalls,
                'calls_inbound'      => $callsInbound,
                'calls_outbound'     => $callsOutbound,
                'calls_missed'       => $callsMissed,
                'calls_connected'    => $callsConnected,
                'total_secs'         => $totalSecs,
                'inbound_secs'       => $inboundSecs,
                'outbound_secs'      => $outboundSecs,
                'total_talk_time'    => $this->formatDuration($totalSecs),
                'avg_duration'       => $this->formatDuration((int) round($avgSecs)),
                'leads_assigned'     => $assigned,
                'leads_attended'     => $leadsAttended,
                'leads_converted'    => $converted,
                'conversion_rate'    => $conversionRate,
                'campaign_calls'     => $campaignCalls,
                'campaign_converted' => $campaignConverted,
                'whatsapp_sent'      => $whatsappSentLeads + $whatsappSentCampaign,
                'whatsapp_received'  => $whatsappReceived,
                'followups_pending'  => $followupsPending,
                'followups_done'     => $followupsDone,
                'avg_response_mins'  => $avgResponseMins,
            ],
            'leadStatusBreakdown' => $leadStatusBreakdown,
            'outcomeBreakdown'    => $outcomeBreakdown,
            'leadCallData'        => $leadCallData,
        ]);
    }

    // ─── Export ───────────────────────────────────────────────────────────────

    public function export(Request $request, string $report, string $format)
    {
        $validReports = ['telecaller-performance', 'conversion', 'source-performance', 'period', 'response-time', 'call-efficiency', 'telecaller-detail'];
        if (!in_array($report, $validReports, true) || !in_array($format, ['excel', 'pdf'], true)) {
            abort(404);
        }

        $data = match ($report) {
            'telecaller-performance' => $this->telecallerPerformanceData($request),
            'conversion'             => $this->conversionData($request),
            'source-performance'     => $this->sourcePerformanceData($request),
            'period'                 => $this->periodData($request),
            'response-time'          => $this->responseTimeData($request),
            'telecaller-detail'      => $this->telecallerDetailData($request),
            default                  => $this->callEfficiencyData($request),
        };

        if ($format === 'excel') {
            return $this->csvDownload($report . '.csv', $data['headers'], $data['rows']);
        }

        return Inertia::render('Manager/Reports/Print', [
            'title'   => $data['title'],
            'headers' => $data['headers'],
            'rows'    => $data['rows'],
        ]);
    }

    // ─── Export data builders ─────────────────────────────────────────────────

    private function telecallerDetailData(Request $request): array
    {
        $filters      = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $managerId    = Auth::id();
        $telecallerId = $filters['telecaller'] !== 'all' ? (int) $filters['telecaller'] : null;

        if (!$telecallerId) {
            return ['title' => 'Detailed Call Report', 'headers' => [], 'rows' => []];
        }

        $telecaller = User::find($telecallerId);
        $myLeadIds  = Lead::where('assigned_to', $telecallerId)
            ->where('assigned_by', $managerId)
            ->when($filters['source'] !== 'all', fn($q) => $q->where('source', $filters['source']))
            ->pluck('id');

        $callLogs = CallLog::where('user_id', $telecallerId)
            ->whereIn('lead_id', $myLeadIds)
            ->whereBetween('created_at', [$startAt, $endAt])
            ->with('lead:id,lead_code,name')
            ->orderBy('lead_id')->orderBy('created_at')
            ->get();

        $rows = $callLogs->map(fn($c) => [
            $c->lead?->lead_code ?? '-',
            $c->lead?->name ?? 'Unknown',
            $c->created_at?->format('Y-m-d H:i:s'),
            $this->formatDuration((int) ($c->duration ?? 0)),
            $c->status ?? '-',
            $c->outcome ?? '-',
            $c->direction ?? '-',
        ])->all();

        return [
            'title'   => 'Detailed Call Report – ' . ($telecaller?->name ?? 'Unknown'),
            'headers' => ['Lead Code', 'Lead Name', 'Called At', 'Duration', 'Status', 'Outcome', 'Direction'],
            'rows'    => $rows,
        ];
    }

    private function telecallerPerformanceData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = $this->telecallerPerformanceRows($startAt, $endAt, $filters)->map(fn($r) => [
            $r['name'],
            $r['assigned'],
            $r['attended'],
            $r['calls'],
            $r['calls_inbound'],
            $r['calls_outbound'],
            $r['calls_missed'],
            $r['calls_connected'],
            $r['total_talk_time'],
            $r['avg_talk_time'],
            $r['whatsapp_sent'],
            $r['campaign_calls'],
            $r['followups'],
            $r['followups_pending'],
            $r['converted'],
            $r['conversion_rate'] . '%',
            $r['efficiency_score'],
        ])->all();

        return [
            'title'   => 'Telecaller Performance Report',
            'headers' => ['Telecaller', 'Assigned Leads', 'Leads Attended', 'Total Calls', 'Inbound', 'Outbound', 'Missed', 'Connected', 'Total Talk Time', 'Avg Talk Time', 'WhatsApp Sent', 'Campaign Calls', 'Follow-ups', 'Followups Pending', 'Converted', 'Conv. Rate', 'Efficiency Score'],
            'rows'    => $rows,
        ];
    }

    private function conversionData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = Lead::whereBetween('created_at', [$startAt, $endAt])
            ->where('assigned_by', Auth::id())
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->get()
            ->map(fn($r) => [$r->status, $r->total])->all();

        return ['title' => 'Conversion Report', 'headers' => ['Status', 'Count'], 'rows' => $rows];
    }

    private function sourcePerformanceData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = Lead::whereBetween('created_at', [$startAt, $endAt])
            ->where('assigned_by', Auth::id())
            ->select('source', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN status='converted' THEN 1 ELSE 0 END) as converted"))
            ->groupBy('source')->get()
            ->map(function ($r) {
                $rate = $r->total > 0 ? round(($r->converted / $r->total) * 100, 2) : 0;
                return [$r->source, $r->total, $r->converted, $rate . '%'];
            })->all();

        return ['title' => 'Source Performance Report', 'headers' => ['Source', 'Total Leads', 'Converted', 'Conversion Rate'], 'rows' => $rows];
    }

    private function periodData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = Lead::whereBetween('created_at', [$startAt, $endAt])
            ->where('assigned_by', Auth::id())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total, SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('day')->orderBy('day')->get()
            ->map(fn($r) => [$r->day, $r->total, $r->converted])->all();

        return ['title' => 'Daily / Weekly / Monthly Report', 'headers' => ['Date', 'Total Leads', 'Converted'], 'rows' => $rows];
    }

    private function responseTimeData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = $this->responseTimeRows($startAt, $endAt, $filters)->map(function ($r) {
            return [$r['lead_code'], $r['lead_name'], $r['telecaller'], $r['created_at'], $r['first_response_at'], $r['response_minutes']];
        })->all();

        return ['title' => 'Lead Response Time Report', 'headers' => ['Lead Code', 'Lead', 'Telecaller', 'Created At', 'First Response', 'Response Minutes'], 'rows' => $rows];
    }

    private function callEfficiencyData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = $this->callEfficiencyRows($startAt, $endAt, $filters)->map(function ($r) {
            return [$r->telecaller_name, $r->total_calls, $r->completed_calls, $r->missed_calls, round($r->avg_duration, 2), $r->completion_rate . '%'];
        })->all();

        return ['title' => 'Call Efficiency Report', 'headers' => ['Telecaller', 'Total Calls', 'Completed', 'Missed', 'Avg Duration', 'Completion Rate'], 'rows' => $rows];
    }

    // ─── Core data helpers ────────────────────────────────────────────────────

    private function telecallerPerformanceRows($startAt, $endAt, array $filters): Collection
    {
        $managerId       = Auth::id();
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $telecallers = User::where('role', 'telecaller')
            ->whereIn('id', $myTelecallerIds)
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('id', (int) $filters['telecaller']))
            ->get(['id', 'name']);

        return $telecallers->map(function ($t) use ($startAt, $endAt, $filters, $managerId) {
            // Lead stats
            $leadQ = Lead::where('assigned_to', $t->id)->where('assigned_by', $managerId)->whereBetween('created_at', [$startAt, $endAt]);
            if ($filters['source'] !== 'all') {
                $leadQ->where('source', $filters['source']);
            }
            $assigned  = (clone $leadQ)->count();
            $converted = (clone $leadQ)->where('status', 'converted')->count();
            $leadIds   = (clone $leadQ)->pluck('id');

            // Call stats (single query)
            $callBase = CallLog::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
            if (($filters['call_type'] ?? 'all') !== 'all') {
                $callBase->where('direction', $filters['call_type']);
            }
            $cs = (clone $callBase)->selectRaw(
                'COUNT(*) as total,
                 SUM(CASE WHEN direction = "inbound"  THEN 1 ELSE 0 END) as inbound_cnt,
                 SUM(CASE WHEN direction = "outbound" THEN 1 ELSE 0 END) as outbound_cnt,
                 SUM(CASE WHEN status IN ("no-answer","busy","failed","canceled") THEN 1 ELSE 0 END) as missed_cnt,
                 SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as connected_cnt,
                 COALESCE(SUM(duration), 0) as total_secs,
                 COALESCE(AVG(NULLIF(duration,0)), 0) as avg_secs'
            )->first();

            $totalCalls    = (int) ($cs->total        ?? 0);
            $callsInbound  = (int) ($cs->inbound_cnt  ?? 0);
            $callsOutbound = (int) ($cs->outbound_cnt ?? 0);
            $callsMissed   = (int) ($cs->missed_cnt   ?? 0);
            $callsConnected= (int) ($cs->connected_cnt ?? 0);
            $totalSecs     = (int) ($cs->total_secs   ?? 0);
            $avgSecs       = (float) ($cs->avg_secs   ?? 0);

            // Campaign contacts assigned to this telecaller
            $ccQ = CampaignContact::where('assigned_to', $t->id);
            if (($filters['campaign'] ?? 'all') !== 'all') {
                $ccQ->where('campaign_id', (int) $filters['campaign']);
            }
            // Campaign calls = sum of call_count on campaign_contacts (the real call tracking field)
            $campaignCalls     = (clone $ccQ)->sum('call_count');
            $campaignConverted = (clone $ccQ)->where('status', 'converted')->count();

            // WhatsApp sent
            $whatsappLeads = $leadIds->isNotEmpty()
                ? WhatsAppMessage::whereIn('lead_id', $leadIds)->where('direction', 'outbound')->whereBetween('created_at', [$startAt, $endAt])->count()
                : 0;
            $ccIds = (clone $ccQ)->pluck('id');
            $whatsappCampaign = $ccIds->isNotEmpty()
                ? WhatsAppMessage::whereIn('campaign_contact_id', $ccIds)->where('direction', 'outbound')->whereBetween('created_at', [$startAt, $endAt])->count()
                : 0;

            // Followups
            $followups        = Followup::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt])->count();
            $followupsPending = Followup::where('user_id', $t->id)->whereNull('completed_at')->count();

            // Leads attended
            $leadsAttended = $leadIds->isNotEmpty()
                ? (clone $callBase)->whereIn('lead_id', $leadIds)->distinct('lead_id')->count('lead_id')
                : 0;

            // Avg response time
            $avgResponseMins = 0;
            if ($leadIds->isNotEmpty()) {
                $fcMap = (clone $callBase)->whereIn('lead_id', $leadIds)
                    ->select('lead_id', DB::raw('MIN(created_at) as fc'))
                    ->groupBy('lead_id')->pluck('fc', 'lead_id');
                if ($fcMap->isNotEmpty()) {
                    $ls   = Lead::whereIn('id', $fcMap->keys())->get(['id', 'created_at']);
                    $diffs = $ls->map(fn($l) => $fcMap[$l->id] ? $l->created_at->diffInMinutes($fcMap[$l->id]) : null)->filter()->values();
                    $avgResponseMins = $diffs->isNotEmpty() ? (int) round($diffs->avg()) : 0;
                }
            }

            $conversionRate = $assigned > 0 ? round(($converted / $assigned) * 100, 1) : 0;
            $efficiency     = $assigned > 0
                ? round((($converted / $assigned) * 70) + min(30, ($totalCalls / max(1, $assigned)) * 10), 1)
                : min(30.0, $totalCalls > 0 ? 10.0 : 0.0);

            return [
                'id'                  => $t->id,
                'name'                => $t->name,
                'assigned'            => $assigned,
                'attended'            => $leadsAttended,
                'converted'           => $converted,
                'conversion_rate'     => $conversionRate,
                'calls'               => $totalCalls,
                'calls_inbound'       => $callsInbound,
                'calls_outbound'      => $callsOutbound,
                'calls_missed'        => $callsMissed,
                'calls_connected'     => $callsConnected,
                'total_duration_secs' => $totalSecs,
                'total_talk_time'     => $this->formatDuration($totalSecs),
                'avg_talk_time'       => $this->formatDuration((int) round($avgSecs)),
                'campaign_calls'      => $campaignCalls,
                'campaign_converted'  => $campaignConverted,
                'whatsapp_sent'       => $whatsappLeads + $whatsappCampaign,
                'followups'           => $followups,
                'followups_pending'   => $followupsPending,
                'avg_response_mins'   => $avgResponseMins,
                'efficiency_score'    => $efficiency,
            ];
        })->sortByDesc('efficiency_score')->values();
    }

    private function responseTimeRows($startAt, $endAt, array $filters): Collection
    {
        $leads = Lead::with('assignedUser')
            ->whereBetween('created_at', [$startAt, $endAt])
            ->where('assigned_by', Auth::id())
            ->when($filters['source'] !== 'all', fn($q) => $q->where('source', $filters['source']))
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('assigned_to', (int) $filters['telecaller']))
            ->latest('id')->limit(200)->get();

        $firstMap = LeadActivity::whereIn('lead_id', $leads->pluck('id'))
            ->whereNotNull('user_id')->whereNotIn('type', ['assignment'])
            ->select('lead_id', DB::raw('MIN(created_at) as first_response_at'))
            ->groupBy('lead_id')->pluck('first_response_at', 'lead_id');

        return $leads->map(function ($lead) use ($firstMap) {
            $first   = $firstMap[$lead->id] ?? null;
            $minutes = $first ? $lead->created_at->diffInMinutes($first) : null;
            return [
                'lead_code'         => $lead->lead_code,
                'lead_name'         => $lead->name,
                'telecaller'        => $lead->assignedUser?->name ?? 'Unassigned',
                'created_at'        => $lead->created_at?->format('Y-m-d H:i:s'),
                'first_response_at' => $first,
                'response_minutes'  => $minutes,
            ];
        });
    }

    private function callEfficiencyRows($startAt, $endAt, array $filters): Collection
    {
        $managerId       = Auth::id();
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $query = CallLog::whereBetween('created_at', [$startAt, $endAt])
            ->whereNotNull('user_id')->whereIn('user_id', $myTelecallerIds);
        if ($filters['telecaller'] !== 'all') {
            $query->where('user_id', (int) $filters['telecaller']);
        }

        return $query->select(
            'user_id',
            DB::raw('COUNT(*) as total_calls'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_calls"),
            DB::raw("SUM(CASE WHEN status IN ('no-answer','busy','failed','canceled') THEN 1 ELSE 0 END) as missed_calls"),
            DB::raw('COALESCE(AVG(NULLIF(duration,0)), 0) as avg_duration')
        )->groupBy('user_id')->get()->map(function ($r) {
            $r->telecaller_name = User::find($r->user_id)?->name ?? 'N/A';
            $r->completion_rate = $r->total_calls > 0 ? round(($r->completed_calls / $r->total_calls) * 100, 2) : 0;
            return $r;
        });
    }

    // ─── Shared helpers ───────────────────────────────────────────────────────

    private function filters(Request $request): array
    {
        return [
            'date_range' => $request->get('date_range', '30'),
            'source'     => $request->get('source', 'all'),
            'telecaller' => $request->get('telecaller', 'all'),
            'campaign'   => $request->get('campaign', 'all'),
            'call_type'  => $request->get('call_type', 'all'),
        ];
    }

    private function filterOptions(): array
    {
        $managerId       = Auth::id();
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        return [
            'sources'     => Lead::where('assigned_by', $managerId)->select('source')->distinct()->orderBy('source')->pluck('source'),
            'telecallers' => User::where('role', 'telecaller')->whereIn('id', $myTelecallerIds)->where('status', 1)->orderBy('name')->get(['id', 'name']),
            'campaigns'   => Campaign::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function periodRange(string $dateRange): array
    {
        $endAt   = now()->endOfDay();
        $startAt = match ($dateRange) {
            '1'       => now()->startOfDay(),
            '7'       => now()->subDays(7)->startOfDay(),
            '90'      => now()->subDays(90)->startOfDay(),
            'week'    => now()->startOfWeek(),
            'month'   => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year'    => now()->startOfYear(),
            default   => now()->subDays(30)->startOfDay(),
        };
        return [$startAt, $endAt];
    }

    private function csvDownload(string $fileName, array $headers, array $rows)
    {
        $callback = function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        };

        return response()->streamDownload($callback, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function formatDuration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60), $seconds % 60);
    }
}
