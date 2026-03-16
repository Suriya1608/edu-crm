<?php

namespace App\Notifications;

use App\Models\Followup;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MissedFollowupEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Followup $followup)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (Setting::get('notify_inapp_escalation', '1') === '1') {
            $channels[] = 'database';
        }
        if (Setting::get('notify_email_escalation', '1') === '1') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->followup->lead;

        // Use site_url from settings so the link works behind proxies / ngrok tunnels
        $siteUrl   = rtrim(Setting::get('site_url', config('app.url')), '/');
        $actionUrl = $siteUrl . '/manager/followups/missed';

        return (new MailMessage)
            ->subject('Missed Follow-up Escalation: ' . ($lead?->lead_code ?? 'Lead'))
            ->view('emails.missed-followup', [
                'followup'  => $this->followup,
                'actionUrl' => $actionUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $lead = $this->followup->lead;
        $telecallerName = $lead?->assignedUser?->name ?? ($this->followup->user?->name ?? 'Unassigned');

        return [
            'title' => 'Missed Follow-up Escalated',
            'message' => 'Lead ' . ($lead?->lead_code ?? ('#' . $this->followup->lead_id)) . ' missed by ' . $telecallerName,
            'followup_id' => $this->followup->id,
            'lead_id' => $this->followup->lead_id,
            'telecaller' => $telecallerName,
            'next_followup' => optional($this->followup->next_followup)?->toDateString(),
            'link' => route('manager.followups.missed'),
        ];
    }
}
