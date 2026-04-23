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
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class LeadController extends Controller
{
    public function dashboard()
    {
        $userId = Auth::id();

        $totalAssignedLeads = Lead::whereAssignedTo($userId)->count();
        $newLeads = Lead::whereAssignedTo($userId)->where('status', 'new')->count();

        $hasCompletedAt = Cache::remember('schema_followups_completed_at', 3600, fn() => Schema::hasColumn('followups', 'completed_at'));

        $followupsTodayQuery = Followup::whereDate('next_followup', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo($userId));
        $overdueFollowupsQuery = Followup::whereDate('next_followup', '<', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo($userId));
        if ($hasCompletedAt) {
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

        $calQuery = Followup::whereHas('lead', fn($q) => $q->where('assigned_to', $userId))
            ->whereYear('next_followup', now()->year)
            ->whereMonth('next_followup', now()->month);
        if (Schema::hasColumn('followups', 'completed_at')) {
            $calQuery->whereNull('completed_at');
        }
        $followupCalendar = $calQuery
            ->selectRaw('DATE(next_followup) as day, COUNT(*) as total')
            ->groupByRaw('DATE(next_followup)')
            ->pluck('total', 'day');

        // Missed callbacks — serialise to plain arrays for JSON transport
        $missedCallbacksData = $missedCallbacks->map(fn($c) => [
            'id'               => $c->id,
            'phone'            => $c->phone,
            'status'           => $c->status,
            'created_at'       => $c->created_at?->toDateTimeString(),
            'lead_name'        => $c->lead?->name ?? 'Unknown Lead',
            'lead_code'        => $c->lead?->lead_code ?? '-',
            'lead_phone'       => $c->lead?->phone ?? $c->phone,
            'encrypted_lead_id'=> $c->lead_id ? encrypt($c->lead_id) : null,
        ]);

        return Inertia::render('Telecaller/Dashboard', [
            'stats' => [
                'assigned'       => $totalAssignedLeads,
                'new_leads'      => $newLeads,
                'followups'      => $followupsToday,
                'overdue'        => $overdueFollowups,
                'calls'          => $totalCallsToday,
                'talk_time_secs' => $talkTimeTodaySeconds,
                'active_calls'   => $activeCallCount,
            ],
            'missed_callbacks' => $missedCallbacksData,
            'followup_calendar' => $followupCalendar,
        ]);
    }

    /* ======================================================
        INDEX PAGE
    ======================================================*/
    public function index(Request $request)
    {
        $query = Lead::with(['enrolledCourse'])
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

        $leads = $query->latest()->paginate(10)->withQueryString()->through(function ($lead) {
            $lead->encrypted_id = encrypt($lead->id);
            $lead->course       = $lead->enrolledCourse?->name;
            $lead->days_aged    = (int) ($lead->created_at?->diffInDays(now()) ?? 0);
            $lead->makeHidden(['enrolledCourse', 'assignedBy', 'assignedUser', 'followups', 'activities', 'whatsappMessages', 'lastActivity']);
            return $lead;
        });


        /* ---------- DASHBOARD COUNTS ---------- */

        $indexCounts     = Lead::whereAssignedTo(Auth::id())
            ->selectRaw("COUNT(*) as total, SUM(status='new') as new_count, SUM(status='interested') as interested_count")
            ->first();
        $totalLeads      = (int) $indexCounts->total;
        $newLeads        = (int) $indexCounts->new_count;
        $interestedLeads = (int) $indexCounts->interested_count;

        $hasCompletedAt = Cache::remember('schema_followups_completed_at', 3600, fn() => Schema::hasColumn('followups', 'completed_at'));

        $followupTodayQuery = Followup::whereDate('next_followup', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo(Auth::id()));
        if ($hasCompletedAt) {
            $followupTodayQuery->whereNull('completed_at');
        }
        $followupToday = $followupTodayQuery->count();

        $activeCallCount = CallLog::where('user_id', Auth::id())
            ->whereIn('status', ['initiated', 'ringing', 'in-progress', 'answered'])
            ->count();

        return Inertia::render('Telecaller/Leads/Index', [
            'stats' => [
                'total'        => $totalLeads,
                'new'          => $newLeads,
                'interested'   => $interestedLeads,
                'followup'     => $followupToday,
                'active_calls' => $activeCallCount,
            ],
            'leads'   => $leads,
            'filters' => [
                'search'     => $request->search     ?? '',
                'status'     => $request->status     ?? '',
                'date_range' => $request->date_range ?? '',
            ],
        ]);
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
            'assignedBy:id,name',
            'enrolledCourse:id,name',
            'activities.user:id,name',
        ])
            ->where('assigned_to', Auth::id())
            ->findOrFail($id);

        $whatsappMessages = Schema::hasTable('whatsapp_messages')
            ? WhatsAppMessage::where('lead_id', $lead->id)->orderBy('created_at')->get()
            : collect();

        $waTemplateName = Setting::get('meta_whatsapp_template_name', 'welcome_template');
        $encId = encrypt($lead->id);

        // Flatten to plain scalars — JSX renders these directly as strings/numbers.
        // Raw Eloquent models would serialize relationship objects (User, Course) as nested
        // JS objects which React cannot render as children (Error #31).
        $leadData = [
            'id'          => $lead->id,
            'lead_code'   => $lead->lead_code,
            'name'        => $lead->name,
            'phone'       => $lead->phone,
            'email'       => $lead->email,
            'status'      => $lead->status,
            'course'      => $lead->enrolledCourse?->name,
            'assigned_by' => $lead->assignedBy?->name,
            'activities'  => $lead->activities->map(fn($a) => [
                'id'          => $a->id,
                'type'        => $a->type,
                'description' => $a->description,
                'user'        => $a->user?->name,
                'time'        => $a->activity_time?->format('d M Y, h:i A'),
            ])->values()->all(),
        ];

        return Inertia::render('Telecaller/Leads/Show', [
            'lead'              => $leadData,
            'whatsapp_messages' => $whatsappMessages,
            'wa_template_name'  => $waTemplateName,
            'urls' => [
                'wa_fetch'      => route('telecaller.leads.whatsapp.fetch',    $encId),
                'wa_store'      => route('telecaller.leads.whatsapp.store',    $encId),
                'wa_media'      => route('telecaller.leads.whatsapp.media',    $encId),
                'wa_template'   => route('telecaller.leads.whatsapp.template', $encId),
                'add_note'      => route('telecaller.leads.addNote',           $encId),
                'change_status' => route('telecaller.leads.changeStatus',      $encId),
                'call_outcome'  => route('call.outcome'),
            ],
        ]);
    }




    // ─── Pipeline (Kanban Board) ────────────────────────────────────────────────

    public function pipeline(Request $request)
    {
        $userId   = Auth::id();
        $statuses = ['new', 'assigned', 'contacted', 'interested', 'follow_up', 'not_interested', 'converted'];

        $base = Lead::with(['enrolledCourse', 'followups'])
            ->where('assigned_to', $userId);

        if ($request->search) {
            $s = $request->search;
            $base->where(fn($q) => $q
                ->where('lead_code', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%")
                ->orWhere('phone', 'like', "%$s%")
            );
        }

        if ($request->date_range) {
            if ($request->date_range === 'today') {
                $base->whereDate('created_at', today());
            } else {
                $base->whereDate('created_at', '>=', now()->subDays((int) $request->date_range));
            }
        }

        $columns = [];
        foreach ($statuses as $status) {
            $columns[$status] = (clone $base)->where('status', $status)->latest()->limit(60)->get()
                ->map(fn($lead) => [
                    'id'           => $lead->id,
                    'encrypted_id' => encrypt($lead->id),
                    'lead_code'    => $lead->lead_code,
                    'name'         => $lead->name,
                    'phone'        => $lead->phone,
                    'course'       => $lead->enrolledCourse?->name,
                    'days_aged'    => (int) ($lead->created_at?->diffInDays(now()) ?? 0),
                    'created_at'   => $lead->created_at?->format('d M'),
                    'next_followup'=> $lead->followups->sortByDesc('next_followup')->first()?->next_followup,
                ])->values()->all();
        }

        return Inertia::render('Telecaller/Leads/Pipeline', [
            'columns' => $columns,
            'filters' => [
                'search'     => $request->search     ?? '',
                'date_range' => $request->date_range ?? '',
            ],
            'urls' => [
                'pipeline'        => route('telecaller.leads.pipeline'),
                'pipeline_status' => route('telecaller.leads.pipeline.status'),
                'leads_index'     => route('telecaller.leads'),
                'lead_show_base'  => url('telecaller/leads'),
            ],
        ]);
    }

    public function updatePipelineStatus(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|string',
            'status'  => 'required|in:new,assigned,contacted,interested,not_interested,converted,follow_up',
        ]);

        try {
            $id = decrypt($request->lead_id);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid lead.'], 422);
        }

        $lead = Lead::where('assigned_to', Auth::id())->findOrFail($id);

        $oldStatus    = $lead->status;
        $lead->status = $request->status;
        $lead->save();

        $lead->activities()->create([
            'user_id'       => Auth::id(),
            'type'          => 'status_change',
            'description'   => 'Status changed to ' . ucfirst(str_replace('_', ' ', $request->status)) . ' via Pipeline',
            'activity_time' => now(),
        ]);

        AuditLogService::log(
            'lead.status_changed', 'Lead', $lead->id,
            ['status' => $oldStatus],
            ['status' => $request->status, 'source' => 'pipeline']
        );

        return response()->json(['success' => true, 'status' => $request->status]);
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
                'remarks'       => $request->remarks ?? '',
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
        $authUser = Auth::user();
        if (!$authUser || $authUser->role !== 'telecaller') {
            return response()->json(['ok' => false], 403);
        }

        $user = $authUser;

        $isOnline = (bool) ($user->is_online ?? false);
        $lastSeen = $user->last_seen_at ? Carbon::parse($user->last_seen_at) : null;
        if ($lastSeen && $lastSeen->lt(now()->subSeconds(90))) {
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

        $hasCompletedAt = Cache::remember('schema_followups_completed_at', 3600, fn() => Schema::hasColumn('followups', 'completed_at'));

        $followupCountQuery = Followup::whereDate('next_followup', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo($user->id));
        $overdueFollowupCountQuery = Followup::whereDate('next_followup', '<', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo($user->id));
        if ($hasCompletedAt) {
            $followupCountQuery->whereNull('completed_at');
            $overdueFollowupCountQuery->whereNull('completed_at');
        }
        $followupCount        = $followupCountQuery->count();
        $overdueFollowupCount = $overdueFollowupCountQuery->count();

        $leadCounts = Lead::whereAssignedTo($user->id)
            ->selectRaw("COUNT(*) as total, SUM(status='new') as new_count")
            ->first();
        $totalAssignedLeads = (int) $leadCounts->total;
        $newLeads           = (int) $leadCounts->new_count;

        $callStats = CallLog::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->selectRaw('COUNT(*) as total_calls, SUM(duration) as talk_time')
            ->first();
        $totalCallsToday      = (int) $callStats->total_calls;
        $talkTimeTodaySeconds = (int) $callStats->talk_time;

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
