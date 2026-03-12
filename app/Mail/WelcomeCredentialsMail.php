<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $siteName;
    public readonly string $siteUrl;

    public function __construct(
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $plainPassword,
        public readonly string $role,
        public readonly string $loginUrl,
    ) {
        $this->siteName = Setting::get('site_name', config('app.name'));
        $this->siteUrl  = Setting::get('site_url', config('app.url'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Account Credentials — Welcome to ' . $this->siteName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome-credentials');
    }

    public function attachments(): array
    {
        return [];
    }
}
