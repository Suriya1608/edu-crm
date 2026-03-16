<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;

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
