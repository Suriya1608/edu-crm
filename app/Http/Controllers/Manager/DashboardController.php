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
        $courseStats = Lead::where('assigned_by', $managerId)
            ->whereNotNull('course')
            ->where('course', '!=', '')
            ->selectRaw("course, COUNT(*) as total, SUM(status = 'converted') as conversions")
            ->groupBy('course')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'course'      => $row->course,
                'total'       => (int) $row->total,
                'conversions' => (int) $row->conversions,
                'rate'        => $row->total > 0 ? round($row->conversions / $row->total * 100, 1) : 0,
            ]);

        return view('manager.dashboard.index', [
            'period' => $period,
            'leadsToday' => $leadsToday,
            'leadsWeek' => $leadsWeek,
            'leadsMonth' => $leadsMonth,
            'totalCallsMade' => $totalCallsMade,
            'totalCallDurationSec' => $totalCallDurationSec,
            'whatsAppConversations' => $whatsAppConversations,
            'conversionRate' => $conversionRate,
            'bestPerformingTelecaller' => $bestPerformingTelecaller,
            'missedFollowups' => $missedFollowups,
            'missedFollowupList' => $missedFollowupList,
            'leadSource' => $leadSource,
            'telecallerStats' => $telecallerStats->take(8),
            'telecallerPresence' => $telecallerPresence,
            'missedInboundCalls' => $missedInboundCalls,
            'courseStats' => $courseStats,
        ]);
    }
}
