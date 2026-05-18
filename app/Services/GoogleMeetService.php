<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMeetService
{
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const SCOPE        = 'https://www.googleapis.com/auth/calendar.events';

    // ── OAuth ──────────────────────────────────────────────────────────────────

    private function clientId(): string
    {
        return (string) config('google.client_id', env('GOOGLE_CLIENT_ID', ''));
    }

    private function clientSecret(): string
    {
        return (string) config('google.client_secret', env('GOOGLE_CLIENT_SECRET', ''));
    }

    public function getAuthUrl(string $redirectUri): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
        return self::AUTH_URL . '?' . $params;
    }

    public function handleCallback(string $code, string $redirectUri): bool
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (!$response->successful()) {
            Log::error('Google OAuth token exchange failed', ['body' => $response->body()]);
            return false;
        }

        $data = $response->json();
        $this->storeTokens($data);
        return true;
    }

    public function isConnected(): bool
    {
        return (bool) Setting::getSecure('google_refresh_token');
    }

    // ── Token Management ───────────────────────────────────────────────────────

    private function storeTokens(array $data): void
    {
        Setting::setSecure('google_access_token',  $data['access_token']);
        Setting::set('google_token_expires_at', now()->addSeconds((int)($data['expires_in'] ?? 3600))->timestamp);

        if (!empty($data['refresh_token'])) {
            Setting::setSecure('google_refresh_token', $data['refresh_token']);
        }
    }

    public function getAccessToken(): ?string
    {
        $expiresAt = (int) Setting::get('google_token_expires_at', 0);

        if ($expiresAt > now()->addSeconds(60)->timestamp) {
            return Setting::getSecure('google_access_token');
        }

        return $this->refreshAccessToken();
    }

    private function refreshAccessToken(): ?string
    {
        $refreshToken = Setting::getSecure('google_refresh_token');
        if (!$refreshToken) {
            return null;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);

        if (!$response->successful()) {
            Log::error('Google token refresh failed', ['body' => $response->body()]);
            return null;
        }

        $data = $response->json();
        $this->storeTokens($data);
        return $data['access_token'];
    }

    // ── Meet Creation ──────────────────────────────────────────────────────────

    /**
     * Create a Google Calendar event with a Meet link.
     *
     * Pass $attendeeEmail to add the lead as a guest — Google will email them
     * a calendar invite that includes the Meet link and blocks their calendar.
     *
     * @return array{ok: bool, link: string|null, event_id: string|null, error: string|null, email_sent: bool}
     */
    public function createMeet(
        string             $title,
        \DateTimeInterface $startTime,
        int                $durationMinutes,
        ?string            $attendeeEmail = null,
        ?string            $attendeeName  = null,
        ?string            $notes         = null,
    ): array {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['ok' => false, 'link' => null, 'event_id' => null, 'error' => 'Google account not connected.', 'email_sent' => false];
        }

        $timeZone = config('app.timezone', 'Asia/Kolkata');
        $tz       = new \DateTimeZone($timeZone);
        $start    = \DateTime::createFromFormat('U', (string) $startTime->getTimestamp())->setTimezone($tz);
        $end      = \DateTime::createFromFormat('U', (string) ($startTime->getTimestamp() + $durationMinutes * 60))->setTimezone($tz);

        $body = [
            'summary'        => $title,
            'description'    => $notes ?? '',
            'start'          => ['dateTime' => $start->format(\DateTimeInterface::ATOM), 'timeZone' => $timeZone],
            'end'            => ['dateTime' => $end->format(\DateTimeInterface::ATOM),   'timeZone' => $timeZone],
            'conferenceData' => [
                'createRequest' => [
                    'requestId'             => uniqid('crm_', true),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 10],
                ],
            ],
        ];

        $emailSent = false;
        if ($attendeeEmail && filter_var($attendeeEmail, FILTER_VALIDATE_EMAIL)) {
            $attendee = ['email' => $attendeeEmail];
            if ($attendeeName) {
                $attendee['displayName'] = $attendeeName;
            }
            $body['attendees']              = [$attendee];
            $body['guestsCanModifyEvent']   = false;
            $body['guestsCanInviteOthers']  = false;
            $emailSent = true;
        }

        $response = Http::withToken($token)
            ->withQueryParameters([
                'conferenceDataVersion' => 1,
                'sendUpdates'           => $emailSent ? 'all' : 'none',
            ])
            ->post(self::CALENDAR_URL, $body);

        if (!$response->successful()) {
            Log::error('Google Meet creation failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['ok' => false, 'link' => null, 'event_id' => null, 'error' => 'Google API error: ' . $response->status(), 'email_sent' => false];
        }

        $event = $response->json();
        $link  = $event['hangoutLink'] ?? $event['conferenceData']['entryPoints'][0]['uri'] ?? null;

        return [
            'ok'         => true,
            'link'       => $link,
            'event_id'   => $event['id'] ?? null,
            'error'      => $link ? null : 'Meet link not returned by Google.',
            'email_sent' => $emailSent,
        ];
    }
}
