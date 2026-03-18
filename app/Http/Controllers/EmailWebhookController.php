<?php

namespace App\Http\Controllers;

use App\Models\EmailBounce;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Http\Request;

/**
 * Handles inbound bounce/complaint webhooks from email providers.
 *
 * Supported providers (auto-detected):
 *   - Mailgun   → POST /email/webhook/bounce?provider=mailgun
 *   - SendGrid  → POST /email/webhook/bounce?provider=sendgrid
 *   - Amazon SES (via SNS) → POST /email/webhook/bounce?provider=ses
 *   - Generic   → POST /email/webhook/bounce  (plain JSON body)
 *
 * Generic payload format:
 *   { "email": "...", "campaign_id": 1, "bounce_type": "hard", "reason": "..." }
 */
class EmailWebhookController extends Controller
{
    public function bounce(Request $request)
    {
        $provider = strtolower($request->query('provider', 'generic'));

        $parsed = match ($provider) {
            'mailgun'  => $this->parseMailgun($request),
            'sendgrid' => $this->parseSendGrid($request),
            'ses'      => $this->parseSes($request),
            default    => $this->parseGeneric($request),
        };

        if (!$parsed) {
            return response()->json(['ok' => false, 'error' => 'Unrecognised payload'], 422);
        }

        foreach ($parsed as $bounce) {
            $this->processBounce(
                email:      $bounce['email'],
                bounceType: $bounce['bounce_type'] ?? 'hard',
                reason:     $bounce['reason'] ?? null,
                campaignId: $bounce['campaign_id'] ?? null,
                provider:   $provider,
            );
        }

        return response()->json(['ok' => true]);
    }

    // ── Provider parsers ──────────────────────────────────────────────────────

    private function parseMailgun(Request $request): ?array
    {
        // Mailgun sends a single event per webhook call
        $event       = $request->input('event-data.event') ?? $request->input('event');
        $email       = $request->input('event-data.recipient') ?? $request->input('recipient');
        $description = $request->input('event-data.delivery-status.description')
            ?? $request->input('description');
        $code        = $request->input('event-data.delivery-status.code')
            ?? $request->input('code');

        if (!$email || !in_array($event, ['failed', 'bounced'])) {
            return null;
        }

        $bounceType = ($code && (int) $code >= 500) ? 'hard' : 'soft';

        return [[
            'email'       => $email,
            'bounce_type' => $bounceType,
            'reason'      => $description ?? "Mailgun {$event} event",
        ]];
    }

    private function parseSendGrid(Request $request): ?array
    {
        // SendGrid sends an array of events
        $events = $request->input();
        if (!is_array($events)) {
            return null;
        }

        $bounces = [];
        foreach ($events as $event) {
            if (!isset($event['event'], $event['email'])) continue;
            if (!in_array($event['event'], ['bounce', 'blocked', 'deferred'])) continue;

            $bounces[] = [
                'email'       => $event['email'],
                'bounce_type' => ($event['event'] === 'bounce') ? 'hard' : 'soft',
                'reason'      => $event['reason'] ?? $event['status'] ?? null,
            ];
        }

        return $bounces ?: null;
    }

    private function parseSes(Request $request): ?array
    {
        // AWS SNS wraps the SES notification in a "Message" JSON string
        $body = $request->input();

        // Handle SNS subscription confirmation
        if (($body['Type'] ?? '') === 'SubscriptionConfirmation') {
            // In production: fetch $body['SubscribeURL'] to confirm the subscription
            return null;
        }

        $message = isset($body['Message']) ? json_decode($body['Message'], true) : $body;

        if (!isset($message['notificationType'])) {
            return null;
        }

        if ($message['notificationType'] !== 'Bounce') {
            return null;
        }

        $bounceInfo = $message['bounce'] ?? [];
        $bounceType = strtolower($bounceInfo['bounceType'] ?? 'permanent') === 'permanent'
            ? 'hard'
            : 'soft';

        $bounces = [];
        foreach ($bounceInfo['bouncedRecipients'] ?? [] as $r) {
            if (!isset($r['emailAddress'])) continue;
            $bounces[] = [
                'email'       => $r['emailAddress'],
                'bounce_type' => $bounceType,
                'reason'      => $r['diagnosticCode'] ?? ($bounceInfo['bounceSubType'] ?? null),
            ];
        }

        return $bounces ?: null;
    }

    private function parseGeneric(Request $request): ?array
    {
        $email = $request->input('email');
        if (!$email) {
            return null;
        }

        return [[
            'email'       => $email,
            'bounce_type' => $request->input('bounce_type', 'hard'),
            'reason'      => $request->input('reason'),
            'campaign_id' => $request->input('campaign_id'),
        ]];
    }

    // ── Core processor ────────────────────────────────────────────────────────

    private function processBounce(
        string  $email,
        string  $bounceType,
        ?string $reason,
        ?int    $campaignId,
        string  $provider,
    ): void {
        // Try to match a recipient record
        $recipientQuery = EmailCampaignRecipient::where('email', $email);
        if ($campaignId) {
            $recipientQuery->where('email_campaign_id', $campaignId);
        }
        $recipient = $recipientQuery->latest()->first();

        $resolvedCampaignId = $campaignId ?? $recipient?->email_campaign_id;

        // Store bounce record (prevent duplicates for same email+campaign)
        $alreadyLogged = EmailBounce::where('email', $email)
            ->when($resolvedCampaignId, fn($q) => $q->where('campaign_id', $resolvedCampaignId))
            ->exists();

        if (!$alreadyLogged) {
            EmailBounce::create([
                'email'       => $email,
                'campaign_id' => $resolvedCampaignId,
                'recipient_id'=> $recipient?->id,
                'bounce_type' => $bounceType,
                'reason'      => $reason,
                'provider'    => $provider,
            ]);
        }

        // Mark recipient as bounced and increment campaign counter
        if ($recipient && $recipient->status !== 'bounced') {
            $recipient->update([
                'status'        => 'bounced',
                'error_message' => $reason ?? "Bounce ({$bounceType})",
            ]);

            if ($resolvedCampaignId) {
                EmailCampaign::where('id', $resolvedCampaignId)->increment('bounced_count');
            }
        }
    }
}
