<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignActivity;
use App\Models\CampaignContact;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CampaignController extends Controller
{
    // ─── My Campaigns List ────────────────────────────────────────────────────

    public function index()
    {
        $baseQuery = fn() => Campaign::whereHas('contacts', function ($q) {
            $q->where('assigned_to', Auth::id());
        })->where('status', '!=', 'draft');

        $totalStats = [
            'total'    => $baseQuery()->count(),
            'contacts' => \App\Models\CampaignContact::where('assigned_to', Auth::id())->count(),
        ];

        $campaigns = $baseQuery()
            ->withCount(['contacts as my_contacts_count' => function ($q) {
                $q->where('assigned_to', Auth::id());
            }])
            ->latest()
            ->paginate(15)
            ->through(fn($c) => [
                'id'                => $c->id,
                'encrypted_id'      => encrypt($c->id),
                'name'              => $c->name,
                'description'       => $c->description,
                'status'            => $c->status,
                'my_contacts_count' => $c->my_contacts_count,
            ]);

        return Inertia::render('Telecaller/Campaigns/Index', compact('campaigns', 'totalStats'));
    }

    // ─── Campaign Contact List ─────────────────────────────────────────────────

    public function show(Request $request, string $campaignId)
    {
        $campaignId = decrypt($campaignId);
        $campaign   = Campaign::findOrFail($campaignId);

        $query = $campaign->contacts()->where('assigned_to', Auth::id());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $contacts = $query->latest()->paginate(20)->withQueryString()
            ->through(fn($c) => [
                'id'           => $c->id,
                'encrypted_id' => encrypt($c->id),
                'name'         => $c->name,
                'phone'        => $c->phone,
                'course'       => $c->course,
                'city'         => $c->city,
                'status'       => $c->status,
                'next_followup'=> $c->next_followup?->format('Y-m-d'),
                'call_count'   => $c->call_count,
            ]);

        $stats = [
            'total'    => $campaign->contacts()->where('assigned_to', Auth::id())->count(),
            'pending'  => $campaign->contacts()->where('assigned_to', Auth::id())->where('status', 'pending')->count(),
            'called'   => $campaign->contacts()->where('assigned_to', Auth::id())->where('status', '!=', 'pending')->count(),
            'converted'=> $campaign->contacts()->where('assigned_to', Auth::id())->where('status', 'converted')->count(),
        ];

        $filters = $request->only(['search', 'status']);

        return Inertia::render('Telecaller/Campaigns/Show', [
            'campaign' => [
                'id'           => $campaign->id,
                'encrypted_id' => encrypt($campaign->id),
                'name'         => $campaign->name,
                'description'  => $campaign->description,
                'status'       => $campaign->status,
            ],
            'contacts' => $contacts,
            'stats'    => $stats,
            'filters'  => $filters,
        ]);
    }

    // ─── Contact Detail Page ──────────────────────────────────────────────────

    public function contact(string $campaignId, string $contactId)
    {
        $campaignId = decrypt($campaignId);
        $contactId  = decrypt($contactId);

        $campaign = Campaign::findOrFail($campaignId);
        $contact  = CampaignContact::where('campaign_id', $campaignId)
            ->where('assigned_to', Auth::id())
            ->findOrFail($contactId);

        $activities = $contact->activities()->with('createdBy')->latest()->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'type'        => $a->type,
                'description' => $a->description,
                'meta'        => $a->meta,
                'created_by'  => $a->createdBy?->name ?? '—',
                'created_at'  => $a->created_at?->diffForHumans(),
            ]);

        $whatsappMessages = WhatsAppMessage::where('campaign_contact_id', $contact->id)
            ->latest()->limit(50)->get()->reverse()->values()
            ->map(fn($m) => [
                'id'             => $m->id,
                'body'           => $m->message_body,
                'direction'      => $m->direction,
                'time'           => $m->created_at?->format('h:i A'),
                'status'         => data_get($m->meta_data, 'meta_status', 'sent'),
                'media_type'     => $m->media_type,
                'media_url'      => $m->media_url ? asset('storage/' . $m->media_url) : null,
                'media_filename' => $m->media_filename,
            ]);

        $encCampaign = encrypt($campaign->id);
        $encContact  = encrypt($contact->id);

        return Inertia::render('Telecaller/Campaigns/Contact', [
            'campaign' => [
                'id'           => $campaign->id,
                'encrypted_id' => $encCampaign,
                'name'         => $campaign->name,
            ],
            'contact' => [
                'id'            => $contact->id,
                'encrypted_id'  => $encContact,
                'name'          => $contact->name,
                'phone'         => $contact->phone,
                'email'         => $contact->email,
                'course'        => $contact->course,
                'city'          => $contact->city,
                'status'        => $contact->status,
                'call_count'    => $contact->call_count,
                'next_followup' => $contact->next_followup?->format('Y-m-d'),
                'followup_time' => $contact->followup_time,
            ],
            'activities'       => $activities,
            'whatsapp_messages'=> $whatsappMessages,
            'urls' => [
                'wa_store'      => route('telecaller.campaigns.contact.whatsapp.store',  [$encCampaign, $encContact]),
                'wa_media'      => route('telecaller.campaigns.contact.whatsapp.media',  [$encCampaign, $encContact]),
                'wa_fetch'      => route('telecaller.campaigns.contact.whatsapp.fetch',  [$encCampaign, $encContact]),
                'add_note'      => route('telecaller.campaigns.contact.note',            [$encCampaign, $encContact]),
                'change_status' => route('telecaller.campaigns.contact.status',          [$encCampaign, $encContact]),
                'set_followup'  => route('telecaller.campaigns.contact.followup',        [$encCampaign, $encContact]),
                'log_call'      => route('telecaller.campaigns.contact.call',            [$encCampaign, $encContact]),
            ],
        ]);
    }

    // ─── Log a Note ──────────────────────────────────────────────────────────

    public function addNote(Request $request, string $campaignId, string $contactId)
    {
        $campaignId = decrypt($campaignId);
        $contactId  = decrypt($contactId);

        $contact = CampaignContact::where('campaign_id', $campaignId)
            ->where('assigned_to', Auth::id())
            ->findOrFail($contactId);

        $request->validate(['note' => 'required|string|max:1000']);

        CampaignActivity::create([
            'campaign_contact_id' => $contact->id,
            'type'                => 'note',
            'description'         => $request->note,
            'created_by'          => Auth::id(),
        ]);

        return back()->with('success', 'Note added.');
    }

    // ─── Update Contact Status ────────────────────────────────────────────────

    public function updateStatus(Request $request, string $campaignId, string $contactId)
    {
        $campaignId = decrypt($campaignId);
        $contactId  = decrypt($contactId);

        $contact = CampaignContact::where('campaign_id', $campaignId)
            ->where('assigned_to', Auth::id())
            ->findOrFail($contactId);

        $request->validate(['status' => 'required|in:pending,called,interested,not_interested,no_answer,callback,converted']);

        $old = $contact->status;
        $contact->update(['status' => $request->status]);

        if ($old !== $request->status) {
            CampaignActivity::create([
                'campaign_contact_id' => $contact->id,
                'type'                => 'status_change',
                'description'         => "Status changed from {$old} to {$request->status}",
                'meta'                => ['old_status' => $old, 'new_status' => $request->status],
                'created_by'          => Auth::id(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Status updated.');
    }

    // ─── Set Follow-Up Date ───────────────────────────────────────────────────

    public function setFollowup(Request $request, string $campaignId, string $contactId)
    {
        $campaignId = decrypt($campaignId);
        $contactId  = decrypt($contactId);

        $contact = CampaignContact::where('campaign_id', $campaignId)
            ->where('assigned_to', Auth::id())
            ->findOrFail($contactId);

        $request->validate([
            'followup_date' => 'required|date|after_or_equal:today',
            'followup_time' => 'nullable|date_format:H:i',
            'notes'         => 'nullable|string|max:500',
            'status'        => 'nullable|in:pending,called,interested,not_interested,no_answer,callback,converted',
        ]);

        $updates = ['next_followup' => $request->followup_date, 'followup_time' => $request->followup_time];
        if ($request->filled('status')) {
            $updates['status'] = $request->status;
        }
        $contact->update($updates);

        $timeStr = $request->followup_time ? ' at ' . date('h:i A', strtotime($request->followup_time)) : '';
        $desc = 'Follow-up scheduled for ' . $request->followup_date . $timeStr;
        if ($request->filled('notes')) {
            $desc .= ' — ' . $request->notes;
        }

        CampaignActivity::create([
            'campaign_contact_id' => $contact->id,
            'type'                => 'followup_set',
            'description'         => $desc,
            'meta'                => ['date' => $request->followup_date, 'time' => $request->followup_time, 'notes' => $request->notes],
            'created_by'          => Auth::id(),
        ]);

        return back()->with('success', 'Follow-up scheduled.');
    }

    // ─── Log Call ─────────────────────────────────────────────────────────────

    public function logCall(Request $request, string $campaignId, string $contactId)
    {
        $campaignId = decrypt($campaignId);
        $contactId  = decrypt($contactId);

        $contact = CampaignContact::where('campaign_id', $campaignId)
            ->where('assigned_to', Auth::id())
            ->findOrFail($contactId);

        $contact->increment('call_count');

        CampaignActivity::create([
            'campaign_contact_id' => $contact->id,
            'type'                => 'call',
            'description'         => 'Outbound call made',
            'meta'                => [
                'outcome'  => $request->input('outcome', 'called'),
                'duration' => $request->input('duration'),
                'notes'    => $request->input('notes'),
            ],
            'created_by' => Auth::id(),
        ]);

        if ($contact->status === 'pending') {
            $contact->update(['status' => 'called']);
        }

        return response()->json(['ok' => true]);
    }

    // ─── Log WhatsApp Message ─────────────────────────────────────────────────

    public function logWhatsApp(Request $request, string $campaignId, string $contactId)
    {
        $campaignId = decrypt($campaignId);
        $contactId  = decrypt($contactId);

        $contact = CampaignContact::where('campaign_id', $campaignId)
            ->where('assigned_to', Auth::id())
            ->findOrFail($contactId);

        $request->validate(['message' => 'required|string|max:1000']);

        CampaignActivity::create([
            'campaign_contact_id' => $contact->id,
            'type'                => 'whatsapp',
            'description'         => $request->message,
            'created_by'          => Auth::id(),
        ]);

        return back()->with('success', 'WhatsApp message logged.');
    }

}
