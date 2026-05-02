<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $emailSubject,
        public readonly string $emailBody,
        public readonly string $recipientName,
        public readonly array $fileAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.campaign');
    }

    public function attachments(): array
    {
        return collect($this->fileAttachments)
            ->filter(fn ($a) => isset($a['path']) && Storage::disk('public')->exists($a['path']))
            ->map(fn ($a) => Attachment::fromStorageDisk('public', $a['path'])->as($a['name'] ?? basename($a['path'])))
            ->values()
            ->all();
    }
}
