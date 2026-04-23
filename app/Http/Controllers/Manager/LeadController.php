<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\LeadActivity;
use Illuminate\Support\Facades\Crypt;
use App\Models\Followup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Schema;
use App\Notifications\LeadAssignmentNotification;
use App\Services\AuditLogService;
use App\Services\LeadDefaults;
use Inertia\Inertia;
class LeadController extends Controller
{


    public function index(Request $request)
    {
        $managerId = Auth::id();
        $query = Lead::with(['assignedUser', 'followups', 'enrolledCourse'])->where('assigned_by', $managerId);

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('lead_code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('source', 'like', '%' . $search . '%')
                    ->orWhereHas('enrolledCourse', fn($cq) => $cq->where('name', 'like', '%' . $search . '%'));
            });
        }


        // Telecaller filter
        if ($request->telecaller) {
            $query->where('assigned_to', $request->telecaller);
        }

        // Status filter
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Date filter


        if ($request->date_range) {

            if ($request->date_range == '7') {
                $query->whereDate('created_at', '>=', now()->subDays(7));
            }

            if ($request->date_range == '30') {
                $query->whereDate('created_at', '>=', now()->subDays(30));
            }

            if ($request->date_range == 'today') {
                $query->whereDate('created_at', now());
            }
        }



        $leads = $query->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $telecallers = User::where('role', 'telecaller')
            ->where('status', 1)
            ->whereIn('id', $myTelecallerIds)
            ->get();

        $myLeadsSubquery = Lead::where('assigned_by', $managerId)->select('id');

        $leadCounts    = Lead::where('assigned_by', $managerId)
            ->selectRaw("COUNT(*) as total, SUM(status='new') as new_count, SUM(status='assigned') as assigned_count")
            ->first();
        $totalLeads    = (int) $leadCounts->total;
        $newLeads      = (int) $leadCounts->new_count;
        $assignedLeads = (int) $leadCounts->assigned_count;
        $followupToday = Followup::whereDate('next_followup', now())->whereIn('lead_id', $myLeadsSubquery)->count();

        $leadsData = $leads->through(fn($lead) => [
            'id'            => $lead->id,
            'encrypted_id'  => encrypt($lead->id),
            'lead_code'     => $lead->lead_code,
            'name'          => $lead->name,
            'phone'         => $lead->phone,
            'email'         => $lead->email,
            'source'        => $lead->source,
            'course'        => $lead->course,
            'status'        => $lead->status,
            'assigned_user' => $lead->assignedUser?->name,
            'days_aged'     => $lead->days_aged,
            'is_duplicate'  => $lead->is_duplicate,
            'next_followup' => $lead->followups->sortByDesc('next_followup')->first()?->next_followup,
        ]);

        return Inertia::render('Manager/Leads/Index', [
            'leads'         => $leadsData,
            'telecallers'   => $telecallers->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'totalLeads'    => $totalLeads,
            'newLeads'      => $newLeads,
            'assignedLeads' => $assignedLeads,
            'followupToday' => $followupToday,
            'filters'       => request()->only(['search', 'telecaller', 'status', 'date_range']),
        ]);
    }

    public function assign(Request $request, $id)
    {
        $leadId = decrypt($id);

        $lead = Lead::findOrFail($leadId);

        $oldUser      = $lead->assignedUser ? $lead->assignedUser->name : null;
        $oldAssignedTo = $lead->assigned_to;

        $newUser = User::findOrFail($request->assigned_to);

        // Update Lead
        $lead->assigned_to = $newUser->id;
        $lead->assigned_by = Auth::id();
        $lead->status = 'assigned';
        $lead->save();

        AuditLogService::log('lead.assigned', 'Lead', $lead->id, ['assigned_to' => $oldAssignedTo], ['assigned_to' => $newUser->id, 'assigned_to_name' => $newUser->name]);

        $newUser->notify(new LeadAssignmentNotification(
            title: 'Lead Assigned by Manager',
            message: 'Lead ' . ($lead->lead_code ?? ('#' . $lead->id)) . ' assigned to you.',
            link: route('telecaller.leads.show', encrypt($lead->id)),
            meta: ['type' => 'lead_assignment', 'lead_id' => $lead->id]
        ));

        // Store Activity
        LeadActivity::create([
            'lead_id'     => $lead->id,
            'user_id'     => Auth::id(), // manager who changed
            'type'        => 'assignment',
            'title'       => $oldUser
                ? 'Lead Reassigned'
                : 'Lead Assigned',
            'description' => $oldUser
                ? "Reassigned from {$oldUser} to {$newUser->name}"
                : "Assigned to {$newUser->name}",
            'activity_time' => now(),
        ]);

        return back()->with('success', 'Telecaller updated successfully');
    }

    public function create()
    {
        $courses = \App\Models\Course::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Manager/Leads/Create', [
            'courses'   => $courses->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'store_url' => route('manager.leads.store'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string',
            'phone'     => 'required|string',
            'email'     => 'nullable|email',
            'course_id' => 'nullable|integer|exists:courses,id',
            'source'    => 'nullable|string',
        ]);

        // Normalize phone: strip non-digits, prepend +91 if 10 digits
        $rawPhone = preg_replace('/\D+/', '', $request->phone);
        $phone    = (strlen($rawPhone) === 10) ? '+91' . $rawPhone : '+' . ltrim($rawPhone, '+');

        // Duplicate detection: flag if phone already exists in DB
        $isDuplicate = Lead::where('phone', $phone)->exists();

        $lead = Lead::create([
            'lead_code'    => $this->generateLeadCode(),
            'name'         => $request->name,
            'phone'        => $phone,
            'email'        => $request->email,
            'course_id'    => $request->course_id ?: null,
            'source'       => $request->source ?? 'manual',
            'status'       => LeadDefaults::defaultStatus(),
            'assigned_by'  => Auth::id(),
            'is_duplicate' => $isDuplicate,
        ]);

        if ($isDuplicate) {
            AuditLogService::log('lead.duplicate_detected', 'Lead', $lead->id, [], ['phone' => $phone]);
        }

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'note',
            'description'   => 'Lead added manually by ' . Auth::user()->name,
            'activity_time' => now(),
        ]);

        $successMsg = $isDuplicate
            ? 'Lead Added — Warning: this phone number already exists in the system (flagged as duplicate).'
            : 'Lead Added Successfully';

        return redirect()->route('manager.leads')->with('success', $successMsg);
    }
    private function generateLeadCode()
    {
        $prefix = 'SMIT'; // later dynamic

        $lastLead = Lead::latest('id')->first();

        $nextNumber = $lastLead ? $lastLead->id + 1 : 1;

        $formattedNumber = str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        return $prefix . '-' . $formattedNumber;
    }
    public function duplicates(Request $request)
    {
        $managerId = Auth::id();

        // Find phones that appear more than once within this manager's leads
        $dupPhones = Lead::where('assigned_by', $managerId)
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        // Find emails that appear more than once within this manager's leads
        $dupEmails = Lead::where('assigned_by', $managerId)
            ->select('email')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        $query = Lead::with(['assignedUser', 'followups'])
            ->where('assigned_by', $managerId)
            ->where(function ($q) use ($dupPhones, $dupEmails) {
                $q->whereIn('phone', $dupPhones);
                if ($dupEmails->isNotEmpty()) {
                    $q->orWhereIn('email', $dupEmails);
                }
            });

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('lead_code', 'like', '%' . $request->search . '%')
                    ->orWhere('name', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $leads = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $leadsData = $leads->through(fn($lead) => [
            'id'            => $lead->id,
            'encrypted_id'  => encrypt($lead->id),
            'lead_code'     => $lead->lead_code,
            'name'          => $lead->name,
            'phone'         => $lead->phone,
            'email'         => $lead->email,
            'source'        => $lead->source,
            'status'        => $lead->status,
            'assigned_user' => $lead->assignedUser?->name,
            'days_aged'     => $lead->days_aged,
            'created_at'    => $lead->created_at->format('d M Y'),
        ]);

        return Inertia::render('Manager/Leads/Duplicates', [
            'leads'   => $leadsData,
            'filters' => request()->only(['search']),
        ]);
    }

    public function show($id)
    {
        try {
            $id = decrypt($id);
        } catch (\Exception $e) {
            abort(404);
        }

        $lead = Lead::with([
            'assignedUser',
            'activities' => function ($q) {
                $q->latest();
            },
            'activities.user'
        ])->findOrFail($id);

        $telecallers = User::where('role', 'telecaller')->get();
        $whatsappMessages = Schema::hasTable('whatsapp_messages')
            ? WhatsAppMessage::where('lead_id', $lead->id)->orderBy('created_at')->get()
            : collect();
        $waTemplateName = Setting::get('meta_whatsapp_template_name', 'welcome_template');

        $encId = encrypt($lead->id);

        return Inertia::render('Manager/Leads/Show', [
            'lead' => [
                'id'            => $lead->id,
                'lead_code'     => $lead->lead_code,
                'name'          => $lead->name,
                'phone'         => $lead->phone,
                'email'         => $lead->email,
                'course'        => $lead->course,
                'status'        => $lead->status,
                'assigned_to'   => $lead->assigned_to,
                'assigned_user' => $lead->assignedUser?->name,
                'is_duplicate'  => $lead->is_duplicate,
                'activities'    => $lead->activities->map(fn($a) => [
                    'id'          => $a->id,
                    'type'        => $a->type,
                    'description' => $a->description,
                    'user'        => $a->user?->name,
                    'time'        => $a->created_at->diffForHumans(),
                ])->values(),
            ],
            'telecallers'       => $telecallers->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'whatsapp_messages' => $whatsappMessages->map(fn($m) => [
                'id'             => $m->id,
                'direction'      => $m->direction,
                'body'           => $m->message_body,
                'time'           => $m->created_at?->format('h:i A'),
                'status'         => data_get($m->meta_data, 'meta_status', 'sent'),
                'media_type'     => $m->media_type,
                'media_url'      => $m->media_url ? asset('storage/' . $m->media_url) : null,
                'media_filename' => $m->media_filename,
            ])->values(),
            'wa_template_name'  => $waTemplateName,
            'urls' => [
                'assign'         => route('manager.assign', $encId),
                'change_status'  => route('manager.leads.changeStatus', $encId),
                'add_note'       => route('manager.leads.addNote', $encId),
                'wa_store'       => route('manager.leads.whatsapp.store', $encId),
                'wa_template'    => route('manager.leads.whatsapp.template', $encId),
                'wa_media'       => route('manager.leads.whatsapp.media', $encId),
                'wa_fetch'       => route('manager.leads.whatsapp.fetch', $encId),
                'call_outcome'   => route('call.outcome'),
                'back'           => route('manager.leads'),
            ],
        ]);
    }

    public function changeStatus(Request $request, $encryptedId)
    {
        $id = decrypt($encryptedId);

        $lead = Lead::findOrFail($id);

        $request->validate([
            'status' => 'required|in:new,assigned,contacted,interested,not_interested,converted,follow_up',
        ]);


        // Update lead status
        $lead->status = $request->status;

        // If followup selected
        if ($request->status === 'follow_up') {

            $request->validate([
                'next_followup' => 'required|date',
                'followup_time' => 'nullable|date_format:H:i',
                'remarks'       => 'nullable|string',
            ]);

            Followup::create([
                'lead_id'       => $lead->id,
                'user_id'       => Auth::id(),
                'remarks'       => $request->remarks ?? '',
                'next_followup' => $request->next_followup,
                'followup_time' => $request->followup_time,
            ]);

            $timeStr = $request->followup_time ? ' at ' . date('h:i A', strtotime($request->followup_time)) : '';
            LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => Auth::id(),
                'type'          => 'followup',
                'description'   => "Follow-up scheduled for {$request->next_followup}{$timeStr}",
                'activity_time' => now(),
            ]);
        }

        $lead->save();

        // Status activity
        LeadActivity::create([
            'lead_id'     => $lead->id,
            'user_id'     => Auth::id(),
            'type'        => 'status_change',
            'description' => "Status changed to " . ucfirst($request->status),
            'activity_time' => now(),
        ]);

        AuditLogService::log('lead.status_changed', 'Lead', $lead->id, ['status' => $lead->getOriginal('status')], ['status' => $request->status]);

        return back()->with('success', 'Status updated successfully');
    }


    public function addNote($encryptedId)
    {
        $id = decrypt($encryptedId);

        $lead = Lead::findOrFail($id);

        request()->validate([
            'note' => 'required|string'
        ]);

        LeadActivity::create([
            'lead_id'     => $lead->id,
            'user_id'     => Auth::id(),
            'type'        => 'note',
            'description' => request('note'),
            'meta_data'   => null,
            'activity_time' => now(),
        ]);

        return back()->with('success', 'Note added successfully');
    }

    // ─── Pipeline (Kanban Board) ────────────────────────────────────────────────

    public function pipeline(Request $request)
    {
        $managerId = Auth::id();

        $statuses = ['new', 'assigned', 'contacted', 'interested', 'follow_up', 'not_interested', 'converted'];

        $base = Lead::with(['assignedUser', 'enrolledCourse', 'followups'])
            ->where('assigned_by', $managerId);

        if ($request->search) {
            $s = $request->search;
            $base->where(fn($q) => $q
                ->where('lead_code', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%")
                ->orWhere('phone', 'like', "%$s%")
            );
        }

        if ($request->telecaller) {
            $base->where('assigned_to', $request->telecaller);
        }

        if ($request->date_range) {
            if ($request->date_range === 'today') {
                $base->whereDate('created_at', today());
            } else {
                $base->whereDate('created_at', '>=', now()->subDays((int) $request->date_range));
            }
        }

        // Single GROUP BY query for column totals instead of 7 separate count() calls
        $rawTotals = Lead::where('assigned_by', $managerId)
            ->whereIn('status', $statuses)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $columns      = [];
        $columnTotals = array_fill_keys($statuses, 0);
        foreach ($rawTotals as $s => $cnt) {
            $columnTotals[$s] = (int) $cnt;
        }
        foreach ($statuses as $status) {
            $columns[$status] = (clone $base)->where('status', $status)->latest()->limit(20)->get();
        }

        $telecallers = User::where('role', 'telecaller')
            ->where('status', 1)
            ->whereIn('id', Lead::where('assigned_by', $managerId)->whereNotNull('assigned_to')->distinct()->pluck('assigned_to'))
            ->get();

        $mapLead = fn($lead) => [
            'id'            => $lead->id,
            'encrypted_id'  => encrypt($lead->id),
            'lead_code'     => $lead->lead_code,
            'name'          => $lead->name,
            'phone'         => $lead->phone,
            'course'        => $lead->course,
            'assigned_user' => $lead->assignedUser?->name,
            'is_duplicate'  => $lead->is_duplicate,
            'days_aged'     => $lead->days_aged,
            'next_followup' => $lead->followups->sortByDesc('next_followup')->first()?->next_followup,
            'created_at'    => $lead->created_at->format('d M'),
        ];

        $columnsData = [];
        foreach ($statuses as $status) {
            $columnsData[$status] = $columns[$status]->map($mapLead)->values();
        }

        return Inertia::render('Manager/Leads/Pipeline', [
            'columns'      => $columnsData,
            'columnTotals' => $columnTotals,
            'telecallers'  => $telecallers->map(fn($t) => [
                'id'           => $t->id,
                'encrypted_id' => encrypt($t->id),
                'name'         => $t->name,
            ])->values(),
            'filters' => request()->only(['search', 'telecaller', 'date_range']),
            'urls'    => [
                'pipeline_status' => route('manager.leads.pipeline.status'),
                'pipeline_more'   => route('manager.leads.pipeline.more'),
            ],
        ]);
    }

    public function pipelineMore(Request $request)
    {
        $request->validate([
            'status' => 'required|in:new,assigned,contacted,interested,follow_up,not_interested,converted',
            'offset' => 'required|integer|min:0',
        ]);

        $managerId = Auth::id();

        $base = Lead::with(['assignedUser', 'enrolledCourse', 'followups'])
            ->where('assigned_by', $managerId);

        if ($request->search) {
            $s = $request->search;
            $base->where(fn($q) => $q
                ->where('lead_code', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%")
                ->orWhere('phone', 'like', "%$s%")
            );
        }
        if ($request->telecaller) {
            $base->where('assigned_to', $request->telecaller);
        }
        if ($request->date_range) {
            if ($request->date_range === 'today') {
                $base->whereDate('created_at', today());
            } else {
                $base->whereDate('created_at', '>=', now()->subDays((int) $request->date_range));
            }
        }

        $statusBase = (clone $base)->where('status', $request->status);
        $total  = (clone $statusBase)->count();
        $leads  = $statusBase->latest()->limit(20)->offset((int) $request->offset)->get();
        $loaded = (int) $request->offset + $leads->count();

        $leadsData = $leads->map(fn($lead) => [
            'id'            => $lead->id,
            'encrypted_id'  => encrypt($lead->id),
            'lead_code'     => $lead->lead_code,
            'name'          => $lead->name,
            'phone'         => $lead->phone,
            'course'        => $lead->course,
            'assigned_user' => $lead->assignedUser?->name,
            'is_duplicate'  => $lead->is_duplicate,
            'days_aged'     => $lead->days_aged,
            'next_followup' => $lead->followups->sortByDesc('next_followup')->first()?->next_followup,
            'created_at'    => $lead->created_at->format('d M'),
        ])->values();

        return response()->json([
            'leads'    => $leadsData,
            'has_more' => $loaded < $total,
            'loaded'   => $loaded,
            'total'    => $total,
        ]);
    }

    public function updatePipelineStatus(Request $request)
    {
        $request->validate([
            'lead_id'       => 'required|string',
            'status'        => 'required|in:new,assigned,contacted,interested,not_interested,converted,follow_up',
            'telecaller_id' => 'nullable|string',
        ]);

        if ($request->status === 'assigned' && !$request->telecaller_id) {
            return response()->json(['success' => false, 'message' => 'Please select a telecaller.'], 422);
        }

        try {
            $id = decrypt($request->lead_id);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid lead.'], 422);
        }

        $lead      = Lead::where('assigned_by', Auth::id())->findOrFail($id);
        $oldStatus = $lead->status;

        $telecaller = null;
        if ($request->status === 'assigned' && $request->telecaller_id) {
            try {
                $telecallerId = decrypt($request->telecaller_id);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Invalid telecaller.'], 422);
            }

            $telecaller    = User::findOrFail($telecallerId);
            $oldAssignedTo = $lead->assigned_to;
            $lead->assigned_to = $telecaller->id;

            $telecaller->notify(new LeadAssignmentNotification(
                title:   'Lead Assigned by Manager',
                message: 'Lead ' . ($lead->lead_code ?? ('#' . $lead->id)) . ' assigned to you.',
                link:    route('telecaller.leads.show', encrypt($lead->id)),
                meta:    ['type' => 'lead_assignment', 'lead_id' => $lead->id]
            ));

            LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => Auth::id(),
                'type'          => 'assignment',
                'title'         => $oldAssignedTo ? 'Lead Reassigned' : 'Lead Assigned',
                'description'   => ($oldAssignedTo ? 'Reassigned' : 'Assigned') . ' to ' . $telecaller->name . ' via Pipeline',
                'activity_time' => now(),
            ]);

            AuditLogService::log('lead.assigned', 'Lead', $lead->id,
                ['assigned_to' => $oldAssignedTo],
                ['assigned_to' => $telecaller->id, 'source' => 'pipeline']
            );
        }

        $lead->status = $request->status;
        $lead->save();

        LeadActivity::create([
            'lead_id'       => $lead->id,
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

        $response = ['success' => true, 'status' => $request->status];
        if ($telecaller) {
            $response['telecaller_name'] = $telecaller->name;
        }

        return response()->json($response);
    }

    public function logCall(Request $request)
    {
        $leadId = decrypt($request->lead_id);
        $lead = Lead::findOrFail($leadId);

        $lead->activities()->create([
            'user_id' => Auth::id(),
            'type' => 'call',
            'title' => 'Outbound Call',
            'description' => "Call made to {$lead->phone}",
        ]);

        return response()->json(['success' => true]);
    }

    public function storeWhatsappMessage(Request $request, $encryptedId)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $leadId = decrypt($encryptedId);
        $lead = Lead::findOrFail($leadId);

        $whatsappMessage = WhatsAppMessage::create([
            'lead_id' => $lead->id,
            'from_number' => (string) $lead->phone,
            'message_body' => $request->message,
            'direction' => 'outbound',
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type' => 'whatsapp',
            'description' => $request->message,
            'meta_data' => [
                'direction' => 'outbound',
                'channel' => 'whatsapp',
            ],
            'activity_time' => now(),
        ]);

        $phone = preg_replace('/\D+/', '', (string) $lead->phone);

        return response()->json([
            'success' => true,
            'message_id' => $whatsappMessage->id,
            'message' => $whatsappMessage->message_body,
            'direction' => $whatsappMessage->direction,
            'time' => optional($whatsappMessage->created_at)->format('H:i'),
            'wa_url' => $phone ? ('https://wa.me/' . $phone . '?text=' . urlencode($whatsappMessage->message_body)) : null,
        ]);
    }
}
