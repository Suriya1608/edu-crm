<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Lead;
use App\Models\Followup;
use App\Models\CallLog;
use App\Models\Setting;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Schema;

class LeadController extends Controller
{
    public function dashboard()
    {
        $userId = Auth::id();

        $totalAssignedLeads = Lead::whereAssignedTo($userId)->count();
        $newLeads = Lead::whereAssignedTo($userId)->where('status', 'new')->count();

        $followupsTodayQuery = Followup::whereDate('next_followup', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo($userId));
        $overdueFollowupsQuery = Followup::whereDate('next_followup', '<', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo($userId));
        if (Schema::hasColumn('followups', 'completed_at')) {
            $followupsTodayQuery->whereNull('completed_at');
            $overdueFollowupsQuery->whereNull('completed_at');
        }
        $followupsToday = $followupsTodayQuery->count();
        $overdueFollowups = $overdueFollowupsQuery->count();

        $totalCallsToday = CallLog::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        $talkTimeTodaySeconds = (int) CallLog::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->sum('duration');

        $activeCallCount = CallLog::where('user_id', $userId)
            ->whereIn('status', ['initiated', 'ringing', 'in-progress', 'answered'])
            ->count();

        $missedCallbacks = CallLog::with('lead:id,name,lead_code,phone')
            ->where('user_id', $userId)
            ->where('direction', 'inbound')
            ->whereIn('status', ['missed', 'no-answer'])
            ->latest('id')
            ->limit(5)
            ->get();

        return view('telecaller.dashboard', compact(
            'totalAssignedLeads',
            'newLeads',
            'followupsToday',
            'overdueFollowups',
            'totalCallsToday',
            'talkTimeTodaySeconds',
            'activeCallCount',
            'missedCallbacks'
        ));
    }

    /* ======================================================
        INDEX PAGE
    ======================================================*/
    public function index(Request $request)
    {
        $query = Lead::with(['assignedBy', 'followups'])
            ->where('assigned_to', Auth::id());

        /* ---------- FILTERS ---------- */

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('lead_code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($request->date_range) {
            if ($request->date_range == 'today') {
                $query->whereDate('created_at', today());
            } else {
                $query->whereDate(
                    'created_at',
                    '>=',
                    now()->subDays($request->date_range)
                );
            }
        }

        $leads = $query->latest()->paginate(10);


        /* ---------- DASHBOARD COUNTS ---------- */

        $totalLeads = Lead::whereAssignedTo(Auth::id())->count();

        $newLeads = Lead::whereAssignedTo(Auth::id())
            ->where('status', 'new')->count();

        $interestedLeads = Lead::whereAssignedTo(Auth::id())
            ->where('status', 'interested')->count();

        $followupTodayQuery = Followup::whereDate('next_followup', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo(Auth::id()));
        if (Schema::hasColumn('followups', 'completed_at')) {
            $followupTodayQuery->whereNull('completed_at');
        }
        $followupToday = $followupTodayQuery->count();

        $activeCallCount = CallLog::where('user_id', Auth::id())
            ->whereIn('status', ['initiated', 'ringing', 'in-progress', 'answered'])
            ->count();

        $missedCallbacks = CallLog::with('lead:id,name,lead_code,phone')
            ->where('user_id', Auth::id())
            ->where('direction', 'inbound')
            ->whereIn('status', ['missed', 'no-answer'])
            ->latest('id')
            ->limit(5)
            ->get();

        $todayFollowupsQuery = Followup::with('lead:id,name,lead_code,phone')
            ->whereDate('next_followup', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo(Auth::id()))
            ->orderBy('next_followup');
        if (Schema::hasColumn('followups', 'completed_at')) {
            $todayFollowupsQuery->whereNull('completed_at');
        }
        $todayFollowups = $todayFollowupsQuery->limit(5)->get();


        return view('telecaller.leads.index', compact(
            'leads',
            'totalLeads',
            'newLeads',
            'interestedLeads',
            'followupToday',
            'activeCallCount',
            'missedCallbacks',
            'todayFollowups'
        ));
    }



