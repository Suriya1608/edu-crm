<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $today = $now->toDateString();

        $totalLeads = Lead::count();
        $totalManagers = User::where('role', 'manager')->count();
        $totalTelecallers = User::where('role', 'telecaller')->count();

        $activeCallsNow = CallLog::whereIn('status', ['initiated', 'ringing', 'in-progress', 'answered'])->count();

        $missedCallsToday = CallLog::whereDate('created_at', $today)
            ->whereIn('status', ['missed', 'no-answer', 'busy', 'failed', 'canceled'])
            ->count();

        $followupsTodayQuery = Followup::whereDate('next_followup', $today);
        if (Schema::hasColumn('followups', 'completed_at')) {
            $followupsTodayQuery->whereNull('completed_at');
        }
        $followupsToday = $followupsTodayQuery->count();

        $conversionsThisMonth = Lead::where('status', 'converted')
            ->whereBetween('updated_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        $sourceRows = Lead::selectRaw("COALESCE(NULLIF(source, ''), 'Unknown') as source_name, COUNT(*) as total")
            ->groupBy('source_name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $sourceLabels = $sourceRows->pluck('source_name')->toArray();
        $sourceValues = $sourceRows->pluck('total')->map(fn($v) => (int) $v)->toArray();

        $callRows = CallLog::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$now->copy()->subDays(13)->startOfDay(), $now->copy()->endOfDay()])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $callVolumeLabels = [];
        $callVolumeValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->toDateString();
            $callVolumeLabels[] = Carbon::parse($day)->format('d M');
            $callVolumeValues[] = (int) (($callRows[$day]->total ?? 0));
        }

        $waVolumeLabels = [];
        $waInboundValues = [];
        $waOutboundValues = [];
        if (Schema::hasTable('whatsapp_messages')) {
            $waRows = WhatsAppMessage::selectRaw('DATE(created_at) as day, direction, COUNT(*) as total')
                ->whereBetween('created_at', [$now->copy()->subDays(13)->startOfDay(), $now->copy()->endOfDay()])
                ->groupBy('day', 'direction')
                ->orderBy('day')
                ->get();

            $waMatrix = [];
            foreach ($waRows as $row) {
                $waMatrix[$row->day][strtolower((string) $row->direction)] = (int) $row->total;
            }

            for ($i = 13; $i >= 0; $i--) {
                $day = $now->copy()->subDays($i)->toDateString();
                $waVolumeLabels[] = Carbon::parse($day)->format('d M');
                $waInboundValues[] = (int) ($waMatrix[$day]['inbound'] ?? 0);
                $waOutboundValues[] = (int) ($waMatrix[$day]['outbound'] ?? 0);
            }
        } else {
            for ($i = 13; $i >= 0; $i--) {
                $waVolumeLabels[] = $now->copy()->subDays($i)->format('d M');
                $waInboundValues[] = 0;
                $waOutboundValues[] = 0;
            }
        }

        $courseStats = Lead::join('courses', 'leads.course_id', '=', 'courses.id')
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

        return view('admin.dashboard', compact(
            'totalLeads',
            'totalManagers',
            'totalTelecallers',
            'activeCallsNow',
            'missedCallsToday',
            'followupsToday',
            'conversionsThisMonth',
            'sourceLabels',
            'sourceValues',
            'callVolumeLabels',
            'callVolumeValues',
            'waVolumeLabels',
            'waInboundValues',
            'waOutboundValues',
            'courseStats'
        ));
    }
}

