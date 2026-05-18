<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Exports\TelecallerLeadActivityExport;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadMeeting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    public function telecallerPerformance(Request $request)
    {
        $filters = [
            'date_range' => $request->get('date_range', '30'),
            'source'     => $request->get('source', 'all'),
            'telecaller' => $request->get('telecaller', 'all'),
        ];
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $filterOptions = [
            'sources'     => Lead::select('source')->distinct()->orderBy('source')->pluck('source'),
            'telecallers' => User::where('role', 'telecaller')->where('status', 1)->orderBy('name')->get(['id', 'name']),
        ];

        $rows = User::where('role', 'telecaller')
            ->where('status', 1)
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('id', (int) $filters['telecaller']))
            ->get(['id', 'name'])
            ->map(function ($t) use ($startAt, $endAt, $filters) {
                $leadQ = Lead::where('assigned_to', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
                if ($filters['source'] !== 'all') {
                    $leadQ->where('source', $filters['source']);
                }

                $assigned  = (clone $leadQ)->count();
                $converted = (clone $leadQ)->where('status', 'converted')->count();
                $active    = (clone $leadQ)->whereNotIn('status', ['converted', 'lost', 'disqualified'])->count();
                $lost      = (clone $leadQ)->where('status', 'lost')->count();

                $callsQ    = CallLog::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
                $calls     = (clone $callsQ)->count();
                $answered  = (clone $callsQ)->where('status', 'completed')->count();
                $missed    = (clone $callsQ)->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled', 'missed'])->count();
                $avgDur    = (float) ((clone $callsQ)->avg('duration') ?: 0);
                $totalSecs = (int) (clone $callsQ)->sum('duration');
                $totalMins = round($totalSecs / 60, 1);
                $answerRate = $calls > 0 ? round(($answered / $calls) * 100, 1) : 0;

                $fuQ              = Followup::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
                $followupsTotal   = (clone $fuQ)->count();
                $followupsDone    = (clone $fuQ)->whereNotNull('completed_at')->count();
                $pendingFollowups = (clone $fuQ)->whereDate('next_followup', '<=', now()->toDateString())->whereNull('completed_at')->count();
                $followupRate     = $followupsTotal > 0 ? round(($followupsDone / $followupsTotal) * 100, 1) : 0;

                $convRate  = $assigned > 0 ? round(($converted / $assigned) * 100, 1) : 0;
                $callScore = $calls > 0 ? min(100, round(($answered / max(1, $calls)) * 100)) : 0;
                $effScore  = round(($convRate * 0.40) + ($followupRate * 0.35) + ($callScore * 0.25), 1);

                // Avg calls per lead (activity density)
                $callsPerLead = $assigned > 0 ? round($calls / $assigned, 1) : 0;

                // Avg talk time per answered call in seconds
                $avgTalkSecs = $answered > 0
                    ? (float) ((clone $callsQ)->where('status', 'completed')->avg('duration') ?: 0)
                    : 0;

                return [
                    'id'                => $t->id,
                    'name'              => $t->name,
                    'assigned'          => $assigned,
                    'converted'         => $converted,
                    'active'            => $active,
                    'lost'              => $lost,
                    'calls'             => $calls,
                    'answered'          => $answered,
                    'missed'            => $missed,
                    'answer_rate'       => $answerRate,
                    'avg_talk_time'     => sprintf('%02d:%02d', floor($avgDur / 60), (int) $avgDur % 60),
                    'avg_talk_secs'     => round($avgTalkSecs),
                    'total_talk_mins'   => $totalMins,
                    'total_talk_secs'   => $totalSecs,
                    'followups_total'   => $followupsTotal,
                    'followups_done'    => $followupsDone,
                    'followup_rate'     => $followupRate,
                    'pending_followups' => $pendingFollowups,
                    'conversion_rate'   => $convRate,
                    'calls_per_lead'    => $callsPerLead,
                    'efficiency_score'  => $effScore,
                    'grade'             => $effScore >= 70 ? 'A' : ($effScore >= 40 ? 'B' : ($effScore >= 20 ? 'C' : 'D')),
                ];
            })->sortByDesc('efficiency_score')->values();

        $n = $rows->count();
        $summary = [
            'total_telecallers'   => $n,
            'total_calls'         => $rows->sum('calls'),
            'total_converted'     => $rows->sum('converted'),
            'total_talk_mins'     => $rows->sum('total_talk_mins'),
            'total_talk_fmt'      => sprintf('%dh %dm', floor($rows->sum('total_talk_mins') / 60), (int) $rows->sum('total_talk_mins') % 60),
            'avg_answer_rate'     => $n > 0 ? round($rows->avg('answer_rate'), 1) : 0,
            'avg_conversion_rate' => $n > 0 ? round($rows->avg('conversion_rate'), 1) : 0,
            'avg_followup_rate'   => $n > 0 ? round($rows->avg('followup_rate'), 1) : 0,
            'total_pending_fu'    => $rows->sum('pending_followups'),
            'total_assigned'      => $rows->sum('assigned'),
            'total_answered'      => $rows->sum('answered'),
            'total_missed'        => $rows->sum('missed'),
            'top_performer'       => $rows->first()['name'] ?? '—',
            'top_score'           => $rows->first()['efficiency_score'] ?? 0,
        ];

        // Performance distribution for doughnut
        $perfDist = [
            'high'    => $rows->where('efficiency_score', '>=', 70)->count(),
            'average' => $rows->whereBetween('efficiency_score', [40, 69.9])->count(),
            'low'     => $rows->where('efficiency_score', '<', 40)->count(),
        ];

        // Monthly trend — last 6 months
        $monthLabels    = [];
        $monthAssigned  = [];
        $monthConverted = [];
        $monthCalls     = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = now()->subMonths($i)->startOfMonth();
            $mEnd   = now()->subMonths($i)->endOfMonth();
            $monthLabels[] = $mStart->format('M Y');
            $q = Lead::whereHas('assignedUser', fn($q) => $q->where('role', 'telecaller'))
                ->whereBetween('created_at', [$mStart, $mEnd]);
            if ($filters['telecaller'] !== 'all') {
                $q->where('assigned_to', (int) $filters['telecaller']);
            }
            if ($filters['source'] !== 'all') {
                $q->where('source', $filters['source']);
            }
            $monthAssigned[]  = (clone $q)->count();
            $monthConverted[] = (clone $q)->where('status', 'converted')->count();

            $callQ = CallLog::whereBetween('created_at', [$mStart, $mEnd]);
            if ($filters['telecaller'] !== 'all') {
                $callQ->where('user_id', (int) $filters['telecaller']);
            } else {
                $callQ->whereHas('user', fn($q) => $q->where('role', 'telecaller'));
            }
            $monthCalls[] = (clone $callQ)->count();
        }

        $title        = 'Telecaller Performance';
        $tableHeaders = ['Rank', 'Telecaller', 'Grade', 'Assigned', 'Converted', 'Active', 'Lost', 'Calls', 'Answered', 'Missed', 'Answer %', 'Avg Talk', 'Talk Time', 'Calls/Lead', 'Followup %', 'Pending F/U', 'Conv %', 'Score'];
        $tableRows    = $rows->map(fn($r, $i) => [
            '#' . ($i + 1), $r['name'], $r['grade'],
            $r['assigned'], $r['converted'], $r['active'], $r['lost'],
            $r['calls'], $r['answered'], $r['missed'],
            $r['answer_rate'] . '%', $r['avg_talk_time'],
            $r['total_talk_mins'] . ' min', $r['calls_per_lead'],
            $r['followup_rate'] . '%', $r['pending_followups'],
            $r['conversion_rate'] . '%', $r['efficiency_score'],
        ])->all();

        return view('admin.reports.telecaller_performance', compact(
            'title', 'rows', 'filters', 'filterOptions', 'summary', 'perfDist',
            'tableHeaders', 'tableRows', 'monthLabels', 'monthAssigned', 'monthConverted', 'monthCalls'
        ));
    }

    public function managerPerformance(Request $request)
    {
        $filters = [
            'date_range' => $request->get('date_range', '30'),
            'source'     => $request->get('source', 'all'),
            'manager'    => $request->get('manager', 'all'),
        ];
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $filterOptions = [
            'sources'  => Lead::select('source')->distinct()->orderBy('source')->pluck('source'),
            'managers' => User::where('role', 'manager')->where('status', 1)->orderBy('name')->get(['id', 'name']),
        ];

        $rows = User::where('role', 'manager')
            ->when($filters['manager'] !== 'all', fn($q) => $q->where('id', (int) $filters['manager']))
            ->get(['id', 'name'])
            ->map(function ($manager) use ($startAt, $endAt, $filters) {
                $leadQ = Lead::where('assigned_by', $manager->id)->whereBetween('created_at', [$startAt, $endAt]);
                if ($filters['source'] !== 'all') {
                    $leadQ->where('source', $filters['source']);
                }

                $total     = (clone $leadQ)->count();
                $converted = (clone $leadQ)->where('status', 'converted')->count();
                $active    = (clone $leadQ)->whereNotIn('status', ['converted', 'lost', 'disqualified'])->count();
                $lost      = (clone $leadQ)->where('status', 'lost')->count();
                $teamSize  = (clone $leadQ)->whereNotNull('assigned_to')->distinct('assigned_to')->count('assigned_to');

                $leadIds = (clone $leadQ)->pluck('id');

                // Call breakdown
                $callQ    = CallLog::whereIn('lead_id', $leadIds);
                $calls    = (clone $callQ)->count();
                $inbound  = (clone $callQ)->where('direction', 'inbound')->count();
                $outbound = (clone $callQ)->where('direction', 'outbound')->count();
                $answered = (clone $callQ)->where('status', 'completed')->count();
                $missed   = (clone $callQ)->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled', 'missed'])->count();
                $totalSecs   = (int) (clone $callQ)->sum('duration');
                $avgDuration = (float) ((clone $callQ)->avg('duration') ?: 0);
                $answerRate  = $calls > 0 ? round(($answered / $calls) * 100, 1) : 0;
                $totalTalkMins = round($totalSecs / 60, 1);

                // Follow-ups
                $fuQ     = Followup::whereIn('lead_id', $leadIds);
                $fuTotal = (clone $fuQ)->count();
                $fuDone  = (clone $fuQ)->whereNotNull('completed_at')->count();
                $fuPend  = (clone $fuQ)->whereDate('next_followup', '<=', now()->toDateString())->whereNull('completed_at')->count();
                $fuRate  = $fuTotal > 0 ? round(($fuDone / $fuTotal) * 100, 1) : 0;

                // Meetings
                $meetingCount = LeadMeeting::whereIn('lead_id', $leadIds)->count();
                $meetingDone  = LeadMeeting::whereIn('lead_id', $leadIds)->where('status', 'completed')->count();

                // Messages
                $msgCount = WhatsAppMessage::whereIn('lead_id', $leadIds)->count();

                // Per-telecaller breakdown under this manager
                $telecallerIds = (clone $leadQ)->whereNotNull('assigned_to')->distinct('assigned_to')->pluck('assigned_to');
                $telecallerBreakdown = User::whereIn('id', $telecallerIds)->get(['id', 'name'])->map(function ($tc) use ($leadIds, $startAt, $endAt) {
                    $tcLeadQ   = Lead::where('assigned_to', $tc->id)->whereIn('id', $leadIds);
                    $tcLeads   = (clone $tcLeadQ)->count();
                    $tcConv    = (clone $tcLeadQ)->where('status', 'converted')->count();
                    $tcCallQ   = CallLog::where('user_id', $tc->id)->whereIn('lead_id', $leadIds);
                    $tcCalls   = (clone $tcCallQ)->count();
                    $tcAns     = (clone $tcCallQ)->where('status', 'completed')->count();
                    $tcMissed  = (clone $tcCallQ)->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled', 'missed'])->count();
                    $tcFuQ     = Followup::where('user_id', $tc->id)->whereIn('lead_id', $leadIds);
                    $tcFuDone  = (clone $tcFuQ)->whereNotNull('completed_at')->count();
                    $tcFuTotal = (clone $tcFuQ)->count();
                    $tcFuRate  = $tcFuTotal > 0 ? round(($tcFuDone / $tcFuTotal) * 100, 1) : 0;
                    $tcConvRate = $tcLeads > 0 ? round(($tcConv / $tcLeads) * 100, 1) : 0;
                    return [
                        'name'            => $tc->name,
                        'leads'           => $tcLeads,
                        'converted'       => $tcConv,
                        'conversion_rate' => $tcConvRate,
                        'calls'           => $tcCalls,
                        'answered'        => $tcAns,
                        'missed'          => $tcMissed,
                        'followup_rate'   => $tcFuRate,
                    ];
                })->values()->all();

                // Avg response time (minutes from lead created_at to first call)
                $firstCallMap = CallLog::whereIn('lead_id', $leadIds)
                    ->select('lead_id', DB::raw('MIN(created_at) as first_call'))
                    ->groupBy('lead_id')
                    ->pluck('first_call', 'lead_id');
                $responseMins = collect();
                foreach ((clone $leadQ)->get(['id', 'created_at']) as $lead) {
                    if (isset($firstCallMap[$lead->id])) {
                        $diff = $lead->created_at->diffInMinutes($firstCallMap[$lead->id]);
                        if ($diff >= 0 && $diff < 10080) {
                            $responseMins->push($diff);
                        }
                    }
                }
                $avgResponseMins = $responseMins->count() > 0 ? round($responseMins->avg()) : null;

                $convRate  = $total > 0 ? round(($converted / $total) * 100, 1) : 0;
                $callScore = $calls > 0 ? min(100, round(($answered / max(1, $calls)) * 100)) : 0;
                $perfScore = round(($convRate * 0.4) + ($fuRate * 0.35) + ($callScore * 0.25), 1);

                return [
                    'id'                    => $manager->id,
                    'name'                  => $manager->name,
                    'grade'                 => $perfScore >= 70 ? 'A' : ($perfScore >= 40 ? 'B' : ($perfScore >= 20 ? 'C' : 'D')),
                    'assigned'              => $total,
                    'converted'             => $converted,
                    'active'                => $active,
                    'lost'                  => $lost,
                    'team_size'             => $teamSize,
                    'calls'                 => $calls,
                    'calls_inbound'         => $inbound,
                    'calls_outbound'        => $outbound,
                    'calls_answered'        => $answered,
                    'calls_missed'          => $missed,
                    'answer_rate'           => $answerRate,
                    'total_talk_mins'       => $totalTalkMins,
                    'total_talk_fmt'        => sprintf('%dh %dm', floor($totalTalkMins / 60), (int) $totalTalkMins % 60),
                    'avg_talk_time'         => sprintf('%02d:%02d', floor($avgDuration / 60), (int) $avgDuration % 60),
                    'followup_rate'         => $fuRate,
                    'followups_total'       => $fuTotal,
                    'followups_done'        => $fuDone,
                    'pending_followups'     => $fuPend,
                    'meetings'              => $meetingCount,
                    'meetings_done'         => $meetingDone,
                    'messages'              => $msgCount,
                    'avg_response_mins'     => $avgResponseMins,
                    'avg_response_fmt'      => $avgResponseMins !== null ? ($avgResponseMins < 60 ? $avgResponseMins . ' min' : round($avgResponseMins / 60, 1) . ' hr') : '—',
                    'conversion_rate'       => $convRate,
                    'performance_score'     => $perfScore,
                    'telecaller_breakdown'  => $telecallerBreakdown,
                ];
            })->sortByDesc('performance_score')->values();

        $n = $rows->count();
        $summary = [
            'total_managers'     => $n,
            'total_leads'        => $rows->sum('assigned'),
            'total_converted'    => $rows->sum('converted'),
            'total_calls'        => $rows->sum('calls'),
            'total_talk_mins'    => $rows->sum('total_talk_mins'),
            'total_talk_fmt'     => sprintf('%dh %dm', floor($rows->sum('total_talk_mins') / 60), (int) $rows->sum('total_talk_mins') % 60),
            'total_meetings'     => $rows->sum('meetings'),
            'total_messages'     => $rows->sum('messages'),
            'total_pending_fu'   => $rows->sum('pending_followups'),
            'avg_conversion'     => $n > 0 ? round($rows->avg('conversion_rate'), 1) : 0,
            'avg_followup_rate'  => $n > 0 ? round($rows->avg('followup_rate'), 1) : 0,
            'avg_answer_rate'    => $n > 0 ? round($rows->avg('answer_rate'), 1) : 0,
            'top_manager'        => $rows->first()['name'] ?? '—',
            'top_score'          => $rows->first()['performance_score'] ?? 0,
        ];

        $perfDist = [
            'high'    => $rows->where('performance_score', '>=', 70)->count(),
            'average' => $rows->whereBetween('performance_score', [40, 69.9])->count(),
            'low'     => $rows->where('performance_score', '<', 40)->count(),
        ];

        // Monthly trend — last 6 months
        $monthLabels    = [];
        $monthAssigned  = [];
        $monthConverted = [];
        $monthCalls     = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = now()->subMonths($i)->startOfMonth();
            $mEnd   = now()->subMonths($i)->endOfMonth();
            $monthLabels[] = $mStart->format('M Y');
            $q = Lead::whereHas('assignedBy', fn($q) => $q->where('role', 'manager'))
                ->whereBetween('created_at', [$mStart, $mEnd]);
            if ($filters['manager'] !== 'all') {
                $q->where('assigned_by', (int) $filters['manager']);
            }
            if ($filters['source'] !== 'all') {
                $q->where('source', $filters['source']);
            }
            $monthAssigned[]  = (clone $q)->count();
            $monthConverted[] = (clone $q)->where('status', 'converted')->count();
            $mLeadIds = (clone $q)->pluck('id');
            $monthCalls[] = CallLog::whereIn('lead_id', $mLeadIds)->count();
        }

        $title        = 'Manager Performance';
        $tableHeaders = ['Rank', 'Manager', 'Grade', 'Team', 'Assigned', 'Converted', 'Active', 'Lost', 'Calls', 'Inbound', 'Outbound', 'Missed', 'Answer %', 'Talk Time', 'Meetings', 'Messages', 'Followup %', 'Pending F/U', 'Avg Response', 'Conv %', 'Score'];
        $tableRows    = $rows->map(fn($r, $i) => [
            '#' . ($i + 1), $r['name'], $r['grade'], $r['team_size'],
            $r['assigned'], $r['converted'], $r['active'], $r['lost'],
            $r['calls'], $r['calls_inbound'], $r['calls_outbound'], $r['calls_missed'],
            $r['answer_rate'] . '%', $r['total_talk_mins'] . ' min',
            $r['meetings'], $r['messages'],
            $r['followup_rate'] . '%', $r['pending_followups'],
            $r['avg_response_fmt'], $r['conversion_rate'] . '%', $r['performance_score'],
        ])->all();

        return view('admin.reports.manager_performance', compact(
            'title', 'rows', 'filters', 'filterOptions', 'summary', 'perfDist',
            'tableHeaders', 'tableRows', 'monthLabels', 'monthAssigned', 'monthConverted', 'monthCalls'
        ));
    }

    public function conversion(Request $request)
    {
        [$filters, $filterOptions, $startAt, $endAt] = $this->base($request);
        $q = Lead::whereBetween('created_at', [$startAt, $endAt]);
        if ($filters['source'] !== 'all') {
            $q->where('source', $filters['source']);
        }
        if ($filters['telecaller'] !== 'all') {
            $q->where('assigned_to', (int) $filters['telecaller']);
        }
        if ($filters['manager'] !== 'all') {
            $q->where('assigned_by', (int) $filters['manager']);
        }
        $rows = (clone $q)->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->orderByDesc('total')->get();

        return $this->renderReport(
            'Conversion Report',
            'conversion',
            route('admin.reports.conversion'),
            ['Status', 'Count'],
            $rows->map(fn($r) => [$r->status, (int) $r->total])->all(),
            [
                'type' => 'doughnut',
                'labels' => $rows->pluck('status')->values()->all(),
                'datasets' => [['label' => 'Count', 'data' => $rows->pluck('total')->map(fn($v) => (int) $v)->values()->all()]],
            ],
            $filters,
            $filterOptions
        );
    }

    public function sourcePerformance(Request $request)
    {
        [$filters, $filterOptions, $startAt, $endAt] = $this->base($request);
        $rows = Lead::whereBetween('created_at', [$startAt, $endAt])
            ->when($filters['manager'] !== 'all', fn($q) => $q->where('assigned_by', (int) $filters['manager']))
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('assigned_to', (int) $filters['telecaller']))
            ->select('source', DB::raw('COUNT(*) as total_leads'), DB::raw("SUM(CASE WHEN status='converted' THEN 1 ELSE 0 END) as converted_leads"))
            ->groupBy('source')
            ->orderByDesc('total_leads')
            ->get()
            ->map(function ($r) {
                $rate = $r->total_leads > 0 ? round(($r->converted_leads / $r->total_leads) * 100, 2) : 0;
                return [
                    'source' => $r->source,
                    'total' => (int) $r->total_leads,
                    'converted' => (int) $r->converted_leads,
                    'rate' => $rate,
                ];
            });

        return $this->renderReport(
            'Lead Source Report',
            'lead-source',
            route('admin.reports.lead-source'),
            ['Source', 'Total Leads', 'Converted', 'Conversion %'],
            $rows->map(fn($r) => [$r['source'], $r['total'], $r['converted'], $r['rate'] . '%'])->all(),
            [
                'type' => 'bar',
                'labels' => $rows->pluck('source')->values()->all(),
                'datasets' => [['label' => 'Total Leads', 'data' => $rows->pluck('total')->values()->all()]],
            ],
            $filters,
            $filterOptions
        );
    }

    public function period(Request $request)
    {
        [$filters, $filterOptions, $startAt, $endAt] = $this->base($request);
        $rows = Lead::whereBetween('created_at', [$startAt, $endAt])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total, SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('day')->orderBy('day')->get();

        return $this->renderReport(
            'Daily / Weekly / Monthly Report',
            'period',
            route('admin.reports.period'),
            ['Date', 'Total Leads', 'Converted'],
            $rows->map(fn($r) => [$r->day, (int) $r->total, (int) $r->converted])->all(),
            [
                'type' => 'line',
                'labels' => $rows->pluck('day')->values()->all(),
                'datasets' => [
                    ['label' => 'Total', 'data' => $rows->pluck('total')->map(fn($v) => (int) $v)->values()->all()],
                    ['label' => 'Converted', 'data' => $rows->pluck('converted')->map(fn($v) => (int) $v)->values()->all()],
                ],
            ],
            $filters,
            $filterOptions
        );
    }

    public function callEfficiency(Request $request)
    {
        [$filters, $filterOptions, $startAt, $endAt] = $this->base($request);
        $rows = CallLog::whereBetween('created_at', [$startAt, $endAt])
            ->whereNotNull('user_id')
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('user_id', (int) $filters['telecaller']))
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_calls"),
                DB::raw("SUM(CASE WHEN status IN ('no-answer','busy','failed','canceled','missed') THEN 1 ELSE 0 END) as missed_calls"),
                DB::raw('COALESCE(AVG(NULLIF(duration,0)), 0) as avg_duration')
            )
            ->groupBy('user_id')
            ->get()
            ->map(function ($r) {
                $name = User::find($r->user_id)?->name ?? 'N/A';
                $rate = $r->total_calls > 0 ? round(($r->completed_calls / $r->total_calls) * 100, 2) : 0;
                return [
                    'name' => $name,
                    'total' => (int) $r->total_calls,
                    'completed' => (int) $r->completed_calls,
                    'missed' => (int) $r->missed_calls,
                    'avg' => round((float) $r->avg_duration, 2),
                    'rate' => $rate,
                ];
            });

        return $this->renderReport(
            'Call Efficiency Report',
            'call-efficiency',
            route('admin.reports.call-efficiency'),
            ['Telecaller', 'Total Calls', 'Completed', 'Missed', 'Avg Duration (s)', 'Completion %'],
            $rows->map(fn($r) => [$r['name'], $r['total'], $r['completed'], $r['missed'], $r['avg'], $r['rate'] . '%'])->all(),
            [
                'type' => 'bar',
                'labels' => $rows->pluck('name')->values()->all(),
                'datasets' => [['label' => 'Completion %', 'data' => $rows->pluck('rate')->values()->all()]],
            ],
            $filters,
            $filterOptions
        );
    }

    public function responseTime(Request $request)
    {
        [$filters, $filterOptions, $startAt, $endAt] = $this->base($request);
        $rows = $this->responseTimeRows($startAt, $endAt, $filters);

        return $this->renderReport(
            'Lead Response Time Report',
            'response-time',
            route('admin.reports.response-time'),
            ['Lead Code', 'Lead', 'Telecaller', 'Created At', 'First Response', 'Response Minutes'],
            $rows->map(fn($r) => [$r['lead_code'], $r['lead_name'], $r['telecaller'], $r['created_at'], $r['first_response_at'], $r['response_minutes']])->all(),
            [
                'type' => 'line',
                'labels' => $rows->pluck('lead_code')->values()->all(),
                'datasets' => [['label' => 'Response Minutes', 'data' => $rows->pluck('response_minutes')->map(fn($v) => (float) ($v ?? 0))->values()->all()]],
            ],
            $filters,
            $filterOptions
        );
    }

    public function telecallerLeadActivity(Request $request)
    {
        $filters = [
            'date_range' => $request->get('date_range', '30'),
            'source'     => $request->get('source', 'all'),
            'telecaller' => $request->get('telecaller', 'all'),
            'search'     => trim($request->get('search', '')),
        ];
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $filterOptions = [
            'sources'     => Lead::select('source')->distinct()->orderBy('source')->pluck('source'),
            'telecallers' => User::where('role', 'telecaller')->where('status', 1)->orderBy('name')->get(['id', 'name']),
        ];

        $telecallers = collect();

        $tcQuery = User::where('role', 'telecaller')->where('status', 1)->orderBy('name');
        if ($filters['telecaller'] !== 'all') {
            $tcQuery->where('id', (int) $filters['telecaller']);
        }

        foreach ($tcQuery->get(['id', 'name']) as $tc) {
            $leadQ = Lead::where('assigned_to', $tc->id)
                ->whereBetween('created_at', [$startAt, $endAt]);
            if ($filters['source'] !== 'all') {
                $leadQ->where('source', $filters['source']);
            }
            if (!empty($filters['search'])) {
                $s = '%' . $filters['search'] . '%';
                $leadQ->where(function ($q) use ($s) {
                    $q->where('lead_code', 'like', $s)
                      ->orWhere('name', 'like', $s)
                      ->orWhere('email', 'like', $s)
                      ->orWhere('phone', 'like', $s);
                });
            }

            $leads = $leadQ->with(['enrolledCourse', 'finalCourse'])->orderByDesc('created_at')->get();
            $leadIds = $leads->pluck('id');

            // Call logs keyed by lead_id
            $callsByLead = CallLog::whereIn('lead_id', $leadIds)
                ->orderBy('created_at')
                ->get()
                ->groupBy('lead_id');

            // WhatsApp messages keyed by lead_id
            $msgsByLead = WhatsAppMessage::whereIn('lead_id', $leadIds)
                ->orderBy('created_at')
                ->get()
                ->groupBy('lead_id');

            // Meetings keyed by lead_id
            $meetingsByLead = LeadMeeting::whereIn('lead_id', $leadIds)
                ->orderBy('meeting_time')
                ->get()
                ->groupBy('lead_id');

            $leadsData = $leads->map(function ($lead) use ($callsByLead, $msgsByLead, $meetingsByLead) {
                $calls    = $callsByLead->get($lead->id, collect());
                $msgs     = $msgsByLead->get($lead->id, collect());
                $meetings = $meetingsByLead->get($lead->id, collect());

                return [
                    'id'           => $lead->id,
                    'lead_code'    => $lead->lead_code,
                    'name'         => $lead->name,
                    'phone'        => $lead->phone,
                    'status'       => $lead->status,
                    'source'       => $lead->source,
                    'course'       => $lead->enrolledCourse?->name ?? '—',
                    'final_course' => $lead->finalCourse?->name ?? '—',
                    'created_at'   => $lead->created_at?->format('d M Y'),
                    'calls'        => $calls->map(fn($c) => [
                        'date'       => $c->created_at?->format('d M Y H:i'),
                        'direction'  => $c->direction,
                        'status'     => $c->status,
                        'outcome'    => $c->outcome ?? '—',
                        'duration'   => $c->duration ? sprintf('%02d:%02d', floor($c->duration / 60), $c->duration % 60) : '—',
                    ])->values()->all(),
                    'messages'     => $msgs->map(fn($m) => [
                        'date'      => ($m->sent_at ?? $m->created_at)?->format('d M Y H:i'),
                        'direction' => $m->direction,
                        'body'      => $m->message_body ?? $m->message ?? '',
                        'type'      => $m->media_type ?? 'text',
                    ])->values()->all(),
                    'meetings'     => $meetings->map(fn($mt) => [
                        'title'  => $mt->title,
                        'time'   => $mt->meeting_time?->format('d M Y H:i'),
                        'type'   => $mt->meeting_type ?? '—',
                        'status' => $mt->status,
                        'notes'  => $mt->notes ?? '—',
                    ])->values()->all(),
                    'call_count'    => $calls->count(),
                    'msg_count'     => $msgs->count(),
                    'meeting_count' => $meetings->count(),
                ];
            });

            $telecallers->push([
                'id'    => $tc->id,
                'name'  => $tc->name,
                'leads' => $leadsData,
            ]);
        }

        return view('admin.reports.telecaller_lead_activity', compact(
            'filters', 'filterOptions', 'telecallers', 'startAt', 'endAt'
        ));
    }

    public function exportLeadActivity(Request $request, string $format)
    {
        if (!in_array($format, ['pdf', 'excel'], true)) {
            abort(404);
        }

        $filters = [
            'date_range' => $request->get('date_range', '30'),
            'source'     => $request->get('source', 'all'),
            'telecaller' => $request->get('telecaller', 'all'),
            'search'     => trim($request->get('search', '')),
        ];
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);

        $tcQuery = User::where('role', 'telecaller')->where('status', 1)->orderBy('name');
        if ($filters['telecaller'] !== 'all') {
            $tcQuery->where('id', (int) $filters['telecaller']);
        }

        $telecallers = collect();
        foreach ($tcQuery->get(['id', 'name']) as $tc) {
            $leadQ = Lead::where('assigned_to', $tc->id)
                ->whereBetween('created_at', [$startAt, $endAt]);
            if ($filters['source'] !== 'all') {
                $leadQ->where('source', $filters['source']);
            }
            if (!empty($filters['search'])) {
                $s = '%' . $filters['search'] . '%';
                $leadQ->where(function ($q) use ($s) {
                    $q->where('lead_code', 'like', $s)
                      ->orWhere('name', 'like', $s)
                      ->orWhere('email', 'like', $s)
                      ->orWhere('phone', 'like', $s);
                });
            }

            $leads = $leadQ->with(['enrolledCourse', 'finalCourse'])->orderByDesc('created_at')->get();
            $leadIds = $leads->pluck('id');

            $callsByLead    = CallLog::whereIn('lead_id', $leadIds)->orderBy('created_at')->get()->groupBy('lead_id');
            $msgsByLead     = WhatsAppMessage::whereIn('lead_id', $leadIds)->orderBy('created_at')->get()->groupBy('lead_id');
            $meetingsByLead = LeadMeeting::whereIn('lead_id', $leadIds)->orderBy('meeting_time')->get()->groupBy('lead_id');

            $leadsData = $leads->map(function ($lead) use ($callsByLead, $msgsByLead, $meetingsByLead) {
                $calls    = $callsByLead->get($lead->id, collect());
                $msgs     = $msgsByLead->get($lead->id, collect());
                $meetings = $meetingsByLead->get($lead->id, collect());
                return [
                    'id'           => $lead->id,
                    'lead_code'    => $lead->lead_code,
                    'name'         => $lead->name,
                    'phone'        => $lead->phone,
                    'status'       => $lead->status,
                    'source'       => $lead->source,
                    'course'       => $lead->enrolledCourse?->name ?? '—',
                    'final_course' => $lead->finalCourse?->name ?? '—',
                    'created_at'   => $lead->created_at?->format('d M Y'),
                    'calls'        => $calls->map(fn($c) => [
                        'date'      => $c->created_at?->format('d M Y H:i'),
                        'direction' => $c->direction,
                        'status'    => $c->status,
                        'outcome'   => $c->outcome ?? '—',
                        'duration'  => $c->duration ? sprintf('%02d:%02d', floor($c->duration / 60), $c->duration % 60) : '—',
                    ])->values()->all(),
                    'messages'     => $msgs->map(fn($m) => [
                        'date'      => ($m->sent_at ?? $m->created_at)?->format('d M Y H:i'),
                        'direction' => $m->direction,
                        'body'      => $m->message_body ?? $m->message ?? '',
                        'type'      => $m->media_type ?? 'text',
                    ])->values()->all(),
                    'meetings'     => $meetings->map(fn($mt) => [
                        'title'  => $mt->title,
                        'time'   => $mt->meeting_time?->format('d M Y H:i'),
                        'type'   => $mt->meeting_type ?? '—',
                        'status' => $mt->status,
                        'notes'  => $mt->notes ?? '—',
                    ])->values()->all(),
                    'call_count'    => $calls->count(),
                    'msg_count'     => $msgs->count(),
                    'meeting_count' => $meetings->count(),
                ];
            });

            $telecallers->push(['id' => $tc->id, 'name' => $tc->name, 'leads' => $leadsData]);
        }

        $periodLabel = $this->periodLabel($filters['date_range']);

        if ($format === 'excel') {
            $filename = 'telecaller-lead-activity-' . now()->format('Ymd') . '.xlsx';
            return Excel::download(new TelecallerLeadActivityExport($telecallers, $periodLabel), $filename);
        }

        // PDF
        $pdf = Pdf::loadView('exports.admin.telecaller_lead_activity', [
            'telecallers' => $telecallers,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d M Y H:i'),
            'filters'     => $filters,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('telecaller-lead-activity-' . now()->format('Ymd') . '.pdf');
    }

    private function exportManagerPerformance(Request $request, string $format)
    {
        $filters = [
            'date_range' => $request->get('date_range', '30'),
            'source'     => $request->get('source', 'all'),
            'manager'    => $request->get('manager', 'all'),
        ];
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $periodLabel = $this->periodLabel($filters['date_range']);

        $rows = User::where('role', 'manager')
            ->when($filters['manager'] !== 'all', fn($q) => $q->where('id', (int) $filters['manager']))
            ->get(['id', 'name'])
            ->map(function ($manager) use ($startAt, $endAt, $filters) {
                $leadQ = Lead::where('assigned_by', $manager->id)->whereBetween('created_at', [$startAt, $endAt]);
                if ($filters['source'] !== 'all') $leadQ->where('source', $filters['source']);
                $total     = (clone $leadQ)->count();
                $converted = (clone $leadQ)->where('status', 'converted')->count();
                $active    = (clone $leadQ)->whereNotIn('status', ['converted', 'lost', 'disqualified'])->count();
                $lost      = (clone $leadQ)->where('status', 'lost')->count();
                $teamSize  = (clone $leadQ)->whereNotNull('assigned_to')->distinct('assigned_to')->count('assigned_to');
                $leadIds   = (clone $leadQ)->pluck('id');
                $callQ     = CallLog::whereIn('lead_id', $leadIds);
                $calls     = (clone $callQ)->count();
                $inbound   = (clone $callQ)->where('direction', 'inbound')->count();
                $outbound  = (clone $callQ)->where('direction', 'outbound')->count();
                $answered  = (clone $callQ)->where('status', 'completed')->count();
                $missed    = (clone $callQ)->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled', 'missed'])->count();
                $talkMins  = round((clone $callQ)->sum('duration') / 60, 1);
                $answerRate = $calls > 0 ? round(($answered / $calls) * 100, 1) : 0;
                $fuQ    = Followup::whereIn('lead_id', $leadIds);
                $fuTotal = (clone $fuQ)->count();
                $fuDone  = (clone $fuQ)->whereNotNull('completed_at')->count();
                $fuPend  = (clone $fuQ)->whereDate('next_followup', '<=', now()->toDateString())->whereNull('completed_at')->count();
                $fuRate  = $fuTotal > 0 ? round(($fuDone / $fuTotal) * 100, 1) : 0;
                $meetings = LeadMeeting::whereIn('lead_id', $leadIds)->count();
                $messages = WhatsAppMessage::whereIn('lead_id', $leadIds)->count();
                $convRate  = $total > 0 ? round(($converted / $total) * 100, 1) : 0;
                $callScore = $calls > 0 ? min(100, round(($answered / max(1, $calls)) * 100)) : 0;
                $perfScore = round(($convRate * 0.4) + ($fuRate * 0.35) + ($callScore * 0.25), 1);
                return [
                    'name'            => $manager->name,
                    'grade'           => $perfScore >= 70 ? 'A' : ($perfScore >= 40 ? 'B' : ($perfScore >= 20 ? 'C' : 'D')),
                    'team_size'       => $teamSize,
                    'assigned'        => $total,
                    'converted'       => $converted,
                    'active'          => $active,
                    'lost'            => $lost,
                    'calls'           => $calls,
                    'calls_inbound'   => $inbound,
                    'calls_outbound'  => $outbound,
                    'calls_missed'    => $missed,
                    'answer_rate'     => $answerRate,
                    'total_talk_mins' => $talkMins,
                    'meetings'        => $meetings,
                    'messages'        => $messages,
                    'followup_rate'   => $fuRate,
                    'pending_followups'=> $fuPend,
                    'conversion_rate' => $convRate,
                    'performance_score'=> $perfScore,
                ];
            })->sortByDesc('performance_score')->values();

        $n = $rows->count();
        $summary = [
            'Period'            => $periodLabel,
            'Total Managers'    => $n,
            'Total Leads'       => $rows->sum('assigned'),
            'Total Converted'   => $rows->sum('converted'),
            'Total Calls'       => $rows->sum('calls'),
            'Total Talk Time'   => round($rows->sum('total_talk_mins')) . ' min',
            'Total Meetings'    => $rows->sum('meetings'),
            'Total Messages'    => $rows->sum('messages'),
            'Avg Conv Rate'     => ($n > 0 ? round($rows->avg('conversion_rate'), 1) : 0) . '%',
            'Avg Answer Rate'   => ($n > 0 ? round($rows->avg('answer_rate'), 1) : 0) . '%',
            'Avg Followup Rate' => ($n > 0 ? round($rows->avg('followup_rate'), 1) : 0) . '%',
            'Pending F/U'       => $rows->sum('pending_followups'),
            'Top Manager'       => $rows->first()['name'] ?? '—',
            'Generated'         => now()->format('d M Y H:i'),
        ];

        if ($format === 'excel') {
            $excelRows = $rows->map(fn($r, $i) => [
                '#' . ($i + 1), $r['name'], $r['grade'], $r['team_size'],
                $r['assigned'], $r['converted'], $r['active'], $r['lost'],
                $r['calls'], $r['calls_inbound'], $r['calls_outbound'], $r['calls_missed'],
                $r['answer_rate'] . '%', $r['total_talk_mins'] . ' min',
                $r['meetings'], $r['messages'],
                $r['followup_rate'] . '%', $r['pending_followups'],
                $r['conversion_rate'] . '%', $r['performance_score'],
            ])->all();
            $headings = ['Rank', 'Manager', 'Grade', 'Team', 'Assigned', 'Converted', 'Active', 'Lost', 'Calls', 'Inbound', 'Outbound', 'Missed', 'Answer %', 'Talk Time', 'Meetings', 'Messages', 'Followup %', 'Pending F/U', 'Conv %', 'Score'];
            return Excel::download(new ArrayExport($excelRows, $headings, 'Manager Performance'), 'manager-performance-' . now()->format('Ymd') . '.xlsx');
        }

        $pdf = Pdf::loadView('exports.admin.manager_performance', [
            'rows'        => $rows,
            'summary'     => $summary,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('manager-performance-' . now()->format('Ymd') . '.pdf');
    }

    private function periodLabel(string $dateRange): string
    {
        return match ($dateRange) {
            '7'       => 'Last 7 Days',
            '90'      => 'Last 90 Days',
            'quarter' => 'This Quarter',
            'year'    => 'This Year',
            default   => 'Last 30 Days',
        };
    }

    public function export(Request $request, string $report, string $format)
    {
        $allowed = [
            'telecaller-performance' => 'telecallerPerformance',
            'manager-performance' => 'managerPerformance',
            'conversion' => 'conversion',
            'lead-source' => 'sourcePerformance',
            'period' => 'period',
            'call-efficiency' => 'callEfficiency',
            'response-time' => 'responseTime',
        ];
        if (!isset($allowed[$report]) || !in_array($format, ['excel', 'pdf'], true)) {
            abort(404);
        }

        if ($report === 'telecaller-performance') {
            return $this->exportTelecallerPerformance($request, $format);
        }
        if ($report === 'manager-performance') {
            return $this->exportManagerPerformance($request, $format);
        }

        $viewResponse = $this->{$allowed[$report]}($request);
        $data = $viewResponse->getData();
        $headers = $data['tableHeaders'] ?? [];
        $rows = $data['tableRows'] ?? [];
        $title = $data['title'] ?? 'Report';

        if ($format === 'excel') {
            return $this->csvDownload($report . '.csv', $headers, $rows);
        }

        return view('admin.reports.print', compact('title', 'headers', 'rows'));
    }

    private function exportTelecallerPerformance(Request $request, string $format)
    {
        $filters = [
            'date_range' => $request->get('date_range', '30'),
            'source'     => $request->get('source', 'all'),
            'telecaller' => $request->get('telecaller', 'all'),
        ];
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $periodLabel = $this->periodLabel($filters['date_range']);

        // Re-run the same row logic as telecallerPerformance()
        $rows = User::where('role', 'telecaller')->where('status', 1)
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('id', (int) $filters['telecaller']))
            ->get(['id', 'name'])
            ->map(function ($t) use ($startAt, $endAt, $filters) {
                $leadQ  = Lead::where('assigned_to', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
                if ($filters['source'] !== 'all') $leadQ->where('source', $filters['source']);
                $assigned  = (clone $leadQ)->count();
                $converted = (clone $leadQ)->where('status', 'converted')->count();
                $active    = (clone $leadQ)->whereNotIn('status', ['converted', 'lost', 'disqualified'])->count();
                $lost      = (clone $leadQ)->where('status', 'lost')->count();
                $callsQ    = CallLog::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
                $calls     = (clone $callsQ)->count();
                $answered  = (clone $callsQ)->where('status', 'completed')->count();
                $missed    = (clone $callsQ)->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled', 'missed'])->count();
                $avgDur    = (float) ((clone $callsQ)->avg('duration') ?: 0);
                $totalMins = round((clone $callsQ)->sum('duration') / 60, 1);
                $answerRate = $calls > 0 ? round(($answered / $calls) * 100, 1) : 0;
                $fuQ       = Followup::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
                $fuTotal   = (clone $fuQ)->count();
                $fuDone    = (clone $fuQ)->whereNotNull('completed_at')->count();
                $fuPending = (clone $fuQ)->whereDate('next_followup', '<=', now()->toDateString())->whereNull('completed_at')->count();
                $fuRate    = $fuTotal > 0 ? round(($fuDone / $fuTotal) * 100, 1) : 0;
                $convRate  = $assigned > 0 ? round(($converted / $assigned) * 100, 1) : 0;
                $callScore = $calls > 0 ? min(100, round(($answered / max(1, $calls)) * 100)) : 0;
                $effScore  = round(($convRate * 0.40) + ($fuRate * 0.35) + ($callScore * 0.25), 1);
                return [
                    'name'              => $t->name,
                    'grade'             => $effScore >= 70 ? 'A' : ($effScore >= 40 ? 'B' : ($effScore >= 20 ? 'C' : 'D')),
                    'assigned'          => $assigned,
                    'converted'         => $converted,
                    'active'            => $active,
                    'lost'              => $lost,
                    'calls'             => $calls,
                    'answered'          => $answered,
                    'missed'            => $missed,
                    'answer_rate'       => $answerRate,
                    'avg_talk_time'     => sprintf('%02d:%02d', floor($avgDur / 60), (int) $avgDur % 60),
                    'total_talk_mins'   => $totalMins,
                    'calls_per_lead'    => $assigned > 0 ? round($calls / $assigned, 1) : 0,
                    'followup_rate'     => $fuRate,
                    'pending_followups' => $fuPending,
                    'conversion_rate'   => $convRate,
                    'efficiency_score'  => $effScore,
                ];
            })->sortByDesc('efficiency_score')->values();

        $n = $rows->count();
        $summary = [
            'Period'              => $periodLabel,
            'Total Telecallers'   => $n,
            'Total Calls'         => $rows->sum('calls'),
            'Total Answered'      => $rows->sum('answered'),
            'Total Missed'        => $rows->sum('missed'),
            'Total Converted'     => $rows->sum('converted'),
            'Total Assigned'      => $rows->sum('assigned'),
            'Total Talk Time'     => round($rows->sum('total_talk_mins')) . ' min',
            'Avg Answer Rate'     => ($n > 0 ? round($rows->avg('answer_rate'), 1) : 0) . '%',
            'Avg Conversion Rate' => ($n > 0 ? round($rows->avg('conversion_rate'), 1) : 0) . '%',
            'Avg Followup Rate'   => ($n > 0 ? round($rows->avg('followup_rate'), 1) : 0) . '%',
            'Total Pending F/U'   => $rows->sum('pending_followups'),
            'Top Performer'       => $rows->first()?->offsetGet('name') ?? '—',
            'Generated'           => now()->format('d M Y H:i'),
        ];

        if ($format === 'excel') {
            $excelRows = $rows->map(fn($r, $i) => [
                '#' . ($i + 1), $r['name'], $r['grade'],
                $r['assigned'], $r['converted'], $r['active'], $r['lost'],
                $r['calls'], $r['answered'], $r['missed'],
                $r['answer_rate'] . '%', $r['avg_talk_time'], $r['total_talk_mins'] . ' min',
                $r['calls_per_lead'], $r['followup_rate'] . '%', $r['pending_followups'],
                $r['conversion_rate'] . '%', $r['efficiency_score'],
            ])->all();
            $headings = ['Rank', 'Telecaller', 'Grade', 'Assigned', 'Converted', 'Active', 'Lost', 'Calls', 'Answered', 'Missed', 'Answer %', 'Avg Talk', 'Talk Time', 'Calls/Lead', 'Followup %', 'Pending F/U', 'Conv %', 'Score'];
            return Excel::download(new ArrayExport($excelRows, $headings, 'Telecaller Performance'), 'telecaller-performance-' . now()->format('Ymd') . '.xlsx');
        }

        // PDF
        $pdf = Pdf::loadView('exports.admin.telecaller_performance', [
            'rows'        => $rows,
            'summary'     => $summary,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('telecaller-performance-' . now()->format('Ymd') . '.pdf');
    }

    private function renderReport(
        string $title,
        string $reportKey,
        string $baseRoute,
        array $tableHeaders,
        array $tableRows,
        array $chartConfig,
        array $filters,
        array $filterOptions
    ) {
        return view('admin.reports.report', compact(
            'title',
            'reportKey',
            'baseRoute',
            'tableHeaders',
            'tableRows',
            'chartConfig',
            'filters',
            'filterOptions'
        ));
    }

    private function base(Request $request): array
    {
        $filters = [
            'date_range' => $request->get('date_range', '30'),
            'source' => $request->get('source', 'all'),
            'telecaller' => $request->get('telecaller', 'all'),
            'manager' => $request->get('manager', 'all'),
        ];
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $filterOptions = [
            'sources' => Lead::query()->select('source')->distinct()->orderBy('source')->pluck('source'),
            'telecallers' => User::where('role', 'telecaller')->where('status', 1)->orderBy('name')->get(['id', 'name']),
            'managers' => User::where('role', 'manager')->where('status', 1)->orderBy('name')->get(['id', 'name']),
        ];
        return [$filters, $filterOptions, $startAt, $endAt];
    }

    private function responseTimeRows($startAt, $endAt, array $filters): Collection
    {
        $leadQ = Lead::with('assignedUser')
            ->whereBetween('created_at', [$startAt, $endAt])
            ->when($filters['source'] !== 'all', fn($q) => $q->where('source', $filters['source']))
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('assigned_to', (int) $filters['telecaller']))
            ->when($filters['manager'] !== 'all', fn($q) => $q->where('assigned_by', (int) $filters['manager']))
            ->latest('id')
            ->limit(200)
            ->get();

        // Exclude system-generated activities (user_id IS NULL = capture/assignment events).
        // Only count real telecaller actions: call, note, whatsapp, sms, followup, status_change.
        $firstMap = LeadActivity::whereIn('lead_id', $leadQ->pluck('id'))
            ->whereNotNull('user_id')
            ->whereNotIn('type', ['assignment'])
            ->select('lead_id', DB::raw('MIN(created_at) as first_response_at'))
            ->groupBy('lead_id')
            ->pluck('first_response_at', 'lead_id');

        return $leadQ->map(function ($lead) use ($firstMap) {
            $first = $firstMap[$lead->id] ?? null;
            $minutes = $first ? $lead->created_at->diffInMinutes($first) : null;
            return [
                'lead_code' => $lead->lead_code,
                'lead_name' => $lead->name,
                'telecaller' => $lead->assignedUser?->name ?? 'Unassigned',
                'created_at' => $lead->created_at?->format('Y-m-d H:i:s'),
                'first_response_at' => $first,
                'response_minutes' => $minutes,
            ];
        });
    }

    private function periodRange(string $dateRange): array
    {
        $endAt = now()->endOfDay();
        $startAt = match ($dateRange) {
            '7' => now()->subDays(7)->startOfDay(),
            '90' => now()->subDays(90)->startOfDay(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->subDays(30)->startOfDay(),
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
}

