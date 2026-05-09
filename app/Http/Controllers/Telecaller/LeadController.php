<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Models\CourseIntake;
use App\Models\Lead;
use App\Models\Followup;
use App\Models\CallLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\LeadMeeting;
use App\Models\WhatsAppMessage;
use App\Exports\ArrayExport;
use App\Mail\LeadEmail;
use App\Models\EmailTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

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

        $callOutcomes = CallLog::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->whereNotNull('outcome')
            ->selectRaw('outcome, COUNT(*) as count')
            ->groupBy('outcome')
            ->pluck('count', 'outcome');

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
            'missed_callbacks'  => $missedCallbacksData,
            'followup_calendar' => $followupCalendar,
            'call_outcomes'     => $callOutcomes,
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

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        $sortable = ['name', 'created_at', 'next_followup'];
        $sort     = in_array($request->sort, $sortable) ? $request->sort : 'created_at';
        $sortDir  = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $perPage  = in_array((int) $request->per_page, [15, 25, 50]) ? (int) $request->per_page : 15;

        $query->with(['enrolledCourse', 'lastActivity'])
              ->withMax('callLogs as last_call_at', 'created_at')
              ->orderBy($sort, $sortDir);

        $leads = $query->paginate($perPage)->withQueryString()->through(function ($lead) {
            $lead->encrypted_id = encrypt($lead->id);
            $lead->course       = $lead->enrolledCourse?->name;
            $lead->days_aged    = (int) ($lead->created_at?->diffInDays(now()) ?? 0);

            // Most recent touch: compare lastActivity vs latest call_log
            $actAt   = $lead->lastActivity?->created_at;
            $callAt  = $lead->last_call_at ? \Carbon\Carbon::parse($lead->last_call_at) : null;
            $lastAt  = collect([$actAt, $callAt])->filter()->max();
            $lastType = null;
            if ($lastAt) {
                if ($callAt && $lastAt->eq($callAt)) {
                    $lastType = 'call';
                } else {
                    $lastType = $lead->lastActivity?->type ?? 'note';
                }
            }

            $lead->last_activity_at   = $lastAt?->toISOString();
            $lead->last_activity_type = $lastType;

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

        $overdueFollowupQuery = Followup::whereDate('next_followup', '<', today())
            ->whereHas('lead', fn($q) => $q->whereAssignedTo(Auth::id()));
        if ($hasCompletedAt) $overdueFollowupQuery->whereNull('completed_at');
        $overdueFollowups = $overdueFollowupQuery->count();

        $convertedThisMonth = Lead::whereAssignedTo(Auth::id())
            ->where('status', 'converted')
            ->whereYear('updated_at', now()->year)
            ->whereMonth('updated_at', now()->month)
            ->count();

        $activeCallCount = CallLog::where('user_id', Auth::id())
            ->whereIn('status', ['initiated', 'ringing', 'in-progress', 'answered'])
            ->count();

        $courses = Course::orderBy('name')->get(['id', 'name'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all();

        $sources = Lead::where('assigned_to', Auth::id())
            ->whereNotNull('source')->where('source', '!=', '')
            ->distinct()->pluck('source')->sort()->values()->all();

        return Inertia::render('Telecaller/Leads/Index', [
            'stats' => [
                'total'           => $totalLeads,
                'new'             => $newLeads,
                'interested'      => $interestedLeads,
                'followup'        => $followupToday,
                'overdue'         => $overdueFollowups,
                'converted_month' => $convertedThisMonth,
                'active_calls'    => $activeCallCount,
            ],
            'leads'   => $leads,
            'courses' => $courses,
            'sources' => $sources,
            'filters' => [
                'search'     => $request->search     ?? '',
                'status'     => $request->status     ?? '',
                'date_range' => $request->date_range ?? '',
                'course_id'  => $request->course_id  ?? '',
                'source'     => $request->source     ?? '',
                'sort'       => $request->sort        ?? '',
                'sort_dir'   => $request->sort_dir    ?? '',
                'per_page'   => $request->per_page    ?? '',
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
                ->map(fn($m) => [
                    'id'             => $m->id,
                    'body'           => $m->message_body,
                    'direction'      => $m->direction,
                    'time'           => $m->created_at?->format('h:i A'),
                    'date'           => $m->created_at?->format('d M Y'),
                    'status'         => data_get($m->meta_data, 'meta_status', 'sent'),
                    'media_type'     => $m->media_type,
                    'media_url'      => $m->media_url ? asset('storage/' . $m->media_url) : null,
                    'media_filename' => $m->media_filename,
                ])
            : collect();

        $waTemplateName  = Setting::get('meta_whatsapp_template_name', 'welcome_template');
        $waSessionActive = Schema::hasTable('whatsapp_messages') && WhatsAppMessage::where('lead_id', $lead->id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
        $encId = encrypt($lead->id);

        // Build unified, descending timeline from lead_activities + call_logs + whatsapp_messages.
        // Exclude type='call' and type='whatsapp' from lead_activities — both are sourced from
        // their dedicated tables which carry richer data and cover inbound records too.
        $activityItems = $lead->activities->filter(fn($a) => !in_array($a->type, ['call', 'whatsapp']))->map(fn($a) => [
            'id'          => 'a-' . $a->id,
            'type'        => $a->type,
            'description' => $a->description,
            'user'        => $a->user?->name,
            'time'        => $a->activity_time?->format('d M Y, h:i A'),
            'sort_ts'     => $a->activity_time?->timestamp ?? 0,
            'direction'   => null,
            'duration'    => null,
            'outcome'     => null,
            'call_status' => null,
        ]);

        $callLogItems = CallLog::where('lead_id', $lead->id)
            ->with('user:id,name')
            ->get()
            ->map(function ($c) {
                $mins = intdiv((int) $c->duration, 60);
                $secs = (int) $c->duration % 60;
                $durationStr = $c->duration > 0
                    ? ($mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s")
                    : null;

                $desc = ucfirst($c->direction ?? 'outbound') . ' call';
                if ($durationStr) $desc .= " · {$durationStr}";
                if ($c->outcome)  $desc .= ' · ' . ucfirst(str_replace('_', ' ', $c->outcome));
                elseif ($c->status) $desc .= ' · ' . ucfirst(str_replace('-', ' ', $c->status));

                return [
                    'id'          => 'c-' . $c->id,
                    'type'        => 'call',
                    'description' => $desc,
                    'user'        => $c->user?->name,
                    'time'        => $c->created_at?->format('d M Y, h:i A'),
                    'sort_ts'     => $c->created_at?->timestamp ?? 0,
                    'direction'   => $c->direction ?? 'outbound',
                    'duration'    => $durationStr,
                    'outcome'     => $c->outcome,
                    'call_status' => $c->status,
                ];
            });

        $waItems = Schema::hasTable('whatsapp_messages')
            ? WhatsAppMessage::where('lead_id', $lead->id)
                ->get()
                ->map(fn($m) => [
                    'id'          => 'w-' . $m->id,
                    'type'        => 'whatsapp',
                    'description' => $m->media_type
                        ? ucfirst($m->direction ?? 'outbound') . ': [' . $m->media_type . ']'
                        : ucfirst($m->direction ?? 'outbound') . ': ' . mb_strimwidth($m->message_body ?? '', 0, 80, '…'),
                    'user'        => null,
                    'time'        => $m->created_at?->format('d M Y, h:i A'),
                    'sort_ts'     => $m->created_at?->timestamp ?? 0,
                    'direction'   => $m->direction ?? 'outbound',
                    'duration'    => null,
                    'outcome'     => null,
                    'call_status' => null,
                ])
            : collect();

        $timeline = $activityItems->concat($callLogItems)->concat($waItems)
            ->sortByDesc('sort_ts')
            ->values()
            ->all();

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
            'activities'  => $timeline,
        ];

        $meetings = Schema::hasTable('lead_meetings')
            ? LeadMeeting::where('lead_id', $lead->id)
                ->with('creator:id,name')
                ->orderByDesc('meeting_time')
                ->get()
                ->map(fn($m) => [
                    'id'            => $m->id,
                    'title'         => $m->title,
                    'meeting_link'  => $m->meeting_link,
                    'meeting_time'  => $m->meeting_time?->format('d M Y, h:i A'),
                    'duration'      => $m->duration,
                    'notes'         => $m->notes,
                    'status'        => $m->status,
                    'meeting_type'  => $m->meeting_type ?? 'google',
                    'whatsapp_sent' => $m->whatsapp_sent,
                    'created_by'    => $m->creator?->name ?? '—',
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('Telecaller/Leads/Show', [
            'lead'              => $leadData,
            'whatsapp_messages' => $whatsappMessages,
            'wa_template_name'  => $waTemplateName,
            'wa_session_active' => $waSessionActive,
            'meetings'          => $meetings,
            'urls' => [
                'wa_fetch'      => route('telecaller.leads.whatsapp.fetch',    $encId),
                'wa_store'      => route('telecaller.leads.whatsapp.store',    $encId),
                'wa_media'      => route('telecaller.leads.whatsapp.media',    $encId),
                'wa_template'   => route('telecaller.leads.whatsapp.template', $encId),
                'add_note'      => route('telecaller.leads.addNote',           $encId),
                'change_status' => route('telecaller.leads.changeStatus',      $encId),
                'call_outcome'  => route('call.outcome'),
                'meet_start'    => route('telecaller.leads.meet.start',    $encId),
                'meet_schedule' => route('telecaller.leads.meet.schedule', $encId),
                'meet_status'   => route('telecaller.leads.meet.status', ['meetingId' => '__ID__']),
                'zoom_start'    => route('telecaller.leads.zoom.start',    $encId),
                'zoom_schedule' => route('telecaller.leads.zoom.schedule', $encId),
                'email'         => route('telecaller.leads.email', $encId),
            ],
            'email_templates' => EmailTemplate::active()->map(fn($t) => [
                'id'      => $t->id,
                'name'    => $t->name,
                'subject' => $t->subject ?? '',
                'body'    => $t->body ?? '',
            ])->values()->all(),
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

        // Track seat enrollment on conversion
        if ($request->status === 'converted' && $oldStatus !== 'converted') {
            CourseIntake::incrementEnrolled($lead);
        } elseif ($oldStatus === 'converted' && $request->status !== 'converted') {
            CourseIntake::decrementEnrolled($lead);
        }

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
            'quota'         => 'required_if:status,converted|nullable|in:management,counselling',
            'next_followup' => 'nullable|date',
            'followup_time' => 'nullable|date_format:H:i',
            'remarks'       => 'nullable|string',
        ]);

        $lead = Lead::whereAssignedTo(Auth::id())
            ->findOrFail($id);

        $oldStatus = $lead->status;

        // Save quota when converting
        if ($request->status === 'converted' && $request->filled('quota')) {
            $lead->quota = $request->quota;
        }
        $lead->status = $request->status;
        $lead->save();

        // Track seat enrollment on conversion
        if ($request->status === 'converted' && $oldStatus !== 'converted') {
            CourseIntake::incrementEnrolled($lead);
        } elseif ($oldStatus === 'converted' && $request->status !== 'converted') {
            CourseIntake::decrementEnrolled($lead);
        }

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
            'user_id'       => Auth::id(),
            'type'          => 'note',
            'title'         => 'Note Added',
            'description'   => $request->note,
            'activity_time' => now(),
        ]);

        return back()->with('success', 'Note added');
    }



    /* ======================================================
        BULK STATUS UPDATE
    ======================================================*/
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'status' => 'required|in:new,assigned,contacted,interested,not_interested,converted,follow_up,lost',
        ]);

        $leads = Lead::where('assigned_to', Auth::id())
            ->whereIn('id', $request->ids)
            ->get();

        foreach ($leads as $lead) {
            $old = $lead->status;
            $lead->update(['status' => $request->status]);
            $lead->activities()->create([
                'user_id'       => Auth::id(),
                'type'          => 'status_change',
                'description'   => 'Bulk status updated to ' . ucfirst(str_replace('_', ' ', $request->status)),
                'activity_time' => now(),
            ]);
            AuditLogService::log('lead.status_changed', 'Lead', $lead->id,
                ['status' => $old],
                ['status' => $request->status, 'source' => 'bulk']
            );
        }

        return response()->json(['updated' => $leads->count()]);
    }



    /* ======================================================
        SEND EMAIL TO LEAD
    ======================================================*/
    public function sendEmail(Request $request, $encryptedId)
    {
        $request->validate([
            'subject'         => 'required|string|max:255',
            'body'            => 'required|string',
            'attachments'     => 'nullable|array',
            'attachments.*'   => 'file|max:10240',
        ]);

        try {
            $id = decrypt($encryptedId);
        } catch (\Throwable) {
            $id = $encryptedId;
        }

        $lead = Lead::where('assigned_to', Auth::id())->findOrFail($id);

        if (!$lead->email) {
            return response()->json(['error' => 'This lead has no email address.'], 422);
        }

        $paths = [];
        foreach ($request->file('attachments', []) as $file) {
            $paths[] = $file->store('email_attachments/tmp', 'local');
        }

        Mail::to($lead->email, $lead->name)->send(
            new LeadEmail(
                $request->subject,
                $request->body,
                array_map(fn($p) => storage_path('app/' . $p), $paths)
            )
        );

        foreach ($paths as $p) {
            Storage::disk('local')->delete($p);
        }

        $lead->activities()->create([
            'user_id'     => Auth::id(),
            'type'        => 'email',
            'title'       => 'Email Sent',
            'description' => 'Subject: ' . $request->subject,
        ]);

        AuditLogService::log('lead.email_sent', 'Lead', $lead->id, [], ['subject' => $request->subject]);

        return response()->json(['message' => 'Email sent successfully.']);
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

        $callOutcomes = CallLog::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->whereNotNull('outcome')
            ->selectRaw('outcome, COUNT(*) as count')
            ->groupBy('outcome')
            ->pluck('count', 'outcome');

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
            'total_calls_today'        => $totalCallsToday,
            'talk_time_today_seconds'  => $talkTimeTodaySeconds,
            'call_outcomes'            => $callOutcomes,
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->input('format', 'excel');

        $query = Lead::with(['enrolledCourse'])->where('assigned_to', Auth::id());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('lead_code', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($request->filled('date_range')) {
            $dr = $request->input('date_range');
            if ($dr === 'today') {
                $query->whereDate('created_at', today());
            } else {
                $query->whereDate('created_at', '>=', now()->subDays($dr));
            }
        }

        $leads = $query->latest()->get()->values()->map(function ($lead, $idx) {
            return [
                'sno'       => $idx + 1,
                'lead_code' => $lead->lead_code ?? '—',
                'name'      => $lead->name ?? '—',
                'phone'     => $lead->phone ?? '—',
                'email'     => $lead->email ?? '',
                'course'    => $lead->enrolledCourse?->name ?? '—',
                'status'    => ucfirst(str_replace('_', ' ', $lead->status ?? '')),
                'created_at'=> $lead->created_at?->format('d M Y') ?? '—',
            ];
        })->toArray();

        $filename = 'leads-export-' . now()->format('Ymd-His');
        $meta = ['userName' => Auth::user()->name, 'generatedAt' => now()->format('d M Y, h:i A')];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.telecaller.leads', array_merge($meta, ['leads' => $leads]))
                ->setPaper('a4', 'landscape');
            return $pdf->download($filename . '.pdf');
        }

        $headings = ['S.No', 'Lead Code', 'Name', 'Phone', 'Email', 'Course', 'Status', 'Created At'];
        return Excel::download(new ArrayExport($leads, $headings, 'Leads'), $filename . '.xlsx');
    }
}
