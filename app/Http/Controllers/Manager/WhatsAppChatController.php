<?php

namespace App\Http\Controllers\Manager;

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
     * WhatsApp Chat Hub — list all leads that have messages,
     * optionally open a specific lead conversation.
     */
    public function index(Request $request)
    {
        // All leads that have at least one WhatsApp message, sorted by latest message
        $conversations = Lead::whereHas('whatsappMessages')
            ->with([
                'whatsappMessages' => fn($q) => $q->latest()->limit(1),
                'assignedUser:id,name',
            ])
            ->get()
            ->sortByDesc(fn($lead) => optional($lead->whatsappMessages->first())->created_at)
            ->values();

        // Unread counts (inbound messages not yet read)
        $unreadCounts = WhatsAppMessage::where('direction', 'inbound')
            ->where('is_read', false)
            ->selectRaw('lead_id, count(*) as cnt')
            ->groupBy('lead_id')
            ->pluck('cnt', 'lead_id');

        // Pre-selected lead (when clicking from lead profile or via ?lead=encryptedId)
        $activeLead     = null;
        $activeMessages = collect();

        if ($request->has('lead')) {
            try {
                $leadId     = decrypt($request->lead);
                $activeLead = Lead::findOrFail($leadId);

                $activeMessages = WhatsAppMessage::where('lead_id', $leadId)
                    ->oldest()
                    ->get();

                // Mark inbound messages as read
                WhatsAppMessage::where('lead_id', $leadId)
                    ->where('direction', 'inbound')
                    ->where('is_read', false)
                    ->update(['is_read' => true]);

                // Clear WhatsApp DB notifications for this lead so toasts stop repeating
                /** @var User $user */
                $user = Auth::user();
                $user->unreadNotifications()
                    ->where('type', WhatsAppInboundNotification::class)
                    ->where('data->lead_id', $leadId)
                    ->update(['read_at' => now()]);

                // If this lead doesn't already appear in conversations (no messages), add it
                if ($conversations->where('id', $leadId)->isEmpty()) {
                    $conversations->prepend($activeLead->load('whatsappMessages', 'assignedUser:id,name'));
                }
            } catch (\Throwable) {
                // bad/expired token — ignore
            }
        }

        $conversationData = $conversations->map(fn($lead) => [
            'id'              => $lead->id,
            'encrypted_id'    => encrypt($lead->id),
            'name'            => $lead->name,
            'phone'           => $lead->phone,
            'assigned_user'   => $lead->assignedUser?->name,
            'last_message'    => $lead->whatsappMessages->first()?->message_body,
            'last_message_at' => $lead->whatsappMessages->first()?->created_at?->format('h:i A'),
            'unread_count'    => $unreadCounts->get($lead->id, 0),
        ]);

        $activeLeadData = $activeLead ? [
            'id'           => $activeLead->id,
            'encrypted_id' => encrypt($activeLead->id),
            'name'         => $activeLead->name,
            'phone'        => $activeLead->phone,
        ] : null;

        $activeMessagesData = $activeMessages->map(fn($m) => [
            'id'           => $m->id,
            'message_body' => $m->message_body,
            'direction'    => $m->direction,
            'time'         => $m->created_at?->format('h:i A'),
            'date'         => $m->created_at?->format('d M Y'),
            'status'       => data_get($m->meta_data, 'meta_status', 'sent'),
            'media_url'    => $m->media_url ?? null,
            'media_type'   => $m->media_type ?? null,
        ]);

        return Inertia::render('Manager/WhatsApp/Index', [
            'conversations'   => $conversationData,
            'activeLead'      => $activeLeadData,
            'activeMessages'  => $activeMessagesData,
            'unreadCounts'    => $unreadCounts,
        ]);
    }

    /**
     * JSON — fetch messages for a lead (used by polling).
     * Also marks inbound messages as read.
     */
    public function messages(Request $request, string $encryptedId)
    {
        $leadId = decrypt($encryptedId);
        $lead   = Lead::findOrFail($leadId);

        // Optional: only return messages newer than a given ID
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

        // Mark inbound as read
        WhatsAppMessage::where('lead_id', $leadId)
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Clear WhatsApp DB notifications for this lead so toasts stop repeating
        /** @var User $user */
        $user = Auth::user();
        $user->unreadNotifications()
            ->where('type', WhatsAppInboundNotification::class)
            ->where('data->lead_id', $leadId)
            ->update(['read_at' => now()]);

        $unread = WhatsAppMessage::where('direction', 'inbound')
            ->where('is_read', false)
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
