<?php

namespace App\Services\WhatsApp\Contracts;

interface WhatsAppProviderInterface
{
    /**
     * Send a text message to the given phone number.
     *
     * @param  string  $to            Normalized phone number (digits only, with country code)
     * @param  string  $body          Message body
     * @param  bool    $inbound24h    True if recipient has messaged us within the last 24 hours
     *                                (only relevant for Meta's 24-hour free-form window)
     * @return array{ok: bool, provider_message_id: string|null, provider: string, error: string|null}
     */
    public function sendText(string $to, string $body, bool $inbound24h = false, string $recipientName = ''): array;

    /**
     * Provider identifier (e.g. 'meta').
     */
    public function name(): string;

    /**
     * Returns true if the provider credentials are configured.
     */
    public function isConfigured(): bool;
}
