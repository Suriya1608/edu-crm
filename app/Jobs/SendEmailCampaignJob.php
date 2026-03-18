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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendEmailCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(public int $emailCampaignId) {}

    public function handle(): void
    {
        $campaign = EmailCampaign::find($this->emailCampaignId);
        if (!$campaign || !in_array($campaign->status, ['scheduled', 'sending'])) {
            return;
        }

        $campaign->update(['status' => 'sending', 'sent_at' => $campaign->sent_at ?? now()]);

        $recipients = $campaign->recipients()->where('status', 'pending')->get();

        $sent   = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            // Skip hard-bounced email addresses — do not attempt delivery
            if (EmailBounce::isHardBounced($recipient->email)) {
                $recipient->update(['status' => 'bounced', 'error_message' => 'Suppressed: previous hard bounce']);
                $failed++;
                continue;
            }

            try {
                $appUrl   = rtrim(config('app.url'), '/');
                $siteName = config('app.name');

                // Variable replacement — personalise per recipient
                $vars = [
                    '{{name}}'           => $recipient->name ?? '',
                    '{{lead_name}}'      => $recipient->name ?? '',
                    '{{email}}'          => $recipient->email ?? '',
                    '{{course_name}}'    => $campaign->name ?? '',
                    '{{site_name}}'      => $siteName,
                    '{{year}}'           => date('Y'),
                    '{{cta_link}}'       => $appUrl,
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

                // Convert relative image src URLs to absolute so they load in email clients
                $body = preg_replace(
                    '/(<img\b[^>]*\bsrc=")\\/(?!\\/)/',
                    '$1' . $appUrl . '/',
                    $body
                );

                // Rewrite <a href="..."> links with click-tracking URLs
                $body = $this->rewriteLinks($body, $campaign->id, $recipient->id, $appUrl);

                // Append open-tracking pixel
                $trackingUrl      = route('email.open', [$campaign->id, $recipient->id]);
                $bodyWithTracking = $body
                    . '<img src="' . $trackingUrl . '" width="1" height="1" alt="" style="border:0;display:block;width:1px;height:1px;overflow:hidden;" />';

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
     * Replace every <a href="http(s)://..."> in $body with a click-tracking URL.
     * Creates an EmailClick record per unique link per recipient.
     */
    private function rewriteLinks(string $body, int $campaignId, int $recipientId, string $appUrl): string
    {
        return preg_replace_callback(
            '/<a\b([^>]*)\bhref="(https?:\/\/[^"]+)"([^>]*)>/i',
            function (array $m) use ($campaignId, $recipientId, $appUrl) {
                $originalUrl = $m[2];

                $token = Str::random(48);

                EmailClick::create([
                    'email_campaign_id' => $campaignId,
                    'recipient_id'      => $recipientId,
                    'tracking_token'    => $token,
                    'url'               => $originalUrl,
                ]);

                $trackUrl = $appUrl . '/email/click/' . $token;

                return '<a' . $m[1] . 'href="' . $trackUrl . '"' . $m[3] . '>';
            },
            $body
        );
    }
}
