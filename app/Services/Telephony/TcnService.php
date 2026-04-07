<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TcnService implements TelephonyInterface
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $refreshToken;
    protected string $redirectUri;

    public function __construct(array $config)
    {
        $this->clientId     = $config['client_id']     ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->refreshToken = $config['refresh_token'] ?? '';
        $this->redirectUri  = $config['redirect_uri']  ?? '';
    }

    /**
     * Generate a fresh TCN access token using the stored refresh token.
     * The client_secret never leaves the server.
     */
    public function generateAccessToken(): ?string
    {
        try {
            $response = Http::asForm()->post('https://auth.tcn.com/token', [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('TCN token generated', ['expires_in' => $data['expires_in'] ?? null]);
                return $data['access_token'] ?? null;
            }

            Log::error('TCN token failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('TCN token exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * TelephonyInterface contract — TCN is WebRTC/browser-based so server-side
     * call initiation is not used. Returns a placeholder response.
     */
    public function makeCall(string $from, string $to): array
    {
        return [
            'provider' => 'tcn',
            'message'  => 'TCN calls are initiated from the browser via WebRTC. Use the softphone widget.',
        ];
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }
}
