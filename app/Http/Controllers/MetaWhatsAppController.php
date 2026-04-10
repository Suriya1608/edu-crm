<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignActivity;
use App\Models\CampaignContact;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Notifications\WhatsAppInboundNotification;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MetaWhatsAppController extends Controller
{
    // ──────────────────────────────────────────────
    //  Send a text message from the lead profile page
    //  POST /manager/leads/{encryptedId}/whatsapp
    // ──────────────────────────────────────────────
    public function sendLeadMessage(Request $request, string $encryptedId)
    {
        $request->validate(['message' => 'required|string|max:4096']);

        try {
            $leadId = decrypt($encryptedId);
        } catch (DecryptException) {
            abort(404);
        }
        $lead = Lead::findOrFail($leadId);

        if (Auth::check() && Auth::user()->role === 'telecaller'
            && (int) $lead->assigned_to !== (int) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to message this lead.',
            ], 403);
        }

        return $this->sendToLead($lead, (string) $request->message);
    }

    // ──────────────────────────────────────────────
    //  Send an approved WhatsApp template to a lead
    //  POST /manager/leads/{encryptedId}/whatsapp/template
    //  POST /telecaller/leads/{encryptedId}/whatsapp/template
    // ──────────────────────────────────────────────
    public function sendLeadTemplate(Request $request, string $encryptedId)
    {
        $request->validate([
            'template_name' => 'required|string|max:100',
            'params'        => 'nullable|array',
            'params.*'      => 'nullable|string|max:255',
            'display_body'  => 'nullable|string|max:1024',
        ]);

        try {
            $leadId = decrypt($encryptedId);
        } catch (DecryptException) {
            abort(404);
        }
        $lead = Lead::findOrFail($leadId);

        if (Auth::check() && Auth::user()->role === 'telecaller'
            && (int) $lead->assigned_to !== (int) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to message this lead.',
            ], 403);
        }

        $token         = $this->accessToken();
        $phoneNumberId = $this->phoneNumberId();

        if (! $token || ! $phoneNumberId) {
            return response()->json(['success' => false, 'message' => 'WhatsApp not configured.'], 422);
        }

        $to           = $this->normalizePhone((string) $lead->phone);
        $templateName = $request->input('template_name');
        $params       = $request->input('params', []);

        $templateLanguage = (string) Setting::get(
            'meta_whatsapp_template_language',
            config('services.meta.whatsapp_default_template_language', 'en')
        );

        $template = [
            'name'     => $templateName,
            'language' => ['code' => $templateLanguage],
        ];

        if (! empty($params)) {
            $template['components'] = [[
                'type'       => 'body',
                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string) $p], $params),
            ]];
        }

        try {
            $http = Http::withToken($token)->timeout(15)->asJson();
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post(
                "https://graph.facebook.com/{$this->graphApiVersion()}/{$phoneNumberId}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $to,
                    'type'              => 'template',
                    'template'          => $template,
                ]
            );

            if (! $response->successful()) {
                $errCode = $response->json('error.code');
                $err     = $response->json('error.message', 'Template send failed');
                Log::error('MetaWA sendLeadTemplate failed', ['error' => $err, 'code' => $errCode, 'template' => $templateName]);

                if ($errCode === 190 || str_contains(strtolower($err), 'token')) {
                    return response()->json(['success' => false, 'message' => 'Meta token expired — update in Admin → Settings → WhatsApp.'], 422);
                }
                return response()->json(['success' => false, 'message' => 'Meta API: ' . $err], 422);
            }

            $metaMessageId = $response->json('messages.0.id');

        } catch (\Throwable $e) {
            Log::error('MetaWA sendLeadTemplate exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $displayBody = $request->input('display_body')
            ?: (! empty($params) ? implode(' ', $params) : '📋 Template: ' . $templateName);

        $row = [
            'lead_id'             => $lead->id,
            'from_number'         => $phoneNumberId,
            'message_body'        => $displayBody,
            'direction'           => 'outbound',
            'provider_message_id' => $metaMessageId,
            'provider'            => 'meta',
            'sent_at'             => now(),
            'meta_data'           => ['meta_status' => 'sent', 'to' => $to, 'template' => $templateName],
        ];

        if (Schema::hasColumn('whatsapp_messages', 'message')) {
            $row['message'] = $displayBody;
        }

        $saved = WhatsAppMessage::create($row);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'whatsapp',
            'description'   => "Template sent: {$templateName}",
            'meta_data'     => ['direction' => 'outbound', 'template' => $templateName,
                                'message_id' => $metaMessageId],
            'activity_time' => now(),
        ]);

        return response()->json([
            'success'    => true,
            'message_id' => $saved->id,
            'message'    => $saved->message_body,
            'direction'  => 'outbound',
            'time'       => optional($saved->created_at)->format('h:i A'),
            'status'     => 'sent',
        ]);
    }

    // ──────────────────────────────────────────────
    //  Send a media file (image / document / audio / video)
    //  POST /manager/leads/{encryptedId}/whatsapp/media
    // ──────────────────────────────────────────────
    public function sendMedia(Request $request, string $encryptedId)
    {
        $request->validate([
            'file'    => 'required|file|max:20480', // 20 MB
            'caption' => 'nullable|string|max:1024',
        ]);

        try {
            $leadId = decrypt($encryptedId);
        } catch (DecryptException) {
            abort(404);
        }
        $lead = Lead::findOrFail($leadId);

        if (Auth::check() && Auth::user()->role === 'telecaller'
            && (int) $lead->assigned_to !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }

        $token         = $this->accessToken();
        $phoneNumberId = $this->phoneNumberId();

        if (! $token || ! $phoneNumberId) {
            return response()->json(['success' => false, 'message' => 'WhatsApp not configured.'], 422);
        }

        $file         = $request->file('file');
        $mimeType     = $file->getMimeType();
        $originalName = $file->getClientOriginalName();
        $waType       = $this->mimeToWaType($mimeType);
        $to           = $this->normalizePhone((string) $lead->phone);
        $caption      = (string) $request->input('caption', '');

        try {
            // Helper: build a fresh authenticated HTTP client
            $makeHttp = function () use ($token) {
                $h = Http::withToken($token)->timeout(30);
                if (app()->environment('local')) {
                    $h = $h->withoutVerifying();
                }
                return $h;
            };

            // ── Step 1: Upload file to Meta (multipart) ──
            // NOTE: asMultipart() / attach() mutate the PendingRequest instance in
            // Laravel 12, so we MUST use a fresh client for the subsequent JSON send.
            $uploadResp = $makeHttp()
                ->asMultipart()
                ->attach('messaging_product', 'whatsapp')
                ->attach('type', $mimeType)
                ->attach('file', $file->get(), $originalName, ['Content-Type' => $mimeType])
                ->post("https://graph.facebook.com/{$this->graphApiVersion()}/{$phoneNumberId}/media");

            if (! $uploadResp->successful()) {
                $errCode = $uploadResp->json('error.code');
                $err     = $uploadResp->json('error.message', 'Media upload failed');
                Log::error('Meta WA media upload failed', ['error' => $err, 'code' => $errCode, 'body' => $uploadResp->body()]);
                if ($errCode === 190 || str_contains(strtolower($err), 'auth') || str_contains(strtolower($err), 'token')) {
                    return response()->json(['success' => false, 'message' => 'Meta token expired — update it in Admin → Settings → WhatsApp.'], 422);
                }
                if (str_contains($err, 'does not exist') || str_contains($err, 'missing permissions') || $errCode === 100) {
                    return response()->json(['success' => false, 'message' => 'Phone Number ID is wrong or your token lacks permission for it — check Admin → Settings → WhatsApp.'], 422);
                }
                return response()->json(['success' => false, 'message' => 'Meta upload: ' . $err], 422);
            }

            $mediaId = $uploadResp->json('id');

            // ── Step 2: Send the message (fresh JSON client) ──
            $mediaPayload = ['id' => $mediaId];
            if ($caption)               $mediaPayload['caption']  = $caption;
            if ($waType === 'document') $mediaPayload['filename'] = $originalName;

            $msgBody = [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $to,
                'type'              => $waType,
                $waType             => $mediaPayload,
            ];

            $sendResp = $makeHttp()->asJson()
                ->post("https://graph.facebook.com/{$this->graphApiVersion()}/{$phoneNumberId}/messages", $msgBody);

            if (! $sendResp->successful()) {
                $err = $sendResp->json('error.message', 'Send failed');
                Log::error('Meta WA media send failed', ['error' => $err, 'body' => $sendResp->body()]);
                return response()->json(['success' => false, 'message' => 'Meta send: ' . $err], 422);
            }

            $metaMessageId = $sendResp->json('messages.0.id');

        } catch (\Throwable $e) {
            Log::error('Meta WA media exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // Store file locally for our own display
        $storedPath  = $file->store("whatsapp/{$lead->id}", 'public');
        $messageBody = $caption ?: "📎 {$originalName}";

        $row = [
            'lead_id'             => $lead->id,
            'from_number'         => $phoneNumberId,
            'message_body'        => $messageBody,
            'direction'           => 'outbound',
            'provider_message_id' => $metaMessageId,
            'sent_at'             => now(),
            'meta_data'           => ['meta_status' => 'sent', 'to' => $to],
            'media_type'          => $waType,
            'media_url'           => $storedPath,
            'media_filename'      => $originalName,
        ];

        if (Schema::hasColumn('whatsapp_messages', 'message')) {
            $row['message'] = $messageBody;
        }

        $saved = WhatsAppMessage::create($row);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'whatsapp',
            'description'   => "Media sent: {$originalName}",
            'meta_data'     => ['direction' => 'outbound', 'media_type' => $waType],
            'activity_time' => now(),
        ]);

        return response()->json([
            'success'        => true,
            'message_id'     => $saved->id,
            'message'        => $saved->message_body,
            'direction'      => 'outbound',
            'time'           => optional($saved->created_at)->format('h:i A'),
            'media_type'     => $waType,
            'media_url'      => asset('storage/' . $storedPath),
            'media_filename' => $originalName,
            'status'         => 'sent',
        ]);
    }

    // ──────────────────────────────────────────────
    //  Fetch messages for polling (inbound + status)
    //  GET /manager/leads/{encryptedId}/whatsapp/messages?after={id}
    // ──────────────────────────────────────────────
    public function fetchMessages(Request $request, string $encryptedId)
    {
        try {
            $leadId = decrypt($encryptedId);
        } catch (DecryptException) {
            abort(404);
        }
        $afterId = (int) $request->query('after', 0);

        $messages = WhatsAppMessage::where('lead_id', $leadId)
            ->when($afterId > 0, fn($q) => $q->where('id', '>', $afterId))
            ->oldest()
            ->get()
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
            ]);

        // Mark inbound messages as read and clear DB notifications so toasts stop repeating
        WhatsAppMessage::where('lead_id', $leadId)
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $authUser?->unreadNotifications()
            ->where('type', WhatsAppInboundNotification::class)
            ->where('data->lead_id', $leadId)
            ->update(['read_at' => now()]);

        // Status updates for already-shown outbound messages
        $statuses = WhatsAppMessage::where('lead_id', $leadId)
            ->where('direction', 'outbound')
            ->when($afterId > 0, fn($q) => $q->where('id', '<=', $afterId))
            ->get(['id', 'meta_data'])
            ->mapWithKeys(fn($m) => [
                $m->id => data_get($m->meta_data, 'meta_status', 'sent'),
            ]);

        return response()->json([
            'ok'       => true,
            'messages' => $messages,
            'statuses' => $statuses,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Campaign Contact: Send text via Meta API
    //  POST /manager/campaigns/{cId}/contacts/{ctId}/whatsapp
    //  POST /telecaller/campaigns/{cId}/contacts/{ctId}/whatsapp/send
    // ──────────────────────────────────────────────
    public function sendCampaignContactMessage(Request $request, string $campaignId, string $contactId)
    {
        $request->validate(['message' => 'required|string|max:4096']);

        try {
            $cId  = decrypt($campaignId);
            $ctId = decrypt($contactId);
        } catch (DecryptException) {
            abort(404);
        }
        $contact = CampaignContact::where('campaign_id', $cId)->findOrFail($ctId);

        if (Auth::check() && Auth::user()->role === 'telecaller'
            && (int) $contact->assigned_to !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        return $this->sendToPhone($contact, (string) $request->message);
    }

    // ──────────────────────────────────────────────
    //  Campaign Contact: Send media via Meta API
    //  POST /manager/campaigns/{cId}/contacts/{ctId}/whatsapp/media
    // ──────────────────────────────────────────────
    public function sendCampaignContactMedia(Request $request, string $campaignId, string $contactId)
    {
        $request->validate([
            'file'    => 'required|file|max:20480',
            'caption' => 'nullable|string|max:1024',
        ]);

        try {
            $cId  = decrypt($campaignId);
            $ctId = decrypt($contactId);
        } catch (DecryptException) {
            abort(404);
        }
        $contact = CampaignContact::where('campaign_id', $cId)->findOrFail($ctId);

        if (Auth::check() && Auth::user()->role === 'telecaller'
            && (int) $contact->assigned_to !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $token         = $this->accessToken();
        $phoneNumberId = $this->phoneNumberId();

        if (! $token || ! $phoneNumberId) {
            return response()->json(['success' => false, 'message' => 'WhatsApp not configured.'], 422);
        }

        $file         = $request->file('file');
        $mimeType     = $file->getMimeType();
        $originalName = $file->getClientOriginalName();
        $waType       = $this->mimeToWaType($mimeType);
        $to           = $this->normalizePhone((string) $contact->phone);
        $caption      = (string) $request->input('caption', '');

        try {
            $makeHttp = function () use ($token) {
                $h = Http::withToken($token)->timeout(30);
                if (app()->environment('local')) {
                    $h = $h->withoutVerifying();
                }
                return $h;
            };

            $uploadResp = $makeHttp()
                ->asMultipart()
                ->attach('messaging_product', 'whatsapp')
                ->attach('type', $mimeType)
                ->attach('file', $file->get(), $originalName, ['Content-Type' => $mimeType])
                ->post("https://graph.facebook.com/{$this->graphApiVersion()}/{$phoneNumberId}/media");

            if (! $uploadResp->successful()) {
                $errCode = $uploadResp->json('error.code');
                $err     = $uploadResp->json('error.message', 'Media upload failed');
                if ($errCode === 190 || str_contains(strtolower($err), 'token')) {
                    return response()->json(['success' => false, 'message' => 'Meta token expired — update in Admin → Settings → WhatsApp.'], 422);
                }
                return response()->json(['success' => false, 'message' => 'Meta upload: ' . $err], 422);
            }

            $mediaId      = $uploadResp->json('id');
            $mediaPayload = ['id' => $mediaId];
            if ($caption)               $mediaPayload['caption']  = $caption;
            if ($waType === 'document') $mediaPayload['filename'] = $originalName;

            $sendResp = $makeHttp()->asJson()->post("https://graph.facebook.com/{$this->graphApiVersion()}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $to,
                'type'              => $waType,
                $waType             => $mediaPayload,
            ]);

            if (! $sendResp->successful()) {
                $err = $sendResp->json('error.message', 'Send failed');
                return response()->json(['success' => false, 'message' => 'Meta send: ' . $err], 422);
            }

            $metaMessageId = $sendResp->json('messages.0.id');

        } catch (\Throwable $e) {
            Log::error('Meta WA campaign media exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $storedPath  = $file->store("whatsapp/campaign/{$contact->id}", 'public');
        $messageBody = $caption ?: "📎 {$originalName}";

        $row = [
            'campaign_contact_id' => $contact->id,
            'from_number'         => $phoneNumberId,
            'message_body'        => $messageBody,
            'direction'           => 'outbound',
            'provider_message_id' => $metaMessageId,
            'sent_at'             => now(),
            'meta_data'           => ['meta_status' => 'sent', 'to' => $to],
            'media_type'          => $waType,
            'media_url'           => $storedPath,
            'media_filename'      => $originalName,
        ];

        if (Schema::hasColumn('whatsapp_messages', 'message')) {
            $row['message'] = $messageBody;
        }

        $saved = WhatsAppMessage::create($row);

        CampaignActivity::create([
            'campaign_contact_id' => $contact->id,
            'type'                => 'whatsapp',
            'description'         => "Media sent: {$originalName}",
            'meta'                => ['direction' => 'outbound', 'media_type' => $waType],
            'created_by'          => Auth::id(),
        ]);

        return response()->json([
            'success'        => true,
            'message_id'     => $saved->id,
            'message'        => $saved->message_body,
            'direction'      => 'outbound',
            'time'           => optional($saved->created_at)->format('h:i A'),
            'media_type'     => $waType,
            'media_url'      => asset('storage/' . $storedPath),
            'media_filename' => $originalName,
            'status'         => 'sent',
        ]);
    }

    // ──────────────────────────────────────────────
    //  Campaign Contact: Fetch messages for polling
    //  GET /manager/campaigns/{cId}/contacts/{ctId}/whatsapp/messages
    // ──────────────────────────────────────────────
    public function fetchCampaignContactMessages(Request $request, string $campaignId, string $contactId)
    {
        try {
            $cId  = decrypt($campaignId);
            $ctId = decrypt($contactId);
        } catch (DecryptException) {
            abort(404);
        }
        $contact = CampaignContact::where('campaign_id', $cId)->findOrFail($ctId);

        $afterId  = (int) $request->query('after', 0);

        $messages = WhatsAppMessage::where('campaign_contact_id', $contact->id)
            ->when($afterId > 0, fn($q) => $q->where('id', '>', $afterId))
            ->oldest()
            ->get()
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
            ]);

        $statuses = WhatsAppMessage::where('campaign_contact_id', $contact->id)
            ->where('direction', 'outbound')
            ->when($afterId > 0, fn($q) => $q->where('id', '<=', $afterId))
            ->get(['id', 'meta_data'])
            ->mapWithKeys(fn($m) => [
                $m->id => data_get($m->meta_data, 'meta_status', 'sent'),
            ]);

        return response()->json([
            'ok'       => true,
            'messages' => $messages,
            'statuses' => $statuses,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Core: send text to a Campaign Contact (provider-agnostic)
    // ──────────────────────────────────────────────
    private function sendToPhone(CampaignContact $contact, string $messageBody)
    {
        /** @var WhatsAppService $wa */
        $wa = app(WhatsAppService::class);

        if (! $wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp is not configured. Please check Admin → Settings → WhatsApp.',
            ], 422);
        }

        $to = $this->normalizePhone((string) $contact->phone);

        $inbound24h = WhatsAppMessage::where('campaign_contact_id', $contact->id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        $result = $wa->send($to, $messageBody, $inbound24h, (string) $contact->name);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        $row = [
            'campaign_contact_id' => $contact->id,
            'from_number'         => $this->phoneNumberId(),
            'message_body'        => $messageBody,
            'direction'           => 'outbound',
            'provider_message_id' => $result['provider_message_id'],
            'provider'            => $result['provider'],
            'sent_at'             => now(),
            'meta_data'           => ['meta_status' => 'sent', 'to' => $to],
        ];

        if (Schema::hasColumn('whatsapp_messages', 'message')) {
            $row['message'] = $messageBody;
        }

        $saved = WhatsAppMessage::create($row);

        CampaignActivity::create([
            'campaign_contact_id' => $contact->id,
            'type'                => 'whatsapp',
            'description'         => $messageBody,
            'meta'                => ['direction' => 'outbound', 'provider' => $result['provider'],
                                      'message_id' => $result['provider_message_id']],
            'created_by'          => Auth::id(),
        ]);

        return response()->json([
            'success'    => true,
            'message_id' => $saved->id,
            'message'    => $saved->message_body,
            'direction'  => $saved->direction,
            'time'       => optional($saved->created_at)->format('h:i A'),
            'status'     => 'sent',
        ]);
    }

    // ──────────────────────────────────────────────
    //  Meta Webhook: GET = verification, POST = events
    // ──────────────────────────────────────────────
    public function webhook(Request $request)
    {
        if ($request->isMethod('GET')) {
            $mode      = $request->query('hub_mode');
            $token     = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            Log::info('Meta webhook: verification attempt', ['mode' => $mode, 'token_match' => $token === $this->verifyToken()]);

            if ($mode === 'subscribe' && $token === $this->verifyToken()) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        Log::info('Meta webhook: POST received', ['object' => $request->input('object'), 'ip' => $request->ip()]);

        // Verify X-Hub-Signature-256 to ensure the request came from Meta
        $secret = $this->appSecret();
        if ($secret) {
            $signature = $request->header('X-Hub-Signature-256', '');
            $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (! hash_equals($expected, $signature)) {
                Log::warning('Meta webhook: signature mismatch — possible spoofed request');
                return response('Unauthorized', 401);
            }
        }

        $payload = $request->all();

        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            Log::info('Meta webhook: ignored non-whatsapp object', ['object' => $payload['object'] ?? 'none']);
            return response('OK', 200);
        }

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                // ── Inbound messages ──
                foreach (($value['messages'] ?? []) as $message) {
                    $msgType = $message['type'] ?? '';
                    $from    = $message['from'] ?? '';
                    $msgId   = $message['id']   ?? null;

                    $supported = ['text', 'image', 'document', 'audio', 'video', 'sticker'];
                    if (! in_array($msgType, $supported)) {
                        continue;
                    }

                    $lead = $this->resolveLeadByPhone($from);
                    if (! $lead) {
                        Log::warning('Meta webhook: inbound message — no lead matched phone', ['from' => $from]);
                        continue;
                    }

                    // Deduplicate
                    if ($msgId && WhatsAppMessage::where('provider_message_id', $msgId)->exists()) {
                        continue;
                    }

                    $body          = '';
                    $mediaType     = null;
                    $mediaUrl      = null;
                    $mediaFilename = null;

                    if ($msgType === 'text') {
                        $body = $message['text']['body'] ?? '';
                    } else {
                        $mediaData     = $message[$msgType] ?? [];
                        $mediaId       = $mediaData['id']       ?? null;
                        $mediaMime     = $mediaData['mime_type'] ?? 'application/octet-stream';
                        $mediaCaption  = $mediaData['caption']  ?? '';
                        $mediaType     = $msgType;
                        $mediaFilename = $mediaData['filename'] ?? null;
                        $body          = $mediaCaption ?: "📎 " . ($mediaFilename ?: ucfirst($msgType));

                        if ($mediaId) {
                            $mediaUrl = $this->downloadMediaFromMeta($mediaId, $lead->id, $mediaMime);
                        }
                    }

                    $row = [
                        'lead_id'             => $lead->id,
                        'from_number'         => $from,
                        'message_body'        => $body,
                        'direction'           => 'inbound',
                        'provider_message_id' => $msgId,
                        'meta_data'           => ['meta_status' => 'received'],
                        'media_type'          => $mediaType,
                        'media_url'           => $mediaUrl,
                        'media_filename'      => $mediaFilename,
                    ];

                    if (Schema::hasColumn('whatsapp_messages', 'message')) {
                        $row['message'] = $body;
                    }

                    WhatsAppMessage::create($row);

                    LeadActivity::create([
                        'lead_id'       => $lead->id,
                        'user_id'       => null,
                        'type'          => 'whatsapp',
                        'description'   => 'Inbound: ' . $body,
                        'meta_data'     => ['direction' => 'inbound', 'message_id' => $msgId],
                        'activity_time' => now(),
                    ]);

                    // Notify assigned telecaller and assigning manager
                    $this->notifyInbound($lead, $body);
                }

                // ── Delivery / read status updates ──
                foreach (($value['statuses'] ?? []) as $status) {
                    $msgId     = $status['id']     ?? null;
                    $newStatus = $status['status'] ?? null;

                    if (! $msgId || ! $newStatus) {
                        continue;
                    }

                    $msg = WhatsAppMessage::where('provider_message_id', $msgId)->first();
                    if ($msg) {
                        $meta                = $msg->meta_data ?? [];
                        $meta['meta_status'] = $newStatus;
                        $msg->update(['meta_data' => $meta]);
                    }
                }
            }
        }

        return response('OK', 200);
    }

    // ──────────────────────────────────────────────
    //  Core: send text to a Lead (provider-agnostic)
    // ──────────────────────────────────────────────
    private function sendToLead(Lead $lead, string $messageBody)
    {
        /** @var WhatsAppService $wa */
        $wa = app(WhatsAppService::class);

        if (! $wa->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp is not configured. Please check Admin → Settings → WhatsApp.',
            ], 422);
        }

        $to = $this->normalizePhone((string) $lead->phone);

        $inbound24h = WhatsAppMessage::where('lead_id', $lead->id)
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        $result = $wa->send($to, $messageBody, $inbound24h, (string) $lead->name);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        $row = [
            'lead_id'             => $lead->id,
            'from_number'         => $this->phoneNumberId(),
            'message_body'        => $messageBody,
            'direction'           => 'outbound',
            'provider_message_id' => $result['provider_message_id'],
            'provider'            => $result['provider'],
            'sent_at'             => now(),
            'meta_data'           => ['meta_status' => 'sent', 'to' => $to],
        ];

        if (Schema::hasColumn('whatsapp_messages', 'message')) {
            $row['message'] = $messageBody;
        }

        $saved = WhatsAppMessage::create($row);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'whatsapp',
            'description'   => $messageBody,
            'meta_data'     => ['direction' => 'outbound', 'provider' => $result['provider'],
                                'message_id' => $result['provider_message_id']],
            'activity_time' => now(),
        ]);

        return response()->json([
            'success'    => true,
            'message_id' => $saved->id,
            'message'    => $saved->message_body,
            'direction'  => $saved->direction,
            'time'       => optional($saved->created_at)->format('h:i A'),
            'status'     => 'sent',
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /** Notify the assigned telecaller and manager about an inbound WhatsApp message. */
    private function notifyInbound(Lead $lead, string $body): void
    {
        try {
            // Notify assigned telecaller
            if ($lead->assigned_to) {
                $telecaller = User::find($lead->assigned_to);
                if ($telecaller) {
                    $telecaller->notify(new WhatsAppInboundNotification($lead, $body, 'telecaller'));
                }
            }

            // Notify assigning manager (assigned_by)
            if ($lead->assigned_by) {
                $manager = User::find($lead->assigned_by);
                if ($manager && $manager->id !== $lead->assigned_to) {
                    $manager->notify(new WhatsAppInboundNotification($lead, $body, 'manager'));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp inbound notification failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /** Download media from Meta and store locally. Returns storage path or null. */
    private function downloadMediaFromMeta(string $mediaId, int $leadId, string $mimeType): ?string
    {
        try {
            $http = Http::withToken($this->accessToken())->timeout(30);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $meta = $http->get("https://graph.facebook.com/{$this->graphApiVersion()}/{$mediaId}");
            if (! $meta->successful()) {
                return null;
            }

            $downloadUrl = $meta->json('url');
            if (! $downloadUrl) {
                return null;
            }

            $content = $http->get($downloadUrl)->body();

            $ext = match (true) {
                str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg') => 'jpg',
                str_contains($mimeType, 'png')  => 'png',
                str_contains($mimeType, 'gif')  => 'gif',
                str_contains($mimeType, 'webp') => 'webp',
                str_contains($mimeType, 'pdf')  => 'pdf',
                str_contains($mimeType, 'mp4')  => 'mp4',
                str_contains($mimeType, 'ogg')  => 'ogg',
                str_contains($mimeType, 'mp3') || str_contains($mimeType, 'mpeg') => 'mp3',
                default => 'bin',
            };

            $path = "whatsapp/{$leadId}/" . uniqid('', true) . ".{$ext}";
            Storage::disk('public')->put($path, $content);
            return $path;

        } catch (\Throwable $e) {
            Log::error('Meta media download failed', ['media_id' => $mediaId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /** Map MIME type to WhatsApp message type. */
    private function mimeToWaType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        return 'document';
    }

    /** Normalize phone to digits-only (Meta expects no '+' prefix). */
    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        if (strlen($digits) >= 11) {
            return $digits;
        }

        $countryCode = ltrim((string) config('services.meta.default_country_code', '91'), '+');
        if (strlen($digits) === 10) {
            return $countryCode . $digits;
        }

        return $digits;
    }

    /** Match an inbound phone number to an existing lead. */
    private function resolveLeadByPhone(string $phone): ?Lead
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $last10 = substr($digits, -10);

        // Try exact matches first (covers +91XXXXXXXXXX, 91XXXXXXXXXX, and raw formats)
        $candidates = array_values(array_unique(array_filter([
            $phone,
            $digits ? '+' . $digits : null,
            $digits ?: null,
        ])));

        $lead = Lead::query()
            ->whereIn('phone', $candidates)
            ->first();

        if ($lead) {
            return $lead;
        }

        // Fallback: last-10-digit suffix LIKE match — avoids full-table scan
        if (strlen($last10) === 10) {
            return Lead::where('phone_last10', $last10)
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function accessToken(): string
    {
        return (string) Setting::getSecure('meta_whatsapp_token', config('services.meta.whatsapp_token', ''));
    }

    private function appSecret(): string
    {
        return (string) Setting::getSecure('meta_whatsapp_app_secret', config('services.meta.whatsapp_app_secret', ''));
    }

    private function phoneNumberId(): string
    {
        return (string) Setting::get('meta_whatsapp_phone_number_id', config('services.meta.whatsapp_phone_id', ''));
    }

    private function verifyToken(): string
    {
        return (string) Setting::get(
            'meta_whatsapp_webhook_verify_token',
            config('services.meta.whatsapp_verify_token', 'crm_verify_token')
        );
    }

    private function graphApiVersion(): string
    {
        return config('services.meta.graph_api_version', 'v22.0');
    }
}
