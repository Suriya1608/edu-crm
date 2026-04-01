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

        $totalLeads    = Lead::where('assigned_by', $managerId)->count();
        $newLeads      = Lead::where('assigned_by', $managerId)->where('status', 'new')->count();
        $assignedLeads = Lead::where('assigned_by', $managerId)->where('status', 'assigned')->count();
        $followupToday = Followup::whereDate('next_followup', now())->whereIn('lead_id', $myLeadsSubquery)->count();


        return view(
            'manager.leads.index',
            compact(
                'leads',
                'telecallers',
                'totalLeads',
                'newLeads',
                'assignedLeads',
                'followupToday'
            )
        );
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

        return view('manager.leads.create', compact('courses'));
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

        return view('manager.leads.duplicates', compact('leads'));
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
        $provider = Setting::get('primary_call_provider', 'twilio');
        $whatsappMessages = Schema::hasTable('whatsapp_messages')
            ? WhatsAppMessage::where('lead_id', $lead->id)->orderBy('created_at')->get()
            : collect();

        return view('manager.leads.show', compact('lead', 'telecallers', 'provider', 'whatsappMessages'));
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

    // public function callLead($encryptedId)
    // {
    //     $id = decrypt($encryptedId);
    //     $lead = Lead::findOrFail($id);

    //     if (!$lead->assignedUser) {
    //         return back()->with('error', 'Lead not assigned to telecaller');
    //     }

    //     $telecallerNumber = $lead->assignedUser->phone;
    //     $leadNumber = $lead->phone;

    //     $response = Http::withBasicAuth(
    //         env('EXOTEL_SID'),
    //         env('EXOTEL_TOKEN')
    //     )->asForm()->post(
    //         "https://{$this->getExotelSubdomain()}/v1/Accounts/" . env('EXOTEL_SID') . "/Calls/connect.json",
    //         [
    //             'From' => env('EXOTEL_FROM_NUMBER'),
    //             'To'   => $telecallerNumber,
    //             'CallerId' => env('EXOTEL_FROM_NUMBER'),
    //             'CallType' => 'trans',
    //             'TimeLimit' => 3600,
    //             'Url' => route('exotel.connect.callback', [
    //                 'lead' => $leadNumber
    //             ])
    //         ]
    //     );

    //     if ($response->successful()) {

    //         // Store activity
    //         $lead->activities()->create([
    //             'user_id' => auth()->id(),
    //             'type' => 'call',
    //             'description' => "Outbound call initiated to {$leadNumber}",
    //             'activity_time' => now(),
    //         ]);

    //         return back()->with('success', 'Call initiated successfully');
    //     }

    //     return back()->with('error', 'Exotel call failed');
    // }

    // private function getExotelSubdomain()
    // {
    //     return 'api.exotel.com';
    // }


    public function makeCall(Request $request)
    {
        try {

            $leadId = decrypt($request->lead_id);
            $lead = Lead::findOrFail($leadId);

            // TODO: Add Exotel API logic here

            return response()->json([
                'success' => true
            ]);
        } catch (\Exception $e) {

            Log::error('Exotel Call Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
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

        $columns       = [];
        $columnTotals  = [];
        foreach ($statuses as $status) {
            $q = (clone $base)->where('status', $status);
            $columnTotals[$status] = $q->count();
            $columns[$status]      = $q->latest()->limit(20)->get();
        }

        $telecallers = User::where('role', 'telecaller')
            ->where('status', 1)
            ->whereIn('id', Lead::where('assigned_by', $managerId)->whereNotNull('assigned_to')->distinct()->pluck('assigned_to'))
            ->get();

        return view('manager.leads.pipeline', compact('columns', 'columnTotals', 'telecallers'));
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

        $cards = $leads->map(fn($lead) => view('manager.leads._pipeline-card', compact('lead'))->render())->values();

        return response()->json([
            'cards'    => $cards,
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
