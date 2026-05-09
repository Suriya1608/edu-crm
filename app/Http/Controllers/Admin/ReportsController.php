<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

                $callsQ   = CallLog::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
                $calls    = (clone $callsQ)->count();
                $answered = (clone $callsQ)->where('status', 'completed')->count();
                $missed   = (clone $callsQ)->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled', 'missed'])->count();
                $avgDur   = (float) ((clone $callsQ)->avg('duration') ?: 0);
                $totalMins = round((clone $callsQ)->sum('duration') / 60, 1);
                $answerRate = $calls > 0 ? round(($answered / $calls) * 100, 1) : 0;

                $fuQ              = Followup::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
                $followupsTotal   = (clone $fuQ)->count();
                $followupsDone    = (clone $fuQ)->whereNotNull('completed_at')->count();
                $pendingFollowups = (clone $fuQ)->whereDate('next_followup', '<=', now()->toDateString())->whereNull('completed_at')->count();
                $followupRate     = $followupsTotal > 0 ? round(($followupsDone / $followupsTotal) * 100, 1) : 0;

                $convRate  = $assigned > 0 ? round(($converted / $assigned) * 100, 1) : 0;
                $convScore = $convRate;
                $fuScore   = $followupRate;
                $callScore = $calls > 0 ? min(100, round(($answered / max(1, $calls)) * 100)) : 0;
                $effScore  = round(($convScore * 0.40) + ($fuScore * 0.35) + ($callScore * 0.25), 1);

                return [
                    'id'               => $t->id,
                    'name'             => $t->name,
                    'assigned'         => $assigned,
                    'converted'        => $converted,
                    'active'           => $active,
                    'lost'             => $lost,
                    'calls'            => $calls,
                    'answered'         => $answered,
                    'missed'           => $missed,
                    'answer_rate'      => $answerRate,
                    'avg_talk_time'    => sprintf('%02d:%02d', floor($avgDur / 60), (int) $avgDur % 60),
                    'total_talk_mins'  => $totalMins,
                    'followups_total'  => $followupsTotal,
                    'followups_done'   => $followupsDone,
                    'followup_rate'    => $followupRate,
                    'pending_followups'=> $pendingFollowups,
                    'conversion_rate'  => $convRate,
                    'efficiency_score' => $effScore,
                ];
            })->sortByDesc('efficiency_score')->values();

        $summary = [
            'total_telecallers' => $rows->count(),
            'total_calls'       => $rows->sum('calls'),
            'total_converted'   => $rows->sum('converted'),
            'avg_conversion'    => $rows->count() > 0 ? round($rows->avg('conversion_rate'), 1) : 0,
            'total_talk_mins'   => $rows->sum('total_talk_mins'),
        ];

        // Monthly trend — last 6 months
        $monthLabels    = [];
        $monthAssigned  = [];
        $monthConverted = [];
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
        }

        $title        = 'Telecaller Performance';
        $tableHeaders = ['Rank', 'Telecaller', 'Assigned', 'Converted', 'Active', 'Lost', 'Calls', 'Answered', 'Missed', 'Answer %', 'Avg Talk', 'Followup %', 'Pending', 'Conv %', 'Score'];
        $tableRows    = $rows->map(fn($r, $i) => [
            '#' . ($i + 1), $r['name'], $r['assigned'], $r['converted'],
            $r['active'], $r['lost'], $r['calls'], $r['answered'], $r['missed'],
            $r['answer_rate'] . '%', $r['avg_talk_time'],
            $r['followup_rate'] . '%', $r['pending_followups'],
            $r['conversion_rate'] . '%', $r['efficiency_score'],
        ])->all();

        return view('admin.reports.telecaller_performance', compact(
            'title', 'rows', 'filters', 'filterOptions', 'summary',
            'tableHeaders', 'tableRows', 'monthLabels', 'monthAssigned', 'monthConverted'
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

                $leadIds     = (clone $leadQ)->pluck('id');
                $callCount   = CallLog::whereIn('lead_id', $leadIds)->count();
                $avgDuration = (float) (CallLog::whereIn('lead_id', $leadIds)->avg('duration') ?: 0);

                $totalFollowups    = Followup::whereIn('lead_id', $leadIds)->count();
                $completedFollowups = Followup::whereIn('lead_id', $leadIds)->whereNotNull('completed_at')->count();
                $pendingFollowups  = Followup::whereIn('lead_id', $leadIds)
                    ->whereDate('next_followup', '<=', now()->toDateString())
                    ->whereNull('completed_at')->count();
                $followupRate = $totalFollowups > 0 ? round(($completedFollowups / $totalFollowups) * 100, 1) : 0;

                $convRate   = $total > 0 ? round(($converted / $total) * 100, 1) : 0;
                $actScore   = $total > 0 ? min(100, round(($callCount / max(1, $total)) * 25)) : 0;
                $perfScore  = round(($convRate * 0.4) + ($followupRate * 0.35) + ($actScore * 0.25), 1);

                return [
                    'id'               => $manager->id,
                    'name'             => $manager->name,
                    'assigned'         => $total,
                    'converted'        => $converted,
                    'active'           => $active,
                    'lost'             => $lost,
                    'team_size'        => $teamSize,
                    'calls'            => $callCount,
                    'avg_talk_time'    => sprintf('%02d:%02d', floor($avgDuration / 60), (int) $avgDuration % 60),
                    'followup_rate'    => $followupRate,
                    'pending_followups'=> $pendingFollowups,
                    'conversion_rate'  => $convRate,
                    'performance_score'=> $perfScore,
                ];
            })->sortByDesc('performance_score')->values();

        $summary = [
            'total_managers'   => $rows->count(),
            'total_leads'      => $rows->sum('assigned'),
            'total_converted'  => $rows->sum('converted'),
            'avg_conversion'   => $rows->count() > 0 ? round($rows->avg('conversion_rate'), 1) : 0,
            'total_calls'      => $rows->sum('calls'),
        ];

        // Monthly trend — last 6 months
        $monthLabels   = [];
        $monthAssigned = [];
        $monthConverted = [];
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
        }

        $title        = 'Manager Performance';
        $tableHeaders = ['Rank', 'Manager', 'Team', 'Assigned', 'Converted', 'Active', 'Lost', 'Calls', 'Followup %', 'Conversion %', 'Score'];
        $tableRows    = $rows->map(fn($r, $i) => [
            '#' . ($i + 1), $r['name'], $r['team_size'], $r['assigned'],
            $r['converted'], $r['active'], $r['lost'], $r['calls'],
            $r['followup_rate'] . '%', $r['conversion_rate'] . '%', $r['performance_score'],
        ])->all();

        return view('admin.reports.manager_performance', compact(
            'title', 'rows', 'filters', 'filterOptions', 'summary',
            'tableHeaders', 'tableRows', 'monthLabels', 'monthAssigned', 'monthConverted'
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

