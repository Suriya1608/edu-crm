<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaViolationEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $link = null,
        public array $meta = []
    ) {
    }

    public function via(object $notifiable): array
    {
        $type = (string) ($this->meta['type'] ?? 'escalation');

        if ($type === 'followup_reminder') {
            $channels = [];
            if (Setting::get('notify_inapp_followup_reminder', '1') === '1') {
                $channels[] = 'database';
            }
            if (Setting::get('notify_email_followup_reminder', '0') === '1') {
                $channels[] = 'mail';
            }
            return $channels;
        }

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
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->message);

        if (!empty($this->link)) {
            $mail->action('Review Escalation', $this->link);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return array_merge($this->meta, [
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
        ]);
    }
}
