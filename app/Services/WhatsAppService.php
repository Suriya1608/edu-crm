<?php

namespace App\Services;

use App\Services\WhatsApp\MetaProvider;

/**
 * WhatsAppService — single entry point for all outbound WhatsApp messages.
 * Uses Meta WhatsApp Cloud API exclusively.
 */
class WhatsAppService
{
    public function __construct(
        private readonly MetaProvider $meta,
    ) {}

    /**
     * Send a text message via Meta WhatsApp Cloud API.
     *
     * @return array{ok: bool, provider_message_id: string|null, provider: string, error: string|null}
     */
    public function send(string $toPhone, string $message, bool $inbound24h = false, string $recipientName = ''): array
    {
        return $this->meta->sendText($toPhone, $message, $inbound24h, $recipientName);
    }

    /**
     * Return the Meta provider instance (for media uploads and webhook handling).
     */
    public function metaProvider(): MetaProvider
    {
        return $this->meta;
    }

    /**
     * Check whether Meta WhatsApp is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->meta->isConfigured();
    }
}
