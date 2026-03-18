<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;
use App\Models\EmailClick;

class EmailTrackingController extends Controller
{
    /**
     * Track open via {campaign_id}/{recipient_id} URL
     * (used by newly sent emails)
     */
    public function open(int $campaignId, int $recipientId)
    {
        $recipient = EmailCampaignRecipient::where('id', $recipientId)
            ->where('email_campaign_id', $campaignId)
            ->first();

        if ($recipient && !$recipient->opened_at) {
            $recipient->update([
                'opened_at' => now(),
                'status'    => 'opened',
            ]);
            $recipient->campaign()->increment('opened_count');
        }

        return $this->pixelResponse();
    }

    /**
     * Track open via legacy token URL
     * (backwards-compat for emails already sent before the route change)
     */
    public function track(string $token)
    {
        $recipient = EmailCampaignRecipient::where('tracking_token', $token)->first();

        if ($recipient && !$recipient->opened_at) {
            $recipient->update([
                'opened_at' => now(),
                'status'    => 'opened',
            ]);
            $recipient->campaign()->increment('opened_count');
        }

        return $this->pixelResponse();
    }

    /**
     * Track link click, then redirect to the original URL.
     */
    public function click(string $token)
    {
        $click = EmailClick::where('tracking_token', $token)->first();

        if ($click) {
            $isFirst = is_null($click->clicked_at);

            $click->increment('click_count');
            $click->update([
                'clicked_at' => $click->clicked_at ?? now(),
                'ip_address' => request()->ip(),
            ]);

            // Increment campaign click_count once per unique recipient
            if ($isFirst) {
                $alreadyClicked = EmailClick::where('email_campaign_id', $click->email_campaign_id)
                    ->where('recipient_id', $click->recipient_id)
                    ->where('id', '!=', $click->id)
                    ->whereNotNull('clicked_at')
                    ->exists();

                if (!$alreadyClicked) {
                    $click->campaign()->increment('click_count');
                }
            }

            return redirect()->away($click->url);
        }

        return redirect(config('app.url'));
    }

    // Return 1×1 transparent GIF
    private function pixelResponse()
    {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }
}
