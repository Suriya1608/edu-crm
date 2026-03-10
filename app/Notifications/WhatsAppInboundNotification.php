<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WhatsAppInboundNotification extends Notification
{
    public function __construct(
        public Lead   $lead,
        public string $messageBody,
        public string $senderRole = 'manager' // the role of the notifiable user
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (\App\Models\Setting::get('notify_email_whatsapp_inbound', '0') === '1') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $route = $this->senderRole === 'telecaller'
            ? route('telecaller.leads.show', encrypt($this->lead->id))
            : route('manager.leads.show', encrypt($this->lead->id));

        return (new MailMessage)
            ->subject('New WhatsApp message from ' . $this->lead->name)
            ->line($this->lead->name . ' sent: ' . \Illuminate\Support\Str::limit($this->messageBody, 100))
            ->action('Open Chat', $route);
    }

    public function toArray(object $notifiable): array
    {
        $route = $this->senderRole === 'telecaller'
            ? route('telecaller.leads.show', encrypt($this->lead->id))
            : route('manager.leads.show', encrypt($this->lead->id));

        return [
            'type'       => 'whatsapp_inbound',
            'title'      => 'WhatsApp: ' . $this->lead->name,
            'message'    => \Illuminate\Support\Str::limit($this->messageBody, 80),
            'link'       => $route,
            'lead_id'    => $this->lead->id,
            'lead_name'  => $this->lead->name,
        ];
    }
}
