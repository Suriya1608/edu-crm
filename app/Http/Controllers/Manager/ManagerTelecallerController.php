<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Lead;
use App\Models\CallLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManagerTelecallerController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $managerId       = Auth::id();
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $telecallerQuery = User::where('role', 'telecaller')->whereIn('id', $myTelecallerIds);
        if (Schema::hasColumn('users', 'is_online')) {
            $telecallerQuery->select(['id', 'name', 'phone', 'status', 'is_online', 'last_seen_at']);
        }

        $telecallers = $telecallerQuery
            ->withCount([
                'assignedLeads as total_leads',

                'assignedLeads as converted_count' => function ($q) {
                    $q->where('status', 'converted');
                },

                'assignedLeads as not_interested_count' => function ($q) {
                    $q->where('status', 'not_interested');
                },

                'assignedLeads as followup_count' => function ($q) {
                    $q->where('status', 'follow_up');
                },

                'assignedLeads as upcoming_followup_count' => function ($q) use ($today) {
                    $q->whereHas('followups', function ($f) use ($today) {
                        $f->whereDate('next_followup', '>', $today);
                    });
                },
            ])
            ->get();

        $todayCallMetrics = CallLog::select(
            'user_id',
            DB::raw('COUNT(*) as today_call_count'),
            DB::raw('COALESCE(SUM(duration), 0) as today_talk_time_sec'),
            DB::raw("SUM(CASE WHEN status IN ('ringing','in-progress','answered') THEN 1 ELSE 0 END) as active_calls"),
            DB::raw('MAX(created_at) as last_call_at')
        )
            ->whereDate('created_at', $today)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $totalCallMetrics = CallLog::select(
            'user_id',
            DB::raw('COUNT(*) as total_call_count'),
            DB::raw('COALESCE(SUM(duration), 0) as total_talk_time_sec')
        )
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $onlineCount = 0;
        $onCallCount = 0;
        $idleCount = 0;
        $offlineCount = 0;

        foreach ($telecallers as $tele) {
            $metrics = $todayCallMetrics->get($tele->id);
            $totalMetrics = $totalCallMetrics->get($tele->id);
            $todayCallCount = (int) ($metrics->today_call_count ?? 0);
            $todayTalkTimeSec = (int) ($metrics->today_talk_time_sec ?? 0);
            $activeCalls = (int) ($metrics->active_calls ?? 0);
            $lastCallAt = !empty($metrics?->last_call_at) ? Carbon::parse($metrics->last_call_at) : null;
            $totalCallCount = (int) ($totalMetrics->total_call_count ?? 0);
            $totalTalkTimeSec = (int) ($totalMetrics->total_talk_time_sec ?? 0);

            $missed = Lead::where('assigned_to', $tele->id)
                ->where('assigned_by', $managerId)
                ->where('status', 'follow_up')
                ->whereHas('followups', function ($q) use ($today) {
                    $q->whereDate('next_followup', '<', $today);
                })
                ->whereDoesntHave('activities', function ($q) use ($today) {
                    $q->whereDate('created_at', $today);
                })
                ->count();

            $isAccountActive = (int) $tele->status === 1;
            $isPresenceOnline = false;

            if ($isAccountActive && Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
                $lastSeen = $tele->last_seen_at ? Carbon::parse($tele->last_seen_at) : null;
                $isPresenceOnline = (bool) ($tele->is_online ?? false) && $lastSeen && $lastSeen->gte(now()->subSeconds(60));
            }

            $availability = 'offline';
            $breakState = 'offline';

            if ($isPresenceOnline) {
                if ($activeCalls > 0) {
                    $availability = 'online';
                    $breakState = 'on_call';
                } elseif ($lastCallAt && $lastCallAt->gte(now()->subMinutes(10))) {
                    $availability = 'online';
                    $breakState = 'online';
                } else {
                    $availability = 'online';
                    $breakState = 'idle';
                }
            }

            $conversionRate = $tele->total_leads > 0
                ? round(($tele->converted_count / $tele->total_leads) * 100, 2)
                : 0;

            $performanceScore = min(
                100,
                ($conversionRate * 0.6) +
                    min(25, $todayCallCount * 1.5) +
                    min(15, floor($todayTalkTimeSec / 120))
            );

            $performanceRating = match (true) {
                $performanceScore >= 85 => 'A+',
                $performanceScore >= 70 => 'A',
                $performanceScore >= 55 => 'B',
                $performanceScore >= 40 => 'C',
                default => 'D',
            };

            $tele->missed_followup_count = $missed;
            $tele->today_call_count = $todayCallCount;
            $tele->today_talk_time_sec = $todayTalkTimeSec;
            $tele->total_call_count = $totalCallCount;
            $tele->total_talk_time_sec = $totalTalkTimeSec;
            $tele->active_call_indicator = $activeCalls > 0;
            $tele->online_offline_status = $availability;
            $tele->break_tracking_status = $breakState;
            $tele->performance_rating = $performanceRating;
            $tele->conversion_rate = $conversionRate;

            if ($availability === 'online') {
                $onlineCount++;
            } else {
                $offlineCount++;
            }

            if ($breakState === 'on_call') {
                $onCallCount++;
            } elseif ($breakState === 'idle') {
                $idleCount++;
            }
        }

        return view('manager.telecallers.index', [
            'telecallers' => $telecallers,
            'totalTelecallers' => $telecallers->count(),
            'activeTelecallers' => $onlineCount,
            'inactiveTelecallers' => $telecallers->where('status', 0)->count(),
            'onlineTelecallers' => $onlineCount,
            'offlineTelecallers' => $offlineCount,
            'onCallTelecallers' => $onCallCount,
            'idleTelecallers' => $idleCount,
        ]);
    }
}