    /* ======================================================
        SHOW PAGE
    ======================================================*/
    public function show($encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            abort(404);
        }

        $lead = Lead::with([
            'assignedBy',
            'activities.user',
            'followups'
        ])
            ->where('assigned_to', Auth::id())
            ->findOrFail($id);

        $provider = Setting::get('call_provider', 'twilio');
        $whatsappMessages = Schema::hasTable('whatsapp_messages')
            ? WhatsAppMessage::where('lead_id', $lead->id)->orderBy('created_at')->get()
            : collect();

        return view('telecaller.leads.show', compact('lead', 'provider', 'whatsappMessages'));
    }




    /* ======================================================
        STORE FOLLOWUP
    ======================================================*/
    public function storeFollowup(Request $request)
    {
        $request->validate([
            'lead_id'       => 'required|exists:leads,id',
            'status'        => 'required',
            'remarks'       => 'nullable|string',
            'next_followup' => 'nullable|date',
            'followup_time' => 'nullable|date_format:H:i',
        ]);

        $lead = Lead::where('assigned_to', Auth::id())
            ->findOrFail($request->lead_id);

        Followup::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'remarks'       => $request->remarks,
            'next_followup' => $request->next_followup,
            'followup_time' => $request->followup_time,
        ]);

        $lead->update([
            'status' => $request->status,
        ]);

        $timeStr = $request->followup_time ? ' at ' . date('h:i A', strtotime($request->followup_time)) : '';
        $desc = $request->next_followup
            ? "Follow-up scheduled for {$request->next_followup}{$timeStr} — Status: {$request->status}"
            : "Changed status to {$request->status}";

        $lead->activities()->create([
            'user_id'     => Auth::id(),
            'type'        => 'followup',
            'title'       => 'Follow-up Scheduled',
            'description' => $desc,
        ]);

        return back()->with('success', 'Follow-up scheduled successfully.');
    }



    /* ======================================================
        CHANGE STATUS
    ======================================================*/
    public function changeStatus(Request $request, $encryptedId)
    {
        $id = decrypt($encryptedId);
        $request->validate([
            'status'        => 'required',
            'next_followup' => 'nullable|date',
            'followup_time' => 'nullable|date_format:H:i',
            'remarks'       => 'nullable|string',
        ]);

        $lead = Lead::whereAssignedTo(Auth::id())
            ->findOrFail($id);

        $oldStatus = $lead->status;

        $lead->update([
            'status' => $request->status,
        ]);

        if ($request->status === 'follow_up' && $request->filled('next_followup')) {
            Followup::create([
                'lead_id'       => $lead->id,
                'user_id'       => Auth::id(),
                'remarks'       => $request->remarks,
                'next_followup' => $request->next_followup,
                'followup_time' => $request->followup_time,
            ]);

            $timeStr = $request->followup_time ? ' at ' . date('h:i A', strtotime($request->followup_time)) : '';
            $lead->activities()->create([
                'user_id'       => Auth::id(),
                'type'          => 'followup',
                'description'   => "Follow-up scheduled for {$request->next_followup}{$timeStr}",
                'activity_time' => Carbon::now('Asia/Kolkata'),
            ]);
        } else {
            $lead->activities()->create([
                'user_id'       => Auth::id(),
                'type'          => 'status_change',
                'description'   => "Status updated to {$request->status}",
                'activity_time' => Carbon::now('Asia/Kolkata'),
            ]);
        }

        AuditLogService::log('lead.status_changed', 'Lead', $lead->id, ['status' => $oldStatus], ['status' => $request->status]);

        return back()->with('success', 'Status updated');
    }



    /* ======================================================
        ADD NOTE
    ======================================================*/
    public function addNote(Request $request, $encryptedId)
    {
        $request->validate([
            'note' => 'required|string'
        ]);

        try {
            $id = decrypt($encryptedId);
        } catch (\Throwable $e) {
            $id = $encryptedId;
        }

        $lead = Lead::whereAssignedTo(Auth::id())
            ->findOrFail($id);

        $lead->activities()->create([
            'user_id' => Auth::id(),
            'type' => 'note',
            'title' => 'Note Added',
            'description' => $request->note
        ]);

        return back()->with('success', 'Note added');
    }



    /* ======================================================
        CALL LEAD (FOR JS TWILIO)
    ======================================================*/
    public function callLead($id)
    {
        $lead = Lead::whereAssignedTo(Auth::id())
            ->findOrFail($id);

        return response()->json([
            'phone' => $lead->phone
        ]);
    }



    /* ======================================================
        STORE CALL LOG (OPTIONAL WEBHOOK)
    ======================================================*/
    public function storeCallLog(Request $request)
    {
        CallLog::create([
            'lead_id' => $request->lead_id,
            'user_id' => Auth::id(),
            'provider' => 'browser',
            'call_sid' => $request->call_sid,
            'status' => $request->status ?? 'completed'
        ]);

        return response()->json(['success' => true]);
    }

    public function panelSnapshot()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'telecaller') {
            return response()->json(['ok' => false], 403);
        }

        $isOnline = (bool) ($user->is_online ?? false);
        $lastSeen = $user->last_seen_at ? Carbon::parse($user->last_seen_at) : null;
        if ($lastSeen && $lastSeen->lt(now()->subSeconds(60))) {
            $isOnline = false;
        }

        $activeCallCount = CallLog::where('user_id', $user->id)
            ->whereIn('status', ['initiated', 'ringing', 'in-progress', 'answered'])
            ->count();

        $missedCallbacks = CallLog::with('lead:id,name,lead_code,phone')
            ->where('user_id', $user->id)
            ->where('direction', 'inbound')
            ->whereIn('status', ['missed', 'no-answer'])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'lead_id' => $log->lead_id,
                    'encrypted_lead_id' => $log->lead_id ? encrypt($log->lead_id) : null,
                    'lead_name' => $log->lead->name ?? 'Unknown',
                    'lead_code' => $log->lead->lead_code ?? '-',
                    'phone' => $log->lead->phone ?? $log->customer_number,
                    'created_at' => optional($log->created_at)->format('d M Y, h:i A'),
                ];
            });

        $followupCountQuery = Followup::whereDate('next_followup', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo($user->id));
        $overdueFollowupCountQuery = Followup::whereDate('next_followup', '<', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo($user->id));
        if (Schema::hasColumn('followups', 'completed_at')) {
            $followupCountQuery->whereNull('completed_at');
            $overdueFollowupCountQuery->whereNull('completed_at');
        }
        $followupCount = $followupCountQuery->count();
        $overdueFollowupCount = $overdueFollowupCountQuery->count();

        $totalAssignedLeads = Lead::whereAssignedTo($user->id)->count();
        $newLeads = Lead::whereAssignedTo($user->id)->where('status', 'new')->count();
        $totalCallsToday = CallLog::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();
        $talkTimeTodaySeconds = (int) CallLog::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->sum('duration');

        return response()->json([
            'ok' => true,
            'is_online' => $isOnline,
            'active_call_count' => $activeCallCount,
            'call_status' => $activeCallCount > 0 ? 'On Call' : 'Idle',
            'missed_callback_count' => $missedCallbacks->count(),
            'missed_callbacks' => $missedCallbacks,
            'today_followup_count' => $followupCount,
            'overdue_followup_count' => $overdueFollowupCount,
            'total_assigned_leads' => $totalAssignedLeads,
            'new_leads' => $newLeads,
            'total_calls_today' => $totalCallsToday,
            'talk_time_today_seconds' => $talkTimeTodaySeconds,
        ]);
    }
    
}
