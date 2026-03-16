<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\EmailCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

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
            try {
                $body = str_replace('{{name}}', $recipient->name ?? '', $campaign->template_body);

                // Convert relative image src URLs to absolute so they load in email clients
                $appUrl = rtrim(config('app.url'), '/');
                $body   = preg_replace(
                    '/(<img\b[^>]*\bsrc=")\/(?!\/)/',
                    '$1' . $appUrl . '/',
                    $body
                );

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
}
