<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\EmailBounce;
use App\Models\EmailCampaign;
use App\Models\EmailClick;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendEmailCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'emails';

    public int $tries   = 3;
    public int $timeout = 600;

    public function __construct(public int $emailCampaignId) {}

    /**
     * Exponential backoff: retry after 30 s, then 2 min.
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $campaign = EmailCampaign::find($this->emailCampaignId);
        if (!$campaign || !in_array($campaign->status, ['scheduled', 'sending'])) {
            return;
        }

        $campaign->update(['status' => 'sending', 'sent_at' => $campaign->sent_at ?? now()]);

        $recipients = $campaign->recipients()->where('status', 'pending')->get();

        // Resolve once — identical for every recipient in this campaign
        $appUrl   = rtrim(config('app.url'), '/');
        $siteName = config('app.name');

        // Pre-fetch all hard-bounced emails in one query instead of one per recipient
        $hardBouncedEmails = EmailBounce::whereIn('email', $recipients->pluck('email'))
            ->where('bounce_type', 'hard')
            ->pluck('email')
            ->flip()
            ->all();

        $sent   = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            // Skip hard-bounced email addresses — do not attempt delivery
            if (isset($hardBouncedEmails[$recipient->email])) {
                $recipient->update(['status' => 'bounced', 'error_message' => 'Suppressed: previous hard bounce']);
                $failed++;
                continue;
            }

            try {
                // Variable replacement — personalise per recipient
                $vars = [
                    '{{name}}'           => $recipient->name ?? '',
                    '{{lead_name}}'      => $recipient->name ?? '',
                    '{{email}}'          => $recipient->email ?? '',
                    '{{course_name}}'    => $campaign->name ?? '',
                    '{{site_name}}'      => $siteName,
                    '{{year}}'           => date('Y'),
                    '{{cta_link}}'       => $appUrl,
                    '{{link}}'           => $appUrl,
                    '{{price}}'          => '',
                    '{{discount}}'       => '',
                    '{{coupon_code}}'    => '',
                    '{{original_price}}' => '',
                    '{{expiry_date}}'    => '',
                    '{{event_name}}'     => $campaign->name ?? '',
                    '{{event_date}}'     => '',
                    '{{event_time}}'     => '',
                    '{{event_venue}}'    => '',
                ];

                $body = str_replace(array_keys($vars), array_values($vars), $campaign->template_body);

                // Make every relative <img src> absolute so email clients can load them.
                $body = $this->absolutifyImages($body, $appUrl);

                // Rewrite <a href="..."> links with click-tracking URLs (batched inserts)
                $body = $this->rewriteLinks($body, $campaign->id, $recipient->id, $appUrl);

                // Append open-tracking pixel
                $trackingUrl      = $appUrl . '/email/open/' . $campaign->id . '/' . $recipient->id;
                $bodyWithTracking = $body
                    . '<img src="' . $trackingUrl . '" width="1" height="1" style="display:none" alt="" />';

                Mail::to($recipient->email, $recipient->name)
                    ->send(new CampaignMail(
                        $campaign->template_subject,
                        $bodyWithTracking,
                        $recipient->name ?? '',
                    ));

                $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $recipient->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $failed++;
            }
        }

        $campaign->update([
            'sent_count'   => $campaign->sent_count + $sent,
            'failed_count' => $campaign->failed_count + $failed,
            'status'       => 'completed',
        ]);
    }

    /**
     * Called by Laravel when all retry attempts are exhausted.
     * Marks the campaign as failed so it doesn't remain stuck in 'sending'.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendEmailCampaignJob failed permanently for campaign #{$this->emailCampaignId}: " . $exception->getMessage());

        $campaign = EmailCampaign::find($this->emailCampaignId);
        if ($campaign && $campaign->status === 'sending') {
            $campaign->update(['status' => 'failed']);
        }
    }

    /**
     * Make every relative <img src="..."> absolute.
     *
     * Leaves these sources untouched (already absolute or non-HTTP):
     *   https://...   http://...   //...   data:...   cid:...
     */
    private function absolutifyImages(string $body, string $appUrl): string
    {
        return preg_replace_callback(
            '/(<img\b[^>]*\bsrc=")([^"]+)(")/i',
            static function (array $m) use ($appUrl): string {
                $src = $m[2];

                if (preg_match('/^(https?:\/\/|\/\/|data:|cid:)/i', $src)) {
                    return $m[1] . $src . $m[3];
                }

                $src = preg_replace('/^(\.\.\/|\.\/)+/', '', $src);
                $src = ltrim($src, '/');

                return $m[1] . $appUrl . '/' . $src . $m[3];
            },
            $body
        );
    }

    /**
     * Replace every <a href="http(s)://..."> with a click-tracking URL.
     * Uses a single bulk INSERT instead of one query per link.
     */
    private function rewriteLinks(string $body, int $campaignId, int $recipientId, string $appUrl): string
    {
        $inserts = [];
        $now     = now();

        $result = preg_replace_callback(
            '/<a\b([^>]*)\bhref="(https?:\/\/[^"]+)"([^>]*)>/i',
            function (array $m) use ($campaignId, $recipientId, $appUrl, &$inserts, $now) {
                $token = Str::random(48);

                $inserts[] = [
                    'email_campaign_id' => $campaignId,
                    'recipient_id'      => $recipientId,
                    'tracking_token'    => $token,
                    'url'               => $m[2],
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];

                return '<a' . $m[1] . 'href="' . $appUrl . '/email/click/' . $token . '"' . $m[3] . '>';
            },
            $body
        );

        if (!empty($inserts)) {
            EmailClick::insert($inserts);
        }

        return $result;
    }
}
