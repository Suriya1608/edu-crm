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
        $telecallerName = $lead?->assignedUser?->name ?? ($this->followup->user?->name ?? 'Unassigned');

        return (new MailMessage)
            ->subject('Missed Follow-up Escalation: ' . ($lead?->lead_code ?? 'Lead'))
            ->line('A follow-up has been missed and escalated to manager.')
            ->line('Lead: ' . ($lead?->name ?? 'N/A'))
            ->line('Lead Code: ' . ($lead?->lead_code ?? 'N/A'))
            ->line('Telecaller: ' . $telecallerName)
            ->line('Follow-up Date: ' . optional($this->followup->next_followup)->format('d M Y'))
            ->action('View Missed Follow-ups', route('manager.followups.missed'));
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
