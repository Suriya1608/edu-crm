<?php

namespace App\Services\WhatsApp;

use App\Models\Setting;
use App\Services\WhatsApp\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaProvider implements WhatsAppProviderInterface
{
    public function name(): string
    {
        return 'meta';
    }

    public function isConfigured(): bool
    {
        return (bool) $this->accessToken() && (bool) $this->phoneNumberId();
    }

    /**
     * Send a text message via Meta Cloud API.
     * Uses free-form text if inside the 24-hour inbound window, otherwise sends the default template.
     */
    public function sendText(string $to, string $body, bool $inbound24h = false, string $recipientName = ''): array
    {
        $token         = $this->accessToken();
        $phoneNumberId = $this->phoneNumberId();

        if (! $token || ! $phoneNumberId) {
            return [
                'ok'                  => false,
                'provider_message_id' => null,
                'provider'            => $this->name(),
                'error'               => 'Meta WhatsApp is not configured. Set token and Phone Number ID in Admin → Settings → WhatsApp.',
            ];
        }

        $templateName     = (string) Setting::get('meta_whatsapp_template_name',
            config('services.meta.whatsapp_default_template', 'welcome_template'));
        $templateLanguage = (string) Setting::get('meta_whatsapp_template_language',
            config('services.meta.whatsapp_default_template_language', 'en'));

        $templatePayload = [
            'name'     => $templateName,
            'language' => ['code' => $templateLanguage],
        ];

        // If the template uses {{1}} for the recipient name, pass it as a body component parameter.
        if ($recipientName !== '') {
            $templatePayload['components'] = [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $recipientName],
                    ],
                ],
            ];
        }

        $payload = $inbound24h
            ? [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['preview_url' => false, 'body' => $body],
            ]
            : [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $to,
                'type'              => 'template',
                'template'          => $templatePayload,
            ];

        try {
            $http = Http::withToken($token)->timeout(15)->asJson();
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post(
                "https://graph.facebook.com/{$this->graphApiVersion()}/{$phoneNumberId}/messages",
                $payload
            );

            if (! $response->successful()) {
                $errCode = $response->json('error.code');
                $error   = $response->json('error.message', 'Unknown Meta API error');
                Log::error('MetaProvider::sendText failed', ['to' => $to, 'error' => $error, 'code' => $errCode]);

                if ($errCode === 190 || str_contains(strtolower($error), 'auth') || str_contains(strtolower($error), 'token')) {
                    return ['ok' => false, 'provider_message_id' => null, 'provider' => $this->name(),
                            'error' => 'Meta token expired — update it in Admin → Settings → WhatsApp.'];
                }
                if (in_array($errCode, [132000, 132001, 132005, 132007, 132012, 132015, 132016])) {
                    return ['ok' => false, 'provider_message_id' => null, 'provider' => $this->name(),
                            'error' => "Template error ({$errCode}): check template name \"{$templateName}\" and language \"{$templateLanguage}\" in Admin → Settings → WhatsApp."];
                }
                if ($errCode === 100 || str_contains($error, 'missing permissions')) {
                    return ['ok' => false, 'provider_message_id' => null, 'provider' => $this->name(),
                            'error' => 'Phone Number ID is wrong or your token lacks permission — check Admin → Settings → WhatsApp.'];
                }

                return ['ok' => false, 'provider_message_id' => null, 'provider' => $this->name(),
                        'error' => 'Meta API: ' . $error];
            }

            return [
                'ok'                  => true,
                'provider_message_id' => $response->json('messages.0.id'),
                'provider'            => $this->name(),
                'error'               => null,
            ];

        } catch (\Throwable $e) {
            Log::error('MetaProvider::sendText exception', ['to' => $to, 'error' => $e->getMessage()]);
            return ['ok' => false, 'provider_message_id' => null, 'provider' => $this->name(),
                    'error' => $e->getMessage()];
        }
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function accessToken(): string
    {
        return (string) Setting::getSecure('meta_whatsapp_token', config('services.meta.whatsapp_token', ''));
    }

    public function phoneNumberId(): string
    {
        return (string) Setting::get('meta_whatsapp_phone_number_id', config('services.meta.whatsapp_phone_id', ''));
    }

    private function graphApiVersion(): string
    {
        return config('services.meta.graph_api_version', 'v22.0');
    }
}
