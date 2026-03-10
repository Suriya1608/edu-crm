<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\WhatsAppInboundNotification;
use App\Services\AutomationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class TelecallerStatusController extends Controller
{
    public function __construct(
        private AutomationSettings $automationSettings,
    ) {
    }

    public function heartbeat(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'telecaller') {
            return response()->json(['ok' => false], 403);
        }

        // Throttle DB writes: only write if no heartbeat was recorded in the last 25s.
        // The JS polls every 30s, so this prevents redundant writes on every request.
        $cacheKey = 'heartbeat_tc_' . $user->id;
        if (!Cache::has($cacheKey)) {
            if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
                User::where('id', $user->id)->update([
                    'is_online'    => true,
                    'last_seen_at' => now(),
                ]);
            }
            Cache::put($cacheKey, true, 25); // 25s TTL
        }

        return response()->json(['ok' => true]);
    }

    public function managerHeartbeat(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'manager') {
            return response()->json(['ok' => false], 403);
        }

        $cacheKey = 'heartbeat_mgr_' . $user->id;
        if (!Cache::has($cacheKey)) {
            if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
                User::where('id', $user->id)->update([
                    'is_online'    => true,
                    'last_seen_at' => now(),
                ]);
            }
            Cache::put($cacheKey, true, 25);
        }

        return response()->json(['ok' => true]);
    }

    public function offline(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'telecaller') {
            return response()->json(['ok' => false], 403);
        }

        if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
            User::where('id', $user->id)->update([
                'is_online' => false,
                'last_seen_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function setAvailability(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'telecaller') {
            return response()->json(['ok' => false], 403);
        }

        $request->validate([
            'is_online' => 'required|boolean',
        ]);

        if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
            User::where('id', $user->id)->update([
                'is_online' => (bool) $request->boolean('is_online'),
                'last_seen_at' => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'is_online' => (bool) $request->boolean('is_online'),
        ]);
    }

    public function managerSnapshot()
    {
        if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
            User::where('role', 'telecaller')
                ->where('is_online', true)
                ->where('last_seen_at', '<', now()->subSeconds(90))
                ->update(['is_online' => false]);
        }

        $telecallers = User::where('role', 'telecaller')
            ->select('id', 'name', 'phone', 'is_online', 'last_seen_at')
            ->orderBy('users.name')
            ->get()
            ->map(function ($row) {
                $isOnline = (bool) ($row->is_online ?? false);
                $lastSeen = $row->last_seen_at ? \Carbon\Carbon::parse($row->last_seen_at) : null;
                if ($lastSeen && $lastSeen->lt(now()->subSeconds(90))) {
                    $isOnline = false;
                }

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'phone' => $row->phone,
                    'is_online' => $isOnline,
                    'last_seen_at' => $lastSeen?->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json(['telecallers' => $telecallers]);
    }

    public function notificationsSnapshot()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'telecaller') {
            return response()->json(['ok' => false], 403);
        }

        $missedCalls = CallLog::with('lead:id,name,lead_code,phone')
            ->where('user_id', $user->id)
            ->where('direction', 'inbound')
            ->whereIn('status', ['missed', 'no-answer'])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'lead_name' => $log->lead->name ?? 'Unknown lead',
                    'lead_code' => $log->lead->lead_code ?? '-',
                    'phone' => $log->lead->phone ?? $log->customer_number,
                    'time' => optional($log->created_at)->format('d M, h:i A'),
                    'lead_url' => $log->lead_id ? route('telecaller.leads.show', encrypt($log->lead_id)) : null,
                ];
            });

        $followupReminders = collect();
        if (
            $this->automationSettings->followupReminderEnabled()
            && Setting::get('notify_inapp_followup_reminder', '1') === '1'
        ) {
            $daysBefore = $this->automationSettings->followupReminderDaysBefore();
            $highlightOverdue = $this->automationSettings->followupOverdueHighlightEnabled();
            $maxDate = today()->addDays($daysBefore)->toDateString();

            $followupReminders = Followup::with('lead:id,name,lead_code')
                ->whereHas('lead', fn($q) => $q->where('assigned_to', $user->id))
                ->whereDate('next_followup', '<=', $maxDate)
                ->orderBy('next_followup')
                ->limit(5)
                ->when(Schema::hasColumn('followups', 'completed_at'), function ($q) {
                    $q->whereNull('completed_at');
                })
                ->get()
                ->map(function ($f) use ($highlightOverdue) {
                    $isOverdue = optional($f->next_followup)->lt(today());
                    $isToday = optional($f->next_followup)->isSameDay(today());
                    return [
                        'id' => $f->id,
                        'lead_name' => $f->lead->name ?? 'Unknown lead',
                        'lead_code' => $f->lead->lead_code ?? '-',
                        'next_followup' => optional($f->next_followup)->format('d M Y'),
                        'type' => $isOverdue
                            ? ($highlightOverdue ? 'overdue' : 'today')
                            : ($isToday ? 'today' : 'upcoming'),
                        'remarks' => $f->remarks,
                    ];
                })->values();
        }

        $allUnread = $user->unreadNotifications()->latest()->limit(10)->get();

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
                $message = $n->data['message']
                    ?? $n->data['body']
                    ?? $n->data['title']
                    ?? 'System notification';

                return [
                    'id'      => $n->id,
                    'message' => (string) $message,
                    'time'    => optional($n->created_at)->diffForHumans(),
                ];
            })->values();

        $badgeCount = $missedCalls->count() + $followupReminders->count()
            + $whatsappNotifications->count() + $systemNotifications->count();

        return response()->json([
            'ok'                    => true,
            'badge_count'           => $badgeCount,
            'missed_calls'          => $missedCalls,
            'followup_reminders'    => $followupReminders,
            'whatsapp_notifications' => $whatsappNotifications,
            'system_notifications'  => $systemNotifications,
        ]);
    }

    public function markNotificationsRead()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'telecaller') {
            return response()->json(['ok' => false], 403);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Fast-poll endpoint for WhatsApp inbound notifications only.
     * Called every 5 s by the real-time toast script.
     *
     * ?after=<ISO-8601 timestamp> — returns notifications created after that time.
     * Omit `after` (first load / login) — returns last 2 h of unread, no sound/toasts.
     */
    public function whatsappInboxPoll(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'telecaller') {
            return response()->json(['ok' => false], 403);
        }

        if (!Schema::hasTable('notifications')) {
            return response()->json(['ok' => true, 'items' => [], 'ts' => now()->toISOString()]);
        }

        $after     = $request->query('after');
        $isFirst   = !$after;

        $query = $user->unreadNotifications()
            ->where('type', WhatsAppInboundNotification::class);

        if ($after) {
            $query->where('created_at', '>', Carbon::parse($after));
        } else {
            // On first load show only last 2 hours so we don't flood old history
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
