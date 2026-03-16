<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramConversation;
use App\Models\InstagramMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramController extends Controller
{
    // ── Webhook ───────────────────────────────────────────────────────────────

    /**
     * GET  /webhooks/meta/instagram  — Meta hub verification challenge
     * POST /webhooks/meta/instagram  — Incoming DM events
     */
    public function webhook(Request $request)
    {
        if ($request->isMethod('get')) {
            return $this->handleVerification($request);
        }

        if (!$this->verifySignature($request)) {
            Log::warning('[Instagram] Webhook signature mismatch — request rejected');
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? '') !== 'instagram') {
            return response('ok', 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            $entryId = $entry['id'] ?? null;

            // Instagram webhooks send entry.id = Instagram Business Account ID (instagram_user_id)
            // Fall back to matching page_id for legacy configurations
            $account = null;
            if ($entryId) {
                $account = InstagramAccount::where('instagram_user_id', $entryId)
                    ->where('is_active', true)
                    ->first();

                if (!$account) {
                    $account = InstagramAccount::where('page_id', $entryId)
                        ->where('is_active', true)
                        ->first();
                }
            }

            if (!$account) {
                Log::warning('[Instagram] Webhook: no active account matched entry.id=' . $entryId);
                continue;
            }

            foreach ($entry['messaging'] ?? [] as $event) {
                $this->handleMessagingEvent($event, $account);
            }
        }

        return response('ok', 200);
    }

    private function handleVerification(Request $request)
    {
        $account = InstagramAccount::active();

        if (
            $account
            && $request->query('hub_mode') === 'subscribe'
            && $request->query('hub_verify_token') === $account->verify_token
        ) {
            return response($request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::warning('[Instagram] Webhook verification failed — token mismatch or no active account');
        return response('Forbidden', 403);
    }

    private function handleMessagingEvent(array $event, InstagramAccount $account): void
    {
        $msg = $event['message'] ?? null;
        if (!$msg) return;

        // Skip echo events (our own outbound messages reflected back by Meta)
        if (!empty($msg['is_echo'])) return;

        $mid      = $msg['mid']  ?? null;
        $text     = $msg['text'] ?? null;
        $senderId = $event['sender']['id'] ?? null;

        if (!$mid || !$text || !$senderId) return;

        // Dedup — skip if already stored
        if (InstagramMessage::where('mid', $mid)->exists()) return;

        // Get or create conversation for this sender
        $conversation = InstagramConversation::firstOrCreate(
            ['instagram_account_id' => $account->id, 'sender_id' => $senderId]
        );

        // Fetch sender profile — also retry if stored name is a raw numeric PSID
        $storedName = $conversation->sender_name ?? '';
        $needsProfile = !$storedName || preg_match('/^\d{10,}$/', trim($storedName));
        if ($needsProfile) {
            $profile = $this->fetchSenderProfile($senderId, $account->access_token);
            if (!empty($profile)) {
                $conversation->sender_name     = $profile['name']     ?? ($storedName ?: $senderId);
                $conversation->sender_username = $profile['username'] ?? $conversation->sender_username;
            } elseif (!$storedName) {
                $conversation->sender_name = $senderId;
            }
        }

        $sentAt = isset($event['timestamp'])
            ? \Carbon\Carbon::createFromTimestampMs((int) $event['timestamp'])
            : now();

        InstagramMessage::create([
            'conversation_id' => $conversation->id,
            'mid'             => $mid,
            'direction'       => 'inbound',
            'body'            => $text,
            'sent_by'         => null,
            'is_read'         => false,
            'sent_at'         => $sentAt,
        ]);

        $conversation->last_message_preview = mb_substr($text, 0, 120);
        $conversation->last_message_at      = $sentAt;
        $conversation->unread_count         = $conversation->unread_count + 1;
        $conversation->save();
    }

    // ── Chat UI ───────────────────────────────────────────────────────────────

    public function index()
    {
        $account = InstagramAccount::active();
        $view    = Auth::user()->role === 'manager'
            ? 'manager.instagram.index'
            : 'telecaller.instagram.index';

        return view($view, compact('account'));
    }

    // ── AJAX: Conversations List ──────────────────────────────────────────────

    public function conversations()
    {
        $account = InstagramAccount::active();

        if (!$account) {
            return response()->json(['ok' => false, 'conversations' => []]);
        }

        $conversations = InstagramConversation::where('instagram_account_id', $account->id)
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get()
            ->map(function ($c) {
                $username = $c->sender_username ?? null;
                $name     = $c->sender_name     ?? null;
                // Prefer @username as display; fall back to real name; last resort is truncated PSID
                if ($username) {
                    $display = '@' . $username;
                } elseif ($name && !preg_match('/^\d{10,}$/', trim($name))) {
                    $display = $name;
                } else {
                    $display = $name ? ('IG User …' . substr(trim($name), -4)) : 'Unknown';
                }
                return [
                    'id'              => $c->id,
                    'display_name'    => $display,
                    'sender_name'     => $name     ?? 'Unknown',
                    'sender_username' => $username,
                    'last_preview'    => $c->last_message_preview,
                    'last_at'         => $c->last_message_at?->diffForHumans(),
                    'unread_count'    => $c->unread_count,
                ];
            });

        $totalUnread = $conversations->sum('unread_count');

        return response()->json([
            'ok'           => true,
            'conversations' => $conversations,
            'total_unread'  => $totalUnread,
        ]);
    }

    // ── AJAX: Messages for a Conversation ────────────────────────────────────

    public function messages(Request $request, $conversationId)
    {
        $conversation = InstagramConversation::findOrFail($conversationId);
        $after        = $request->query('after'); // last known message ID (incremental poll)

        if ($after) {
            $msgs = InstagramMessage::where('conversation_id', $conversationId)
                ->where('id', '>', $after)
                ->orderBy('id')
                ->get();
        } else {
            $msgs = InstagramMessage::where('conversation_id', $conversationId)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->reverse()
                ->values();
        }

        $username = $conversation->sender_username ?? null;
        $name     = $conversation->sender_name     ?? null;
        if ($username) {
            $displayName = '@' . $username;
        } elseif ($name && !preg_match('/^\d{10,}$/', trim($name))) {
            $displayName = $name;
        } else {
            $displayName = $name ? ('IG User …' . substr(trim($name), -4)) : 'Unknown';
        }

        return response()->json([
            'ok'              => true,
            'display_name'    => $displayName,
            'sender_name'     => $name     ?? 'Unknown',
            'sender_username' => $username,
            'messages'        => $msgs->map(fn($m) => [
                'id'        => $m->id,
                'direction' => $m->direction,
                'body'      => $m->body,
                'sent_by'   => $m->sender?->name,
                'sent_at'   => $m->sent_at?->format('h:i A'),
                'sent_date' => $m->sent_at?->format('d M Y'),
            ]),
        ]);
    }

    // ── Send Reply ────────────────────────────────────────────────────────────

    public function reply(Request $request, $conversationId)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $conversation = InstagramConversation::findOrFail($conversationId);
        $account      = InstagramAccount::findOrFail($conversation->instagram_account_id);
        $text         = $request->input('message');
        $user         = Auth::user();

        // Instagram Messaging API requires the Instagram Business Account ID (instagram_user_id)
        // The Facebook page_id cannot be used as the sender node for Instagram DMs
        $senderId = $account->instagram_user_id;
        if (!$senderId) {
            return response()->json([
                'ok'    => false,
                'error' => 'Instagram Business Account ID not configured. Please update the Instagram settings.',
            ], 422);
        }

        [$mid, $sendError] = $this->sendToInstagram(
            $conversation->sender_id,
            $text,
            $account->access_token,
            $senderId
        );

        if (!$mid) {
            return response()->json([
                'ok'    => false,
                'error' => $sendError ?? 'Failed to send. Check that the Instagram access token is valid and not expired.',
            ], 422);
        }

        $message = InstagramMessage::create([
            'conversation_id' => $conversationId,
            'mid'             => $mid,
            'direction'       => 'outbound',
            'body'            => $text,
            'sent_by'         => $user->id,
            'is_read'         => true,
            'sent_at'         => now(),
        ]);

        $conversation->last_message_preview = mb_substr($text, 0, 120);
        $conversation->last_message_at      = now();
        $conversation->save();

        return response()->json([
            'ok'      => true,
            'message' => [
                'id'        => $message->id,
                'direction' => 'outbound',
                'body'      => $text,
                'sent_by'   => $user->name,
                'sent_at'   => now()->format('h:i A'),
                'sent_date' => now()->format('d M Y'),
            ],
        ]);
    }

    // ── Mark Conversation Read ────────────────────────────────────────────────

    public function markRead($conversationId)
    {
        InstagramMessage::where('conversation_id', $conversationId)
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        InstagramConversation::where('id', $conversationId)
            ->update(['unread_count' => 0]);

        return response()->json(['ok' => true]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function verifySignature(Request $request): bool
    {
        $account = InstagramAccount::active();
        if (!$account || !$account->app_secret) {
            return true; // skip in development when app_secret not set
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (!$signature) return false;

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $account->app_secret);
        return hash_equals($expected, $signature);
    }

    private function fetchSenderProfile(string $senderId, string $accessToken): array
    {
        try {
            $res = Http::timeout(5)->get("https://graph.facebook.com/v21.0/{$senderId}", [
                'fields'       => 'name,username',
                'access_token' => $accessToken,
            ]);

            if ($res->ok()) {
                return $res->json() ?? [];
            }

            Log::warning('[Instagram] fetchSenderProfile non-OK: ' . $res->body());
        } catch (\Exception $e) {
            Log::warning('[Instagram] fetchSenderProfile error: ' . $e->getMessage());
        }

        return [];
    }

    /** @return array{0: ?string, 1: ?string} [message_id|null, error_message|null] */
    private function sendToInstagram(
        string $recipientId,
        string $text,
        string $accessToken,
        string $pageId
    ): array {

        try {

            $res = Http::post(
                "https://graph.facebook.com/v21.0/{$pageId}/messages",
                [
                    "recipient" => [
                        "id" => $recipientId
                    ],
                    "message" => [
                        "text" => $text
                    ],
                    "access_token" => $accessToken
                ]
            );

            if ($res->ok()) {
                $mid = $res->json('message_id') ?? ('crm_' . uniqid('', true));
                return [$mid, null];
            }

            $metaError = $res->json('error.message') ?? $res->body();
            Log::error('[Instagram] Send failed (' . $res->status() . '): ' . $res->body());

            return [null, $metaError];
        } catch (\Exception $e) {

            Log::error('[Instagram] sendToInstagram exception: ' . $e->getMessage());
            return [null, $e->getMessage()];
        }
    }
}
