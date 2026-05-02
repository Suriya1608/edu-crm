<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Followup;
use App\Models\Lead;
use App\Notifications\WhatsAppInboundNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class FollowupManagementController extends Controller
{
    private function mapFollowup(Followup $f): array
    {
        $nf   = $f->next_followup;
        $now  = now()->startOfDay();
        $date = $nf ? $nf->startOfDay() : null;

        if ($f->completed_at) {
            $statusLabel = 'completed';
        } elseif ($date && $date->lt($now)) {
            $statusLabel = 'overdue';
        } elseif ($date && $date->eq($now)) {
            $statusLabel = 'today';
        } else {
            $statusLabel = 'upcoming';
        }

        return [
            'id'                 => $f->id,
            'next_followup'      => $nf?->format('Y-m-d'),
            'next_followup_fmt'  => $nf?->format('d M Y'),
            'followup_time'      => $f->followup_time,
            'followup_time_fmt'  => $f->followup_time ? Carbon::parse($f->followup_time)->format('h:i A') : null,
            'remarks'            => $f->remarks,
            'status_label'       => $statusLabel,
            'is_completed'       => (bool) $f->completed_at,
            'lead_id'            => $f->lead_id,
            'encrypted_lead_id'  => $f->lead_id ? encrypt($f->lead_id) : null,
            'lead_code'          => $f->lead?->lead_code,
            'lead_name'          => $f->lead?->name,
            'lead_phone'         => $f->lead?->phone,
            'telecaller_name'    => $f->lead?->assignedUser?->name ?? $f->user?->name,
        ];
    }

    public function today(Request $request)
    {
        $myLeadsSubquery = Lead::where('assigned_by', Auth::id())->select('id');

        $followups = Followup::with(['lead.assignedUser', 'user'])
            ->whereIn('lead_id', $myLeadsSubquery)
            ->whereDate('next_followup', now()->toDateString())
            ->orderBy('next_followup')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($f) => $this->mapFollowup($f));

        return Inertia::render('Manager/Followups/Index', [
            'scope'     => 'today',
            'title'     => 'Today Follow-ups',
            'followups' => $followups,
        ]);
    }

    public function overdue(Request $request)
    {
        $myLeadsSubquery = Lead::where('assigned_by', Auth::id())->select('id');

        $followups = Followup::with(['lead.assignedUser', 'user'])
            ->whereIn('lead_id', $myLeadsSubquery)
            ->whereDate('next_followup', '<', now()->toDateString())
            ->orderBy('next_followup')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($f) => $this->mapFollowup($f));

        return Inertia::render('Manager/Followups/Index', [
            'scope'     => 'overdue',
            'title'     => 'Overdue Follow-ups',
            'followups' => $followups,
        ]);
    }

    public function upcoming(Request $request)
    {
        $myLeadsSubquery = Lead::where('assigned_by', Auth::id())->select('id');

        $followups = Followup::with(['lead.assignedUser', 'user'])
            ->whereIn('lead_id', $myLeadsSubquery)
            ->whereDate('next_followup', '>', now()->toDateString())
            ->orderBy('next_followup')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($f) => $this->mapFollowup($f));

        return Inertia::render('Manager/Followups/Index', [
            'scope'     => 'upcoming',
            'title'     => 'Upcoming Follow-ups',
            'followups' => $followups,
        ]);
    }

    public function missedByTelecaller(Request $request)
    {
        $rows = Followup::query()
            ->join('leads', 'leads.id', '=', 'followups.lead_id')
            ->leftJoin('users as telecaller', 'telecaller.id', '=', 'leads.assigned_to')
            ->whereDate('followups.next_followup', '<', now()->toDateString())
            ->where('leads.assigned_by', Auth::id())
            ->select(
                'telecaller.id as telecaller_id',
                DB::raw("COALESCE(telecaller.name, 'Unassigned') as telecaller_name"),
                DB::raw('COUNT(followups.id) as missed_count'),
                DB::raw('MIN(followups.next_followup) as oldest_pending'),
                DB::raw('MAX(followups.next_followup) as latest_pending')
            )
            ->groupBy('telecaller.id', 'telecaller.name')
            ->orderByDesc('missed_count')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($row) => [
                'telecaller_id'   => $row->telecaller_id,
                'telecaller_name' => $row->telecaller_name,
                'missed_count'    => (int) $row->missed_count,
                'oldest_pending'  => $row->oldest_pending ? Carbon::parse($row->oldest_pending)->format('d M Y') : '—',
                'latest_pending'  => $row->latest_pending ? Carbon::parse($row->latest_pending)->format('d M Y') : '—',
            ]);

        return Inertia::render('Manager/Followups/Missed', [
            'rows' => $rows,
        ]);
    }

    public function calendarData(Request $request): \Illuminate\Http\JsonResponse
    {
        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);

        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $myLeadsSubquery = Lead::where('assigned_by', Auth::id())->select('id');

        $query = Followup::whereIn('lead_id', $myLeadsSubquery)
            ->whereYear('next_followup', $year)
            ->whereMonth('next_followup', $month);

        if (Schema::hasColumn('followups', 'completed_at')) {
            $query->whereNull('completed_at');
        }

        $days = $query
            ->selectRaw('DATE(next_followup) as day, COUNT(*) as total')
            ->groupByRaw('DATE(next_followup)')
            ->pluck('total', 'day');

        return response()->json([
            'year'  => $year,
            'month' => $month,
            'days'  => $days,
        ]);
    }

    public function markAllNotificationsRead(Request $request)
    {
        if ($request->user()) {
            $request->user()->unreadNotifications->markAsRead();
        }

        return back();
    }

    public function notificationsSnapshot(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'manager') {
            return response()->json(['ok' => false], 403);
        }

        if (!Schema::hasTable('notifications')) {
            return response()->json([
                'ok'                     => true,
                'badge_count'            => 0,
                'whatsapp_notifications' => [],
                'system_notifications'   => [],
            ]);
        }

        $allUnread = $user->unreadNotifications()->latest()->limit(15)->get();

        $whatsappNotifications = $allUnread
            ->where('type', WhatsAppInboundNotification::class)
            ->take(5)
            ->map(function ($n) {
                return [
                    'id'      => $n->id,
                    'title'   => $n->data['title']   ?? 'WhatsApp message',
                    'message' => $n->data['message']  ?? '',
                    'link'    => $n->data['link']     ?? '#',
                    'time'    => optional($n->created_at)->diffForHumans(),
                ];
            })->values();

        $systemNotifications = $allUnread
            ->where('type', '!=', WhatsAppInboundNotification::class)
            ->take(5)
            ->map(function ($n) {
                $payload = $n->data ?? [];
                return [
                    'id'      => $n->id,
                    'title'   => $payload['title']   ?? 'Notification',
                    'message' => $payload['message'] ?? $payload['body'] ?? '-',
                    'link'    => $payload['link']    ?? '#',
                    'time'    => optional($n->created_at)->diffForHumans(),
                ];
            })->values();

        $badgeCount = $whatsappNotifications->count() + $systemNotifications->count();

        return response()->json([
            'ok'                     => true,
            'badge_count'            => $badgeCount,
            'whatsapp_notifications' => $whatsappNotifications,
            'system_notifications'   => $systemNotifications,
        ]);
    }

    /**
     * Fast-poll endpoint for WhatsApp inbound notifications only.
     * Called every 5 s by the real-time toast script.
     *
     * ?after=<ISO-8601 timestamp> — returns notifications created after that time.
     * Omit `after` (first load / login) — returns last 2 h of unread notifications.
     */
    public function whatsappInboxPoll(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'manager') {
            return response()->json(['ok' => false], 403);
        }

        if (!Schema::hasTable('notifications')) {
            return response()->json(['ok' => true, 'items' => [], 'ts' => now()->toISOString()]);
        }

        $after   = $request->query('after');
        $isFirst = !$after;

        $query = $user->unreadNotifications()
            ->where('type', WhatsAppInboundNotification::class);

        if ($after) {
            $query->where('created_at', '>', Carbon::parse($after));
        } else {
            $query->where('created_at', '>', now()->subHours(2));
        }

        $items = $query->latest()->limit(10)->get()->map(fn($n) => [
            'id'      => $n->id,
            'title'   => $n->data['title']   ?? 'New WhatsApp',
            'message' => $n->data['message'] ?? '',
            'link'    => $n->data['link']    ?? '#',
        ])->values();

        return response()->json([
            'ok'       => true,
            'is_first' => $isFirst,
            'items'    => $items,
            'ts'       => now()->toISOString(),
        ]);
    }
}
