<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $now = now();
        $startAt = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(),
            default => $now->copy()->startOfMonth(),
        };

        // Scope everything to this manager's assigned leads
        $managerId       = Auth::id();
        $myLeadsSubquery = Lead::where('assigned_by', $managerId)->select('id');
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')
            ->distinct()
            ->pluck('assigned_to');

        $leadsToday  = Lead::whereDate('created_at', $now->toDateString())->where('assigned_by', $managerId)->count();
        $leadsWeek   = Lead::where('created_at', '>=', $now->copy()->startOfWeek())->where('assigned_by', $managerId)->count();
        $leadsMonth  = Lead::where('created_at', '>=', $now->copy()->startOfMonth())->where('assigned_by', $managerId)->count();

        $callsInPeriod       = CallLog::where('created_at', '>=', $startAt)->whereIn('lead_id', $myLeadsSubquery);
        $totalCallsMade      = (clone $callsInPeriod)->count();
        $totalCallDurationSec = (int) (clone $callsInPeriod)->sum('duration');

        $whatsAppConversations = 0;
        if (Schema::hasTable('whatsapp_messages')) {
            $whatsAppConversations = WhatsAppMessage::where('created_at', '>=', $startAt)
                ->whereIn('lead_id', $myLeadsSubquery)
                ->distinct('lead_id')
                ->count('lead_id');
        }

        $periodTotalLeads     = Lead::where('created_at', '>=', $startAt)->where('assigned_by', $managerId)->count();
        $periodConvertedLeads = Lead::where('created_at', '>=', $startAt)->where('assigned_by', $managerId)->where('status', 'converted')->count();
        $conversionRate = $periodTotalLeads > 0
            ? round(($periodConvertedLeads / $periodTotalLeads) * 100, 2)
            : 0.0;

        $telecallers = User::where('role', 'telecaller')
            ->whereIn('id', $myTelecallerIds)
            ->withCount([
                'assignedLeads as assigned_count' => function ($query) use ($startAt, $managerId) {
                    $query->where('created_at', '>=', $startAt)->where('assigned_by', $managerId);
                },
                'assignedLeads as converted_count' => function ($query) use ($startAt, $managerId) {
                    $query->where('created_at', '>=', $startAt)->where('assigned_by', $managerId)->where('status', 'converted');
                },
            ])
            ->get();

        $callsByTelecaller = CallLog::select('user_id', DB::raw('COUNT(*) as total_calls'))
            ->where('created_at', '>=', $startAt)
            ->whereIn('user_id', $myTelecallerIds)
            ->groupBy('user_id')
            ->pluck('total_calls', 'user_id');

        $pendingFuByTelecaller = Followup::select('user_id', DB::raw('COUNT(*) as pending_followups'))
            ->whereIn('lead_id', $myLeadsSubquery)
            ->whereDate('next_followup', '>=', $now->toDateString())
            ->whereDate('next_followup', '<=', $now->copy()->addDays(7)->toDateString())
            ->groupBy('user_id')
            ->pluck('pending_followups', 'user_id');

        $telecallerStats = $telecallers->map(function ($telecaller) use ($callsByTelecaller, $pendingFuByTelecaller) {
            $assigned = (int) $telecaller->assigned_count;
            $converted = (int) $telecaller->converted_count;

            $telecaller->total_calls       = (int) ($callsByTelecaller[$telecaller->id] ?? 0);
            $telecaller->pending_followups = (int) ($pendingFuByTelecaller[$telecaller->id] ?? 0);
            $telecaller->conversion_rate   = $assigned > 0
                ? round(($converted / $assigned) * 100, 2)
                : 0.0;

            return $telecaller;
        })->sortByDesc(function ($telecaller) {
            return [$telecaller->converted_count, $telecaller->conversion_rate, $telecaller->total_calls];
        })->values();

        $bestPerformingTelecaller = $telecallerStats->first();

        $missedFollowupsQuery = Followup::with([
            'lead:id,name,phone,assigned_to',
            'lead.assignedUser:id,name',
        ])->whereIn('lead_id', $myLeadsSubquery)
          ->whereDate('next_followup', '<', $now->toDateString());

        $missedFollowups    = (clone $missedFollowupsQuery)->count();
        $missedFollowupList = (clone $missedFollowupsQuery)->orderBy('next_followup')->limit(6)->get();

        $leadSource = Lead::select('source', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $startAt)
            ->where('assigned_by', $managerId)
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
            User::where('role', 'telecaller')
                ->where('is_online', true)
                ->where('last_seen_at', '<', now()->subSeconds(90))
                ->update(['is_online' => false]);
        }

        $telecallerPresence = User::where('role', 'telecaller')
            ->whereIn('id', $myTelecallerIds)
            ->orderBy('name')
            ->get(['id', 'name', 'is_online'])
            ->map(function ($telecaller) {
                return [
                    'id'        => $telecaller->id,
                    'name'      => $telecaller->name,
                    'is_online' => (bool) ($telecaller->is_online ?? false),
                ];
            });

        $missedInboundCalls = CallLog::with(['lead:id,lead_code,name,phone'])
            ->whereIn('lead_id', $myLeadsSubquery)
            ->where('direction', 'inbound')
            ->where('status', 'missed')
            ->latest('id')
            ->limit(8)
            ->get();

        $managerId = Auth::id();
        $courseStats = Lead::join('courses', 'leads.course_id', '=', 'courses.id')
            ->where('leads.assigned_by', $managerId)
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
            ]);

        $calQuery = Followup::whereIn('lead_id', Lead::where('assigned_by', $managerId)->select('id'))
            ->whereYear('next_followup', $now->year)
            ->whereMonth('next_followup', $now->month);
        if (Schema::hasColumn('followups', 'completed_at')) {
            $calQuery->whereNull('completed_at');
        }
        $followupCalendar = $calQuery
            ->selectRaw('DATE(next_followup) as day, COUNT(*) as total')
            ->groupByRaw('DATE(next_followup)')
            ->pluck('total', 'day');

        return Inertia::render('Manager/Dashboard', [
            'period'                   => $period,
            'leadsToday'               => $leadsToday,
            'leadsWeek'                => $leadsWeek,
            'leadsMonth'               => $leadsMonth,
            'totalCallsMade'           => $totalCallsMade,
            'totalCallDurationSec'     => $totalCallDurationSec,
            'whatsAppConversations'    => $whatsAppConversations,
            'conversionRate'           => $conversionRate,
            'bestPerformingTelecaller' => $bestPerformingTelecaller
                ? ['name' => $bestPerformingTelecaller->name, 'conversion_rate' => $bestPerformingTelecaller->conversion_rate]
                : null,
            'missedFollowups'          => $missedFollowups,
            'missedFollowupList'       => $missedFollowupList->map(fn($f) => [
                'id'           => $f->id,
                'lead_id'      => $f->lead_id,
                'next_followup'=> $f->next_followup,
                'lead'         => $f->lead ? [
                    'name'          => $f->lead->name,
                    'assigned_user' => $f->lead->assignedUser ? ['name' => $f->lead->assignedUser->name] : null,
                ] : null,
            ])->values(),
            'leadSource'               => $leadSource->map(fn($r) => [
                'source' => $r->source ?: 'Unknown',
                'total'  => (int) $r->total,
            ])->values(),
            'telecallerStats'          => $telecallerStats->take(8)->map(fn($t) => [
                'id'               => $t->id,
                'name'             => $t->name,
                'assigned_count'   => $t->assigned_count,
                'total_calls'      => $t->total_calls,
                'pending_followups'=> $t->pending_followups,
                'conversion_rate'  => $t->conversion_rate,
            ])->values(),
            'telecallerPresence'       => $telecallerPresence->values(),
            'missedInboundCalls'       => $missedInboundCalls->map(fn($c) => [
                'id'                  => $c->id,
                'created_at_formatted'=> $c->created_at?->format('d M, h:i A'),
                'lead_id'             => $c->lead_id,
                'lead_code'           => $c->lead?->lead_code,
                'lead_name'           => $c->lead?->name,
                'lead_phone'          => $c->lead?->phone,
                'customer_number'     => $c->customer_number ?? null,
                'encrypted_lead_id'   => $c->lead_id ? encrypt($c->lead_id) : null,
            ])->values(),
            'courseStats'              => $courseStats->values(),
            'followupCalendar'         => $followupCalendar,
            // URLs
            'presenceSnapshotUrl'      => route('manager.telecaller-status.snapshot'),
            'calendarDataUrl'          => route('manager.followups.calendar-data'),
            'telecallersUrl'           => route('manager.telecallers'),
            'leadsCreateUrl'           => route('manager.leads.create'),
        ]);
    }
}
