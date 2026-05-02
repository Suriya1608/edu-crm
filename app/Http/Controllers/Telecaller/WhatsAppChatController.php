<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Notifications\WhatsAppInboundNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WhatsAppChatController extends Controller
{
    /**
     * WhatsApp Chat Hub — list leads assigned to this telecaller that have messages.
     */
    public function index(Request $request)
    {
        $conversations = Lead::where('assigned_to', Auth::id())
            ->whereHas('whatsappMessages')
            ->with([
                'whatsappMessages' => fn($q) => $q->latest()->limit(1),
            ])
            ->get()
            ->sortByDesc(fn($lead) => optional($lead->whatsappMessages->first())->created_at)
            ->values();

        $unreadCounts = WhatsAppMessage::where('direction', 'inbound')
            ->where('is_read', false)
            ->whereIn('lead_id', $conversations->pluck('id'))
            ->selectRaw('lead_id, count(*) as cnt')
            ->groupBy('lead_id')
            ->pluck('cnt', 'lead_id');

        $activeLead     = null;
        $activeMessages = collect();

        if ($request->has('lead')) {
            try {
                $leadId     = decrypt($request->lead);
                $activeLead = Lead::where('assigned_to', Auth::id())->findOrFail($leadId);

                $activeMessages = WhatsAppMessage::where('lead_id', $leadId)
                    ->oldest()
                    ->get();

                WhatsAppMessage::where('lead_id', $leadId)
                    ->where('direction', 'inbound')
                    ->where('is_read', false)
                    ->update(['is_read' => true]);

                /** @var User $user */
                $user = Auth::user();
                $user->unreadNotifications()
                    ->where('type', WhatsAppInboundNotification::class)
                    ->where('data->lead_id', $leadId)
                    ->update(['read_at' => now()]);

                if ($conversations->where('id', $leadId)->isEmpty()) {
                    $conversations->prepend($activeLead->load('whatsappMessages'));
                }
            } catch (\Throwable) {
                // bad/expired token — ignore
            }
        }

        // Serialize conversations for Inertia
        $conversationData = $conversations->map(fn($lead) => [
            'id'           => $lead->id,
            'encrypted_id' => encrypt($lead->id),
            'name'         => $lead->name,
            'phone'        => $lead->phone,
            'lead_url'     => route('telecaller.leads.show', encrypt($lead->id)),
            'unread_count' => $unreadCounts[$lead->id] ?? 0,
            'last_message' => $lead->whatsappMessages->first() ? [
                'body'      => $lead->whatsappMessages->first()->message_body,
                'direction' => $lead->whatsappMessages->first()->direction,
                'time'      => $lead->whatsappMessages->first()->created_at?->format('h:i A'),
            ] : null,
        ]);

        // Serialize active lead & messages
        $activeLeadData = $activeLead ? [
            'id'           => $activeLead->id,
            'encrypted_id' => encrypt($activeLead->id),
            'name'         => $activeLead->name,
            'phone'        => $activeLead->phone,
            'lead_url'     => route('telecaller.leads.show', encrypt($activeLead->id)),
        ] : null;

        $activeMessagesData = $activeMessages->map(fn($m) => [
            'id'             => $m->id,
            'message_body'   => $m->message_body,
            'direction'      => $m->direction,
            'media_type'     => $m->media_type,
            'media_url'      => $m->media_url ? asset('storage/' . $m->media_url) : null,
            'media_filename' => $m->media_filename,
            'time'           => $m->created_at?->format('h:i A'),
            'date'           => $m->created_at?->format('d M Y'),
            'status'         => data_get($m->meta_data, 'meta_status', 'sent'),
        ])->values();

        return Inertia::render('Telecaller/WhatsApp/Index', [
            'conversations'       => $conversationData,
            'activeLead'          => $activeLeadData,
            'activeMessages'      => $activeMessagesData,
            'sendUrlPattern'      => route('telecaller.leads.whatsapp.store',   '__ID__'),
            'mediaUrlPattern'     => route('telecaller.leads.whatsapp.media',   '__ID__'),
            'messagesUrlPattern'  => route('telecaller.whatsapp.messages',      '__ID__'),
        ]);
    }

    /**
     * JSON — fetch messages for a lead (used by hub polling).
     * Also marks inbound messages as read.
     */
    public function messages(Request $request, string $encryptedId)
    {
        $leadId = decrypt($encryptedId);
        $lead   = Lead::where('assigned_to', Auth::id())->findOrFail($leadId);

        $afterId = (int) $request->query('after', 0);

        $query = WhatsAppMessage::where('lead_id', $leadId)->oldest();
        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query->get()->map(fn($m) => [
            'id'           => $m->id,
            'message_body' => $m->message_body,
            'direction'    => $m->direction,
            'time'         => $m->created_at?->format('h:i A'),
            'date'         => $m->created_at?->format('d M Y'),
            'status'       => data_get($m->meta_data, 'meta_status', 'sent'),
        ]);

        WhatsAppMessage::where('lead_id', $leadId)
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        /** @var User $user */
        $user = Auth::user();
        $user->unreadNotifications()
            ->where('type', WhatsAppInboundNotification::class)
            ->where('data->lead_id', $leadId)
            ->update(['read_at' => now()]);

        $unread = WhatsAppMessage::where('direction', 'inbound')
            ->where('is_read', false)
            ->whereIn('lead_id', Lead::where('assigned_to', Auth::id())->pluck('id'))
            ->selectRaw('lead_id, count(*) as cnt')
            ->groupBy('lead_id')
            ->pluck('cnt', 'lead_id');

        return response()->json([
            'ok'       => true,
            'lead'     => ['id' => $lead->id, 'name' => $lead->name, 'phone' => $lead->phone],
            'messages' => $messages,
            'unread'   => $unread,
        ]);
    }
}
