<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TelecallerDailySummary extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $managerName,
        public readonly string $reportDate,
        public readonly array  $rows   // per-telecaller data rows
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Telecaller Daily Summary — {$this->reportDate}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.telecaller-daily-summary',
        );
    }
}
