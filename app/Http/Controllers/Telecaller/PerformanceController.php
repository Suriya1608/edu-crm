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

    public function custom(Request $request)
    {
        $from = $request->input('date_from');
        $to   = $request->input('date_to');

        if (!$from || !$to) {
            return redirect()->route('telecaller.performance.daily');
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end   = Carbon::parse($to)->endOfDay();
        } catch (\Exception) {
            return redirect()->route('telecaller.performance.daily');
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > 365) {
            $end = $start->copy()->addDays(365)->endOfDay();
        }

        return $this->renderPerformance(
            $start->format('d M') . ' – ' . $end->format('d M Y'),
            'custom',
            $start,
            $end
        );
    }

    public function export(Request $request, string $scope)
    {
        $format = $request->input('format', 'excel');

        if ($scope === 'custom') {
            $from = $request->input('date_from');
            $to   = $request->input('date_to');
            try {
                $start = Carbon::parse($from)->startOfDay();
                $end   = Carbon::parse($to)->endOfDay();
            } catch (\Exception) {
                $start = now()->startOfDay();
                $end   = now()->endOfDay();
            }
            $title = 'Custom Range Performance';
        } else {
            [$start, $end, $title] = match ($scope) {
                'weekly'  => [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY), 'Weekly Performance'],
                'monthly' => [now()->startOfMonth(), now()->endOfMonth(), 'Monthly Summary'],
                default   => [now()->startOfDay(), now()->endOfDay(), 'Daily Performance'],
            };
        }

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

        // ── Extra analytics for export ─────────────────────────────────────
        $courseWiseExport = DB::table('leads')
            ->join('courses', 'courses.id', '=', 'leads.course_id')
            ->where('leads.assigned_to', $userId)
            ->whereBetween('leads.created_at', [$start, $end])
            ->selectRaw('courses.name as course_name, COUNT(*) as enquiries, SUM(leads.status = "converted") as conversions')
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('enquiries')
            ->get()
            ->map(fn($r) => ['course' => $r->course_name, 'enquiries' => (int) $r->enquiries, 'conversions' => (int) $r->conversions])
            ->toArray();

        $finalCourseExport = DB::table('leads')
            ->join('courses', 'courses.id', '=', 'leads.final_course_id')
            ->where('leads.assigned_to', $userId)
            ->where('leads.status', 'converted')
            ->whereBetween('leads.created_at', [$start, $end])
            ->whereNotNull('leads.final_course_id')
            ->selectRaw('courses.name as course_name, COUNT(*) as cnt')
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn($r) => ['course' => $r->course_name, 'count' => (int) $r->cnt])
            ->toArray();

        $genderExport = Lead::where('assigned_to', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(NULLIF(gender, ""), "not_specified") as grp, COUNT(*) as total, SUM(status = "converted") as conversions')
            ->groupBy(DB::raw('COALESCE(NULLIF(gender, ""), "not_specified")'))
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => ['gender' => ucfirst(str_replace('_', ' ', $r->grp)), 'total' => (int) $r->total, 'conversions' => (int) $r->conversions])
            ->toArray();

        $quotaExport = Lead::where('assigned_to', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('quota')->where('quota', '!=', '')
            ->selectRaw('quota, COUNT(*) as total, SUM(status = "converted") as conversions')
            ->groupBy('quota')->orderByDesc('total')
            ->get()
            ->map(fn($r) => ['quota' => ucfirst($r->quota), 'total' => (int) $r->total, 'conversions' => (int) $r->conversions])
            ->toArray();

        $convertedExport = Lead::where('assigned_to', $userId)
            ->where('status', 'converted')
            ->whereBetween('created_at', [$start, $end])
            ->with(['enrolledCourse:id,name', 'finalCourse:id,name'])
            ->select('id', 'name', 'lead_code', 'phone', 'gender', 'quota', 'course_id', 'final_course_id', 'created_at')
            ->latest()->limit(500)->get()
            ->map(fn($l) => [
                'lead_code'       => $l->lead_code,
                'name'            => $l->name,
                'phone'           => $l->phone,
                'gender'          => ucfirst($l->gender ?? '-'),
                'enquired_course' => $l->enrolledCourse->name ?? '-',
                'final_course'    => $l->finalCourse->name ?? '-',
                'quota'           => ucfirst($l->quota ?? '-'),
                'date'            => $l->created_at->format('d M Y'),
            ])
            ->toArray();

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
            'outcomeBreakdown'    => collect($outcomeBreakdown)->mapWithKeys(fn($cnt, $key) => [$outcomeLabels[$key] ?? $key => $cnt])->toArray(),
            'dailyBreakdown'      => $dailyBreakdown->values()->toArray(),
            'courseWiseBreakdown' => $courseWiseExport,
            'finalCourseBreakdown'=> $finalCourseExport,
            'genderBreakdown'     => $genderExport,
            'quotaBreakdown'      => $quotaExport,
            'convertedLeads'      => $convertedExport,
        ];

        $filename = 'performance-' . $scope . '-' . now()->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.telecaller.performance', $meta)->setPaper('a4', 'portrait');
            return $pdf->download($filename . '.pdf');
        }

        // ── Excel ───────────────────────────────────────────────────────────
        $rows = [];
        foreach ($meta['summary'] as $label => $value) {
            $rows[] = [$label, $value];
        }
        $rows[] = ['', ''];
        $rows[] = ['CALL OUTCOMES', ''];
        $rows[] = ['Outcome', 'Count'];
        foreach ($meta['outcomeBreakdown'] as $label => $cnt) {
            $rows[] = [$label, $cnt];
        }
        $rows[] = ['', ''];
        $rows[] = ['DAILY CALL ACTIVITY', ''];
        $rows[] = ['Date', 'Calls', 'Talk Time', 'Avg/Call'];
        foreach ($meta['dailyBreakdown'] as $row) {
            $rows[] = [$row['day'], $row['calls'], $row['talk_time'], $row['avg']];
        }
        if (!empty($meta['courseWiseBreakdown'])) {
            $rows[] = ['', ''];
            $rows[] = ['ENQUIRED COURSE BREAKDOWN', ''];
            $rows[] = ['Course', 'Enquiries', 'Conversions'];
            foreach ($meta['courseWiseBreakdown'] as $r) {
                $rows[] = [$r['course'], $r['enquiries'], $r['conversions']];
            }
        }
        if (!empty($meta['finalCourseBreakdown'])) {
            $rows[] = ['', ''];
            $rows[] = ['FINAL SELECTED COURSE', ''];
            $rows[] = ['Course', 'Count'];
            foreach ($meta['finalCourseBreakdown'] as $r) {
                $rows[] = [$r['course'], $r['count']];
            }
        }
        if (!empty($meta['genderBreakdown'])) {
            $rows[] = ['', ''];
            $rows[] = ['GENDER ANALYSIS', ''];
            $rows[] = ['Gender', 'Total Leads', 'Conversions'];
            foreach ($meta['genderBreakdown'] as $r) {
                $rows[] = [$r['gender'], $r['total'], $r['conversions']];
            }
        }
        if (!empty($meta['quotaBreakdown'])) {
            $rows[] = ['', ''];
            $rows[] = ['QUOTA BREAKDOWN', ''];
            $rows[] = ['Quota', 'Total Leads', 'Conversions'];
            foreach ($meta['quotaBreakdown'] as $r) {
                $rows[] = [$r['quota'], $r['total'], $r['conversions']];
            }
        }
        if (!empty($meta['convertedLeads'])) {
            $rows[] = ['', ''];
            $rows[] = ['CONVERTED LEADS', ''];
            $rows[] = ['Lead Code', 'Name', 'Phone', 'Gender', 'Enquired Course', 'Final Course', 'Quota', 'Date'];
            foreach ($meta['convertedLeads'] as $l) {
                $rows[] = [$l['lead_code'], $l['name'], $l['phone'], $l['gender'], $l['enquired_course'], $l['final_course'], $l['quota'], $l['date']];
            }
        }

        return Excel::download(new ArrayExport($rows, ['Metric', 'Value'], $title), $filename . '.xlsx');
    }

    public function reportsPage()
    {
        $userId = Auth::id();

        $courseWiseRows = DB::table('leads')
            ->join('courses', 'courses.id', '=', 'leads.course_id')
            ->where('leads.assigned_to', $userId)
            ->selectRaw('courses.id as course_id, courses.name as course_name')
            ->groupBy('courses.id', 'courses.name')
            ->orderBy('course_name')
            ->get()
            ->map(fn($r) => ['course_id' => (int) $r->course_id, 'course' => $r->course_name])
            ->values();

        $finalCourseRows = DB::table('leads')
            ->join('courses', 'courses.id', '=', 'leads.final_course_id')
            ->where('leads.assigned_to', $userId)
            ->where('leads.status', 'converted')
            ->whereNotNull('leads.final_course_id')
            ->selectRaw('courses.id as course_id, courses.name as course_name')
            ->groupBy('courses.id', 'courses.name')
            ->orderBy('course_name')
            ->get()
            ->map(fn($r) => ['course_id' => (int) $r->course_id, 'course' => $r->course_name])
            ->values();

        return Inertia::render('Telecaller/Reports/Index', [
            'courseWiseRows'  => $courseWiseRows,
            'finalCourseRows' => $finalCourseRows,
        ]);
    }

    public function leadReport(Request $request)
    {
        $userId = Auth::id();
        $format = $request->input('format', 'excel');

        $from  = $request->input('date_from');
        $to    = $request->input('date_to');
        $start = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $end   = $to   ? Carbon::parse($to)->endOfDay()     : now()->endOfDay();

        $query = Lead::where('assigned_to', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->with(['enrolledCourse:id,name', 'finalCourse:id,name'])
            ->select('id', 'name', 'lead_code', 'phone', 'gender', 'quota', 'status', 'course_id', 'final_course_id', 'created_at');

        if ($request->filled('gender') && $request->input('gender') !== 'all') {
            $g = $request->input('gender');
            $g === 'not_specified'
                ? $query->where(fn($q) => $q->whereNull('gender')->orWhere('gender', ''))
                : $query->where('gender', $g);
        }

        if ($request->filled('course_id') && $request->input('course_id') !== 'all') {
            $query->where('course_id', (int) $request->input('course_id'));
        }

        if ($request->filled('final_course_id') && $request->input('final_course_id') !== 'all') {
            $query->where('final_course_id', (int) $request->input('final_course_id'));
        }

        if ($request->filled('quota') && $request->input('quota') !== 'all') {
            $q = $request->input('quota');
            $q === 'not_specified'
                ? $query->where(fn($q2) => $q2->whereNull('quota')->orWhere('quota', ''))
                : $query->where('quota', $q);
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $leads    = $query->latest()->limit(1000)->get();
        $period   = $start->format('d M Y') . ' – ' . $end->format('d M Y');
        $filename = 'lead-report-' . now()->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.telecaller.lead-report', [
                'leads'       => $leads,
                'userName'    => Auth::user()->name,
                'period'      => $period,
                'generatedAt' => now()->format('d M Y, h:i A'),
                'filterDesc'  => $this->describeFilters($request),
            ])->setPaper('a4', 'landscape');
            return $pdf->download($filename . '.pdf');
        }

        $headers = ['Lead Code', 'Name', 'Phone', 'Gender', 'Enquired Course', 'Final Course', 'Quota', 'Status', 'Date'];
        $rows = $leads->map(fn($l) => [
            $l->lead_code,
            $l->name,
            $l->phone,
            ucfirst($l->gender ?? '-'),
            $l->enrolledCourse->name ?? '-',
            $l->finalCourse->name    ?? '-',
            ucfirst($l->quota ?? '-'),
            ucfirst(str_replace('_', ' ', $l->status)),
            $l->created_at->format('d M Y'),
        ])->toArray();

        return Excel::download(new ArrayExport($rows, $headers, 'Lead Report'), $filename . '.xlsx');
    }

    private function describeFilters(Request $request): string
    {
        $parts = [];
        if (($v = $request->input('gender'))   && $v !== 'all')
            $parts[] = 'Gender: ' . ucfirst(str_replace('_', ' ', $v));
        if (($v = $request->input('course_id')) && $v !== 'all') {
            $name = DB::table('courses')->where('id', $v)->value('name');
            $parts[] = 'Enquired Course: ' . ($name ?? $v);
        }
        if (($v = $request->input('final_course_id')) && $v !== 'all') {
            $name = DB::table('courses')->where('id', $v)->value('name');
            $parts[] = 'Final Course: ' . ($name ?? $v);
        }
        if (($v = $request->input('quota'))  && $v !== 'all')
            $parts[] = 'Quota: ' . ucfirst($v);
        if (($v = $request->input('status')) && $v !== 'all')
            $parts[] = 'Status: ' . ucfirst(str_replace('_', ' ', $v));
        return empty($parts) ? 'No filters — all leads in period' : implode(' · ', $parts);
    }

    private function prevPeriod(string $scope, Carbon $start, Carbon $end): array
    {
        if ($scope === 'custom') {
            $days = max(1, (int) $start->diffInDays($end));
            return [
                $start->copy()->subDays($days + 1)->startOfDay(),
                $start->copy()->subDay()->endOfDay(),
            ];
        }
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
        [$prevStart, $prevEnd] = $this->prevPeriod($scope, $start, $end);

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

        // ── Course-wise enquiry breakdown ──────────────────────────────────
        $courseWiseRows = DB::table('leads')
            ->join('courses', 'courses.id', '=', 'leads.course_id')
            ->where('leads.assigned_to', $userId)
            ->whereBetween('leads.created_at', [$start, $end])
            ->selectRaw('courses.id as course_id, courses.name as course_name, COUNT(*) as enquiries, SUM(leads.status = "converted") as conversions')
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('enquiries')
            ->get()
            ->map(fn($r) => [
                'course_id'   => (int) $r->course_id,
                'course'      => $r->course_name,
                'enquiries'   => (int) $r->enquiries,
                'conversions' => (int) $r->conversions,
            ])
            ->values();

        // ── Final selected course breakdown (converted leads only) ──────────
        $finalCourseRows = DB::table('leads')
            ->join('courses', 'courses.id', '=', 'leads.final_course_id')
            ->where('leads.assigned_to', $userId)
            ->where('leads.status', 'converted')
            ->whereBetween('leads.created_at', [$start, $end])
            ->whereNotNull('leads.final_course_id')
            ->selectRaw('courses.id as course_id, courses.name as course_name, COUNT(*) as cnt')
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn($r) => ['course_id' => (int) $r->course_id, 'course' => $r->course_name, 'count' => (int) $r->cnt])
            ->values();

        // ── Gender-wise breakdown ───────────────────────────────────────────
        $genderRows = Lead::where('assigned_to', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(NULLIF(gender, ""), "not_specified") as grp, COUNT(*) as total, SUM(status = "converted") as conversions')
            ->groupBy(DB::raw('COALESCE(NULLIF(gender, ""), "not_specified")'))
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'gender'      => $r->grp,
                'total'       => (int) $r->total,
                'conversions' => (int) $r->conversions,
            ])
            ->values();

        // ── Quota breakdown (management vs counselling) ─────────────────────
        $quotaRows = Lead::where('assigned_to', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('quota')
            ->where('quota', '!=', '')
            ->selectRaw('quota, COUNT(*) as total, SUM(status = "converted") as conversions')
            ->groupBy('quota')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'quota'       => $r->quota,
                'total'       => (int) $r->total,
                'conversions' => (int) $r->conversions,
            ])
            ->values();

        // ── Converted lead details ──────────────────────────────────────────
        $convertedLeadsList = Lead::where('assigned_to', $userId)
            ->where('status', 'converted')
            ->whereBetween('created_at', [$start, $end])
            ->with(['enrolledCourse:id,name', 'finalCourse:id,name'])
            ->select('id', 'name', 'lead_code', 'gender', 'quota', 'course_id', 'final_course_id', 'created_at')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($l) => [
                'encrypted_id'    => encrypt($l->id),
                'name'            => $l->name,
                'lead_code'       => $l->lead_code,
                'gender'          => $l->gender,
                'quota'           => $l->quota,
                'enquired_course' => $l->enrolledCourse->name ?? '-',
                'final_course'    => $l->finalCourse->name ?? '-',
                'created_at'      => $l->created_at->format('d M Y'),
            ])
            ->values();

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

            // New analytics
            'courseWiseRows'     => $courseWiseRows,
            'finalCourseRows'    => $finalCourseRows,
            'genderRows'         => $genderRows,
            'quotaRows'          => $quotaRows,
            'convertedLeadsList' => $convertedLeadsList,
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
