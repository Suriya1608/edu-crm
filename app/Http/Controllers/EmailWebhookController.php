<?php

namespace App\Http\Controllers;

use App\Models\EmailBounce;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles inbound bounce webhooks from email providers.
 *
 * Routes:
 *   POST /email/webhook/bounce?provider={mailgun|sendgrid|ses|generic}  (legacy web route)
 *   POST /api/campaigns/bounce                                           (new API route, same logic)
 *
 * Supported providers (auto-detected from payload or ?provider= param):
 *   - Mailgun   → query ?provider=mailgun
 *   - SendGrid  → query ?provider=sendgrid
 *   - Amazon SES (via SNS) → query ?provider=ses  OR auto-detected from SNS headers
 *   - Generic / SMTP DSN   → plain JSON body (default)
 *
 * Generic / SMTP DSN payload:
 *   { "email": "...", "campaign_id": 1, "bounce_type": "hard|soft", "reason": "..." }
 */
class EmailWebhookController extends Controller
{
    public function bounce(Request $request)
    {
        // Auto-detect SNS from the x-amz-sns-message-type header even without ?provider=ses
        $provider = strtolower($request->query('provider', 'generic'));
        if ($provider === 'generic' && $request->header('x-amz-sns-message-type')) {
            $provider = 'ses';
        }

        $parsed = match ($provider) {
            'mailgun'  => $this->parseMailgun($request),
            'sendgrid' => $this->parseSendGrid($request),
            'ses'      => $this->parseSes($request),
            default    => $this->parseGeneric($request),
        };

        if ($parsed === false) {
            // parseSes returns false when it handled an SNS confirmation (not a bounce)
            return response()->json(['ok' => true, 'info' => 'sns_confirmed']);
        }

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

    // ── SNS signature verification ────────────────────────────────────────────

    /**
     * Verify an AWS SNS message signature.
     * Downloads the signing cert from AWS and verifies the HMAC-SHA1 signature.
     * Returns true if valid, or if the body doesn't look like an SNS message (non-SNS providers).
     */
    private function verifySnsSignature(array $body): bool
    {
        // Only applies to SNS messages; skip for non-SNS payloads
        if (! isset($body['SigningCertURL'], $body['Signature'])) {
            return true;
        }

        $certUrl = $body['SigningCertURL'];
        $parsed  = parse_url($certUrl);

        // Cert URL must be HTTPS from amazonaws.com
        if (($parsed['scheme'] ?? '') !== 'https' ||
            ! str_ends_with($parsed['host'] ?? '', '.amazonaws.com')) {
            Log::warning('EmailWebhook: SNS cert URL not from amazonaws.com', ['url' => $certUrl]);
            return false;
        }

        try {
            $cert   = Http::timeout(5)->get($certUrl)->body();
            $pubKey = openssl_get_publickey($cert);
            if (! $pubKey) {
                return false;
            }

            // Build canonical string per AWS SNS spec
            $type   = $body['Type'] ?? '';
            $fields = $type === 'SubscriptionConfirmation'
                ? ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type']
                : ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];

            $canonical = '';
            foreach ($fields as $field) {
                if (isset($body[$field])) {
                    $canonical .= $field . "\n" . $body[$field] . "\n";
                }
            }

            $sig = base64_decode($body['Signature']);
            return openssl_verify($canonical, $sig, $pubKey, OPENSSL_ALGO_SHA1) === 1;

        } catch (\Throwable $e) {
            Log::error('EmailWebhook: SNS signature check threw', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ── Provider parsers ──────────────────────────────────────────────────────

    private function parseMailgun(Request $request): ?array
    {
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

    /**
     * Parse an AWS SNS notification wrapping an SES bounce event.
     * Returns false (not null) when this was an SNS SubscriptionConfirmation
     * that was handled (caller should return 200 OK without processing bounces).
     *
     * @return array|null|false
     */
    private function parseSes(Request $request): array|null|false
    {
        $body = $request->input();

        // ── Verify SNS message signature before processing anything ──────────
        if (! $this->verifySnsSignature($body)) {
            Log::warning('EmailWebhook: SNS signature verification failed', [
                'type' => $body['Type'] ?? 'unknown',
            ]);
            return null;
        }

        // ── SNS subscription confirmation ─────────────────────────────────────
        if (($body['Type'] ?? '') === 'SubscriptionConfirmation') {
            $subscribeUrl = $body['SubscribeURL'] ?? null;
            if ($subscribeUrl && str_starts_with($subscribeUrl, 'https://sns.')) {
                try {
                    Http::timeout(10)->get($subscribeUrl);
                    Log::info('EmailWebhook: SNS subscription confirmed', ['url' => $subscribeUrl]);
                } catch (\Throwable $e) {
                    Log::error('EmailWebhook: SNS confirmation failed', ['error' => $e->getMessage()]);
                }
            }
            return false; // signal: handled, no bounces to process
        }

        // ── SNS Notification wrapping SES event JSON ──────────────────────────
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

    /**
     * Generic / SMTP DSN JSON format.
     * Expected fields: email, bounce_type (hard|soft), reason, campaign_id
     * Also accepts SMTP DSN-style fields: action, status, diagnostic_code
     */
    private function parseGeneric(Request $request): ?array
    {
        $email = $request->input('email');
        if (!$email) {
            return null;
        }

        // Normalise SMTP DSN action field to bounce type
        $action     = strtolower($request->input('action', ''));
        $status     = $request->input('status', '');           // e.g. "5.1.1"
        $bounceType = $request->input('bounce_type');

        if (!$bounceType) {
            if ($action === 'failed' || str_starts_with($status, '5')) {
                $bounceType = 'hard';
            } elseif (in_array($action, ['delayed', 'relayed', 'expanded'])) {
                $bounceType = 'soft';
            } else {
                $bounceType = 'hard'; // safe default
            }
        }

        $reason = $request->input('reason')
            ?? $request->input('diagnostic_code')
            ?? ($status ? "SMTP status {$status}" : null);

        return [[
            'email'       => $email,
            'bounce_type' => $bounceType,
            'reason'      => $reason,
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
        // Match the most recent recipient record for this email
        $recipientQuery = EmailCampaignRecipient::where('email', $email);
        if ($campaignId) {
            $recipientQuery->where('email_campaign_id', $campaignId);
        }
        $recipient = $recipientQuery->latest()->first();

        $resolvedCampaignId = $campaignId ?? $recipient?->email_campaign_id;

        // Store bounce record (one per email+campaign to prevent duplicates)
        $alreadyLogged = EmailBounce::where('email', $email)
            ->when($resolvedCampaignId, fn($q) => $q->where('campaign_id', $resolvedCampaignId))
            ->exists();

        if (!$alreadyLogged) {
            EmailBounce::create([
                'email'        => $email,
                'campaign_id'  => $resolvedCampaignId,
                'recipient_id' => $recipient?->id,
                'bounce_type'  => $bounceType,
                'reason'       => $reason,
                'provider'     => $provider,
            ]);
        }

        // Update recipient atomically — WHERE status != 'bounced' prevents double-count
        // on simultaneous webhook deliveries for the same bounce event.
        if ($recipient) {
            $updated = EmailCampaignRecipient::where('id', $recipient->id)
                ->where('status', '!=', 'bounced')
                ->update([
                    'status'        => 'bounced',
                    'bounced_at'    => now(),
                    'bounce_type'   => $bounceType,
                    'error_message' => $reason ?? "Bounce ({$bounceType})",
                ]);

            if ($updated && $resolvedCampaignId) {
                EmailCampaign::where('id', $resolvedCampaignId)->increment('bounced_count');
            }
        }

        // Hard bounce → mark all matching lead records as email_invalid
        if ($bounceType === 'hard') {
            Lead::where('email', $email)
                ->where('email_valid', true)
                ->update(['email_valid' => false]);
        }
    }
}
