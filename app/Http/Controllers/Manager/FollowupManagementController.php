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

class FollowupManagementController extends Controller
{
    public function today(Request $request)
    {

        $myLeadsSubquery = Lead::where('assigned_by', Auth::id())->select('id');

        $followups = Followup::with(['lead.assignedUser', 'user'])
            ->whereIn('lead_id', $myLeadsSubquery)
            ->whereDate('next_followup', now()->toDateString())
            ->orderBy('next_followup')
            ->paginate(15)
            ->withQueryString();

        return view('manager.followups.today', [
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
            ->withQueryString();

        return view('manager.followups.overdue', [
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
            ->withQueryString();

        return view('manager.followups.upcoming', [
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
            ->withQueryString();

        return view('manager.followups.missed_by_telecaller', [
            'rows' => $rows,
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
