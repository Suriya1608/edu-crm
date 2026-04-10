<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoginSessionService
{
    /**
     * Create a UserSession record for the given user, capturing device and location.
     */
    public function createSession(User $user, Request $request): void
    {
        $ua       = $request->userAgent() ?? '';
        $device   = $this->parseUserAgent($ua);
        $location = $this->fetchLocation($request->ip() ?? '');

        UserSession::create([
            'user_id'          => $user->id,
            'login_at'         => Carbon::now('Asia/Kolkata'),
            'ip_address'       => $request->ip(),
            'user_agent'       => mb_substr($ua, 0, 500),
            'device_type'      => $device['device'],
            'browser'          => $device['browser'],
            'platform'         => $device['platform'],
            'location_area'    => $location['area'],
            'location_city'    => $location['city'],
            'location_state'   => $location['state'],
            'location_country' => $location['country'],
        ]);
    }

    private function parseUserAgent(string $ua): array
    {
        $browser  = 'Unknown';
        $platform = 'Unknown';
        $device   = 'Desktop';

        // Platform detection
        if (str_contains($ua, 'Windows'))       $platform = 'Windows';
        elseif (str_contains($ua, 'Macintosh')) $platform = 'macOS';
        elseif (str_contains($ua, 'Android'))   { $platform = 'Android'; $device = 'Mobile'; }
        elseif (str_contains($ua, 'iPhone'))    { $platform = 'iOS';     $device = 'Mobile'; }
        elseif (str_contains($ua, 'iPad'))      { $platform = 'iOS';     $device = 'Tablet'; }
        elseif (str_contains($ua, 'Linux'))     $platform = 'Linux';

        // Browser detection (order matters — Edge/Opera before Chrome)
        if (str_contains($ua, 'Edg/'))                                         $browser = 'Edge';
        elseif (str_contains($ua, 'OPR/'))                                     $browser = 'Opera';
        elseif (str_contains($ua, 'Chrome'))                                   $browser = 'Chrome';
        elseif (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome'))   $browser = 'Safari';
        elseif (str_contains($ua, 'Firefox'))                                  $browser = 'Firefox';
        elseif (str_contains($ua, 'Trident'))                                  $browser = 'Internet Explorer';

        return compact('browser', 'platform', 'device');
    }

    private function fetchLocation(string $ip): array
    {
        $empty = ['area' => null, 'city' => null, 'state' => null, 'country' => null];

        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return $empty;
        }

        // Private / RFC-1918 / link-local ranges — skip geolocation
        $privateRanges = ['192.168.', '10.', '172.16.', '172.17.', '172.18.', '172.19.',
                          '172.2',    '172.30.', '172.31.', '169.254.'];
        foreach ($privateRanges as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return $empty;
            }
        }

        try {
            $endpoint = rtrim((string) config('services.geo_lookup.base_url', 'https://ipwho.is'), '/');
            $response = Http::timeout(3)->get("{$endpoint}/{$ip}");

            if ($response->ok()) {
                $data = $response->json();
                if (($data['success'] ?? false) === true) {
                    return [
                        'area'    => $data['connection']['isp'] ?? null,
                        'city'    => $data['city']        ?? null,
                        'state'   => $data['region']      ?? null,
                        'country' => $data['country']     ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('IP geolocation failed: ' . $e->getMessage());
        }

        return $empty;
    }
}
