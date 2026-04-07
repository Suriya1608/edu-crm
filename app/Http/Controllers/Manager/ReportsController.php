<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'new' => (clone $leadBase)->whereIn('status', ['new', 'assigned'])->count(),
            'contacted' => (clone $leadBase)->where('status', 'contacted')->count(),
            'interested' => (clone $leadBase)->where('status', 'interested')->count(),
            'converted' => (clone $leadBase)->where('status', 'converted')->count(),
        ];

        $sourceRows = (clone $leadBase)->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $telecallerRows = $this->telecallerPerformanceRows($startAt, $endAt, $filters);

        return view('manager.reports.home', compact(
            'filters',
            'filterOptions',
            'totalLeads',
            'contactedLeads',
            'convertedLeads',
            'conversionRate',
            'activeTelecallers',
            'funnel',
            'sourceRows',
            'telecallerRows'
        ));
    }

    public function telecallerPerformance(Request $request)
    {
        $filters = $this->filters($request);
        $filterOptions = $this->filterOptions();
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = $this->telecallerPerformanceRows($startAt, $endAt, $filters);
        return view('manager.reports.telecaller_performance', compact('filters', 'filterOptions', 'rows'));
    }

    public function conversion(Request $request)
    {
        $filters = $this->filters($request);
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

        $statusRows = (clone $base)->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

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
            ])
            ->when($filters['telecaller'] !== 'all', fn($q) => $q->where('id', (int) $filters['telecaller']))
            ->get()
            ->map(function ($u) {
                $rate = $u->total_leads > 0 ? round(($u->converted_leads / $u->total_leads) * 100, 2) : 0;
                return ['name' => $u->name, 'total' => $u->total_leads, 'converted' => $u->converted_leads, 'rate' => $rate];
            });

        return view('manager.reports.conversion', compact('filters', 'filterOptions', 'statusRows', 'teleRows'));
    }

    public function sourcePerformance(Request $request)
    {
        $filters = $this->filters($request);
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

        return view('manager.reports.source_performance', compact('filters', 'filterOptions', 'rows'));
    }

    public function period(Request $request)
    {
        $filters = $this->filters($request);
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

        return view('manager.reports.period', compact('filters', 'filterOptions', 'daily', 'weekly', 'monthly'));
    }

    public function responseTime(Request $request)
    {
        $filters = $this->filters($request);
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
            $first = $firstResponses[$lead->id] ?? null;
            $minutes = null;
            if ($first) {
                $minutes = $lead->created_at->diffInMinutes($first);
            }
            return [
                'lead_code' => $lead->lead_code,
                'lead_name' => $lead->name,
                'telecaller' => $lead->assignedUser?->name ?? 'Unassigned',
                'created_at' => $lead->created_at,
                'first_response_at' => $first,
                'response_minutes' => $minutes,
            ];
        });

        $avgResponse = round($rows->whereNotNull('response_minutes')->avg('response_minutes') ?? 0, 2);

        return view('manager.reports.response_time', compact('filters', 'filterOptions', 'rows', 'avgResponse'));
    }

    public function callEfficiency(Request $request)
    {
        $filters = $this->filters($request);
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

        return view('manager.reports.call_efficiency', compact('filters', 'filterOptions', 'rows'));
    }

    public function export(Request $request, string $report, string $format)
    {
        $validReports = ['telecaller-performance', 'conversion', 'source-performance', 'period', 'response-time', 'call-efficiency'];
        if (!in_array($report, $validReports, true) || !in_array($format, ['excel', 'pdf'], true)) {
            abort(404);
        }

        $data = match ($report) {
            'telecaller-performance' => $this->telecallerPerformanceData($request),
            'conversion' => $this->conversionData($request),
            'source-performance' => $this->sourcePerformanceData($request),
            'period' => $this->periodData($request),
            'response-time' => $this->responseTimeData($request),
            default => $this->callEfficiencyData($request),
        };

        if ($format === 'excel') {
            return $this->csvDownload($report . '.csv', $data['headers'], $data['rows']);
        }

        return view('manager.reports.print', [
            'title' => $data['title'],
            'headers' => $data['headers'],
            'rows' => $data['rows'],
        ]);
    }

    private function telecallerPerformanceData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = $this->telecallerPerformanceRows($startAt, $endAt, $filters)->map(function ($r) {
            return [$r['name'], $r['assigned'], $r['calls'], $r['avg_talk_time'], $r['followups'], $r['converted'], $r['efficiency_score']];
        })->all();

        return [
            'title' => 'Telecaller Performance Report',
            'headers' => ['Telecaller', 'Assigned Leads', 'Calls Made', 'Avg Talk Time', 'Follow-ups', 'Conversions', 'Efficiency Score'],
            'rows' => $rows,
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

        return [
            'title' => 'Conversion Report',
            'headers' => ['Status', 'Count'],
            'rows' => $rows,
        ];
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

        return [
            'title' => 'Source Performance Report',
            'headers' => ['Source', 'Total Leads', 'Converted', 'Conversion Rate'],
            'rows' => $rows,
        ];
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

        return [
            'title' => 'Daily / Weekly / Monthly Report',
            'headers' => ['Date', 'Total Leads', 'Converted'],
            'rows' => $rows,
        ];
    }

    private function responseTimeData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = $this->responseTimeRows($startAt, $endAt, $filters)->map(function ($r) {
            return [$r['lead_code'], $r['lead_name'], $r['telecaller'], $r['created_at'], $r['first_response_at'], $r['response_minutes']];
        })->all();

        return [
            'title' => 'Lead Response Time Report',
            'headers' => ['Lead Code', 'Lead', 'Telecaller', 'Created At', 'First Response', 'Response Minutes'],
            'rows' => $rows,
        ];
    }

    private function callEfficiencyData(Request $request): array
    {
        $filters = $this->filters($request);
        [$startAt, $endAt] = $this->periodRange($filters['date_range']);
        $rows = $this->callEfficiencyRows($startAt, $endAt, $filters)->map(function ($r) {
            return [$r->telecaller_name, $r->total_calls, $r->completed_calls, $r->missed_calls, round($r->avg_duration, 2), $r->completion_rate . '%'];
        })->all();

        return [
            'title' => 'Call Efficiency Report',
            'headers' => ['Telecaller', 'Total Calls', 'Completed', 'Missed', 'Avg Duration', 'Completion Rate'],
            'rows' => $rows,
        ];
    }

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
            $leadQuery = Lead::where('assigned_to', $t->id)->where('assigned_by', $managerId)->whereBetween('created_at', [$startAt, $endAt]);
            if ($filters['source'] !== 'all') {
                $leadQuery->where('source', $filters['source']);
            }
            $assigned = (clone $leadQuery)->count();
            $converted = (clone $leadQuery)->where('status', 'converted')->count();
            $followups = Followup::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt])->count();

            $calls = CallLog::where('user_id', $t->id)->whereBetween('created_at', [$startAt, $endAt]);
            $callCount = (clone $calls)->count();
            $avgDuration = (clone $calls)->avg('duration') ?: 0;

            $efficiency = $assigned > 0 ? round((($converted / $assigned) * 70) + min(30, $callCount), 2) : min(30, $callCount);

            return [
                'name' => $t->name,
                'assigned' => $assigned,
                'calls' => $callCount,
                'avg_talk_time' => $this->formatDuration((int) round($avgDuration)),
                'followups' => $followups,
                'converted' => $converted,
                'efficiency_score' => $efficiency,
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
            ->latest('id')
            ->limit(200)
            ->get();

        // Exclude system-generated activities (user_id IS NULL = capture/assignment events).
        // Only count real telecaller actions: call, note, whatsapp, sms, followup, status_change.
        $firstMap = LeadActivity::whereIn('lead_id', $leads->pluck('id'))
            ->whereNotNull('user_id')
            ->whereNotIn('type', ['assignment'])
            ->select('lead_id', DB::raw('MIN(created_at) as first_response_at'))
            ->groupBy('lead_id')
            ->pluck('first_response_at', 'lead_id');

        return $leads->map(function ($lead) use ($firstMap) {
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

    private function callEfficiencyRows($startAt, $endAt, array $filters): Collection
    {
        $managerId       = Auth::id();
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $query = CallLog::whereBetween('created_at', [$startAt, $endAt])
            ->whereNotNull('user_id')
            ->whereIn('user_id', $myTelecallerIds);
        if ($filters['telecaller'] !== 'all') {
            $query->where('user_id', (int) $filters['telecaller']);
        }

        return $query->select(
            'user_id',
            DB::raw('COUNT(*) as total_calls'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_calls"),
            DB::raw("SUM(CASE WHEN status IN ('no-answer','busy','failed','canceled') THEN 1 ELSE 0 END) as missed_calls"),
            DB::raw('COALESCE(AVG(NULLIF(duration,0)), 0) as avg_duration')
        )
            ->groupBy('user_id')
            ->get()
            ->map(function ($r) {
                $r->telecaller_name = User::find($r->user_id)?->name ?? 'N/A';
                $r->completion_rate = $r->total_calls > 0 ? round(($r->completed_calls / $r->total_calls) * 100, 2) : 0;
                return $r;
            });
    }

    private function filters(Request $request): array
    {
        return [
            'date_range' => $request->get('date_range', '30'),
            'source' => $request->get('source', 'all'),
            'telecaller' => $request->get('telecaller', 'all'),
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
        ];
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

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function formatDuration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60), $seconds % 60);
    }
}
