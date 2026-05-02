<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZoomService
{
    private const TOKEN_URL   = 'https://zoom.us/oauth/token';
    private const MEETING_URL = 'https://api.zoom.us/v2/users/me/meetings';

    // ── Credentials ────────────────────────────────────────────────────────────

    private function accountId(): string
    {
        return Setting::getSecure('zoom_account_id', '');
    }

    private function clientId(): string
    {
        return Setting::getSecure('zoom_client_id', '');
    }

    private function clientSecret(): string
    {
        return Setting::getSecure('zoom_client_secret', '');
    }

    public function isConfigured(): bool
    {
        return $this->accountId() && $this->clientId() && $this->clientSecret();
    }

    // ── Token (Server-to-Server OAuth) ─────────────────────────────────────────

    public function getAccessToken(): ?string
    {
        $expiresAt = (int) Setting::get('zoom_token_expires_at', 0);

        if ($expiresAt > now()->addSeconds(60)->timestamp) {
            return Setting::getSecure('zoom_access_token');
        }

        return $this->fetchAccessToken();
    }

    private function fetchAccessToken(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $response = Http::withBasicAuth($this->clientId(), $this->clientSecret())
            ->asForm()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'account_credentials',
                'account_id' => $this->accountId(),
            ]);

        if (!$response->successful()) {
            Log::error('Zoom token fetch failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $data = $response->json();

        Setting::setSecure('zoom_access_token', $data['access_token']);
        Setting::set('zoom_token_expires_at', now()->addSeconds((int)($data['expires_in'] ?? 3600))->timestamp);

        return $data['access_token'];
    }

    // ── Meeting Creation ───────────────────────────────────────────────────────

    /**
     * Create a Zoom meeting via Server-to-Server OAuth.
     *
     * @return array{ok: bool, link: string|null, meeting_id: string|null, error: string|null}
     */
    public function createMeeting(
        string             $title,
        \DateTimeInterface $startTime,
        int                $durationMinutes,
        ?string            $attendeeEmail = null,
        ?string            $attendeeName  = null,
        ?string            $notes         = null,
    ): array {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['ok' => false, 'link' => null, 'meeting_id' => null, 'error' => 'Zoom not configured or credentials invalid.'];
        }

        $timeZone = config('app.timezone', 'Asia/Kolkata');
        $tz       = new \DateTimeZone($timeZone);
        $start    = \DateTime::createFromFormat('U', (string) $startTime->getTimestamp())->setTimezone($tz);

        $body = [
            'topic'      => $title,
            'type'       => 2, // scheduled
            'start_time' => $start->format('Y-m-d\TH:i:s'),
            'duration'   => $durationMinutes,
            'timezone'   => $timeZone,
            'agenda'     => $notes ?? '',
            'settings'   => [
                'host_video'        => true,
                'participant_video'  => true,
                'join_before_host'   => true,
                'mute_upon_entry'    => false,
                'auto_recording'     => 'none',
                'waiting_room'       => false,
            ],
        ];

        $response = Http::withToken($token)
            ->post(self::MEETING_URL, $body);

        if (!$response->successful()) {
            Log::error('Zoom meeting creation failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['ok' => false, 'link' => null, 'meeting_id' => null, 'error' => 'Zoom API error: ' . $response->status()];
        }

        $meeting = $response->json();
        $link    = $meeting['join_url'] ?? null;

        return [
            'ok'         => (bool) $link,
            'link'       => $link,
            'meeting_id' => (string) ($meeting['id'] ?? ''),
            'error'      => $link ? null : 'Join URL not returned by Zoom.',
        ];
    }
}
