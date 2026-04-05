<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\TcnUserAccount;
use App\Services\Telephony\TelephonyFactory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TcnController extends Controller
{
    // TCN API base URLs
    const AUTH_URL = 'https://auth.tcn.com/token';
    const API_BASE  = 'https://api.bom.tcn.com';

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // OAuth Step 0 â€” Redirect admin to TCN login page
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function authRedirect(): RedirectResponse
    {
        $clientId    = Setting::getSecure('tcn_client_id', env('TCN_CLIENT_ID'));
        $redirectUri = Setting::get('tcn_redirect_uri', env('TCN_REDIRECT_URI'));

        $state = Str::random(32);
        session(['tcn_oauth_state' => $state]);

        $url = 'https://auth.tcn.com/auth?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'scope'         => 'openid offline_access',
            'state'         => $state,
        ]);

        return redirect($url);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // OAuth callback â€” exchange code for refresh_token and store it
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function authCallback(Request $request): RedirectResponse
    {
        $code  = $request->query('code');
        $state = $request->query('state');

        // Detect per-user flow (admin connecting a user's account)
        $userId      = session('tcn_oauth_user_id');
        $encryptedId = session('tcn_oauth_encrypted_id');

        // Determine where to redirect on error/success
        $errorRoute   = ($userId && $encryptedId)
            ? route('admin.users.edit', $encryptedId)
            : route('admin.settings.call');
        $successRoute = $errorRoute;

        if (!$code) {
            session()->forget(['tcn_oauth_state', 'tcn_oauth_user_id', 'tcn_oauth_encrypted_id']);
            return redirect($errorRoute)->with('error', 'TCN OAuth cancelled or failed.');
        }

        // CSRF state check
        if ($state && $state !== session('tcn_oauth_state')) {
            session()->forget(['tcn_oauth_state', 'tcn_oauth_user_id', 'tcn_oauth_encrypted_id']);
            return redirect($errorRoute)->with('error', 'TCN OAuth state mismatch. Please try again.');
        }

        session()->forget(['tcn_oauth_state', 'tcn_oauth_user_id', 'tcn_oauth_encrypted_id']);

        $clientId     = Setting::getSecure('tcn_client_id',     env('TCN_CLIENT_ID'));
        $clientSecret = Setting::getSecure('tcn_client_secret', env('TCN_CLIENT_SECRET'));
        // Must exactly match the redirect_uri sent during the authorize request
        $redirectUri  = route('tcn.auth.callback');

        $response = Http::asForm()->post(self::AUTH_URL, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
        ]);

        if (!$response->successful()) {
            Log::error('TCN OAuth callback failed', [
                'user_id' => $userId,
                'body'    => $response->body(),
            ]);
            return redirect($errorRoute)
                ->with('error', 'TCN token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        // ── Per-user flow ──────────────────────────────────────────────
        if ($userId) {
            $accessToken  = $data['access_token']  ?? null;
            $refreshToken = $data['refresh_token'] ?? null;

            if (!$accessToken) {
                return redirect($errorRoute)->with('error', 'TCN did not return an access token.');
            }

            // Fetch agent info using the fresh access token
            $agentId   = null;
            $agentResp = Http::withToken($accessToken)
                ->post(self::API_BASE . '/api/v0alpha/p3api/getcurrentagent', (object)[]);

            if ($agentResp->successful()) {
                $agentData = $agentResp->json();
                $agentId   = $agentData['agentSid'] ?? $agentData['agent_id'] ?? null;
            } else {
                Log::warning('TCN authCallback: could not fetch agent', [
                    'user_id' => $userId,
                    'status'  => $agentResp->status(),
                    'body'    => $agentResp->body(),
                ]);
            }

            // Fetch hunt group via agent skills
            $huntGroupId = null;
            if ($agentId) {
                $skillsResp = Http::withToken($accessToken)
                    ->post(self::API_BASE . '/api/v0alpha/p3api/getagentskills', [
                        'huntGroupSid' => 0,
                        'agentSid'     => (int) $agentId,
                    ]);

                if ($skillsResp->successful()) {
                    $skillsData  = $skillsResp->json();
                    $huntGroupId = $skillsData['huntGroupSid'] ?? $skillsData['hunt_group_id'] ?? null;
                } else {
                    Log::warning('TCN authCallback: could not fetch skills', [
                        'user_id' => $userId,
                        'status'  => $skillsResp->status(),
                        'body'    => $skillsResp->body(),
                    ]);
                }
            }

            // Persist — refresh_token stored encrypted via model mutator
            TcnUserAccount::saveForUser((int) $userId, [
                'agent_id'      => $agentId      ? (string) $agentId      : null,
                'hunt_group_id' => $huntGroupId  ? (string) $huntGroupId  : null,
                'refresh_token' => $refreshToken,
            ]);

            Log::info('TCN user account connected via OAuth', [
                'user_id'       => $userId,
                'agent_id'      => $agentId,
                'hunt_group_id' => $huntGroupId,
            ]);

            return redirect($successRoute)
                ->with('success', 'TCN account connected! Agent: ' . ($agentId ?? 'n/a') . ', Hunt Group: ' . ($huntGroupId ?? 'n/a'));
        }

        // ── Admin global flow ──────────────────────────────────────────
        if (!empty($data['refresh_token'])) {
            Setting::setSecure('tcn_refresh_token', $data['refresh_token']);
            Log::info('TCN refresh_token stored successfully');
        }

        return redirect()->route('admin.settings.call')
            ->with('success', 'TCN account connected successfully!');
    }

    // ---------------------------------------------------------------
    // Per-user OAuth — Step A: Redirect to TCN login
    // Route: GET /tcn/connect/{encryptedUserId}   (admin-only)
    // ---------------------------------------------------------------

    public function userConnectRedirect(string $encryptedId): RedirectResponse
    {
        $userId = decrypt($encryptedId);

        $clientId    = Setting::getSecure('tcn_client_id', env('TCN_CLIENT_ID'));
        // Always use the static callback URI that is registered in TCN's OAuth app.
        // Dynamic URIs like /tcn/callback/{encryptedId} cause redirect_uri mismatch errors.
        $redirectUri = route('tcn.auth.callback');

        $state = Str::random(32);
        session([
            'tcn_oauth_state'         => $state,
            'tcn_oauth_user_id'       => $userId,       // resolved later in authCallback
            'tcn_oauth_encrypted_id'  => $encryptedId,  // for redirect back to user edit page
        ]);

        $url = 'https://auth.tcn.com/auth?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'scope'         => 'openid offline_access',
            'state'         => $state,
        ]);

        return redirect($url);
    }

    // ---------------------------------------------------------------
    // Per-user OAuth — Step B: Exchange code, fetch agent info, save
    // Route: GET /tcn/callback/{encryptedUserId}
    // ---------------------------------------------------------------

    public function userCallback(Request $request, string $encryptedId): RedirectResponse
    {
        $userId = decrypt($encryptedId);

        $code  = $request->query('code');
        $state = $request->query('state');

        if (!$code) {
            return redirect()->route('admin.users.edit', $encryptedId)
                ->with('error', 'TCN OAuth cancelled or failed.');
        }

        // CSRF state check
        if ($state && $state !== session('tcn_oauth_state')) {
            return redirect()->route('admin.users.edit', $encryptedId)
                ->with('error', 'TCN OAuth state mismatch. Please try again.');
        }

        // User ID binding check
        if ((int) session('tcn_oauth_user_id') !== (int) $userId) {
            return redirect()->route('admin.users.edit', $encryptedId)
                ->with('error', 'TCN OAuth user mismatch. Please try again.');
        }

        session()->forget(['tcn_oauth_state', 'tcn_oauth_user_id']);

        $clientId     = Setting::getSecure('tcn_client_id',     env('TCN_CLIENT_ID'));
        $clientSecret = Setting::getSecure('tcn_client_secret', env('TCN_CLIENT_SECRET'));
        $redirectUri  = route('tcn.user.callback', ['encryptedId' => $encryptedId]);

        // Exchange authorization code for tokens
        $tokenResp = Http::asForm()->post(self::AUTH_URL, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
        ]);

        if (!$tokenResp->successful()) {
            Log::error('TCN userCallback token exchange failed', [
                'user_id' => $userId,
                'body'    => $tokenResp->body(),
            ]);
            return redirect()->route('admin.users.edit', $encryptedId)
                ->with('error', 'TCN token exchange failed: ' . $tokenResp->body());
        }

        $tokenData    = $tokenResp->json();
        $accessToken  = $tokenData['access_token']  ?? null;
        $refreshToken = $tokenData['refresh_token'] ?? null;

        if (!$accessToken) {
            return redirect()->route('admin.users.edit', $encryptedId)
                ->with('error', 'TCN did not return an access token.');
        }

        // Fetch agent info using the fresh access token
        $agentResp = Http::withToken($accessToken)
            ->post(self::API_BASE . '/api/v0alpha/p3api/getcurrentagent', (object)[]);

        $agentId = null;
        if ($agentResp->successful()) {
            $agentData = $agentResp->json();
            $agentId   = $agentData['agentSid'] ?? $agentData['agent_id'] ?? null;
        } else {
            Log::warning('TCN userCallback: could not fetch agent', [
                'user_id' => $userId,
                'status'  => $agentResp->status(),
                'body'    => $agentResp->body(),
            ]);
        }

        // Fetch hunt group via agent skills
        $huntGroupId = null;
        if ($agentId) {
            $skillsResp = Http::withToken($accessToken)
                ->post(self::API_BASE . '/api/v0alpha/p3api/getagentskills', [
                    'huntGroupSid' => 0,
                    'agentSid'     => (int) $agentId,
                ]);

            if ($skillsResp->successful()) {
                $skillsData  = $skillsResp->json();
                $huntGroupId = $skillsData['huntGroupSid'] ?? $skillsData['hunt_group_id'] ?? null;
            } else {
                Log::warning('TCN userCallback: could not fetch skills', [
                    'user_id' => $userId,
                    'status'  => $skillsResp->status(),
                    'body'    => $skillsResp->body(),
                ]);
            }
        }

        // Persist — refresh_token encrypted, agent/hunt_group from API
        TcnUserAccount::saveForUser($userId, [
            'agent_id'      => $agentId      ? (string) $agentId      : null,
            'hunt_group_id' => $huntGroupId  ? (string) $huntGroupId  : null,
            'refresh_token' => $refreshToken,
        ]);

        Log::info('TCN user account connected via OAuth', [
            'user_id'       => $userId,
            'agent_id'      => $agentId,
            'hunt_group_id' => $huntGroupId,
        ]);

        return redirect()->route('admin.users.edit', $encryptedId)
            ->with('success', 'TCN account connected! Agent: ' . ($agentId ?? 'n/a') . ', Hunt Group: ' . ($huntGroupId ?? 'n/a'));
    }

    //â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Step 1 â€” Generate Access Token
    // client_secret stays on server; browser receives only access_token
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function token(): JsonResponse
    {
        try {
            /** @var \App\Services\Telephony\TcnService $svc */
            $svc   = TelephonyFactory::make('tcn');
            $token = $svc->generateAccessToken();

            if (!$token) {
                return response()->json(['error' => 'Failed to generate TCN access token'], 502);
            }

            return response()->json(['access_token' => $token]);
        } catch (\Throwable $e) {
            Log::error('TCN /token error', ['msg' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Step 2 â€” Get Current Agent
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function agent(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('access_token');

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/p3api/getcurrentagent', (object)[]);

        return response()->json($response->json(), $response->status());
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Step 3 â€” Get Agent Skills
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function skills(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('access_token');

        $payload = ['huntGroupSid' => (int) $request->input('huntGroupSid', 0)];

        // agentSid is required by TCN's getagentskills endpoint â€” 400 without it
        if ($request->filled('agentSid')) {
            $payload['agentSid'] = (int) $request->input('agentSid');
        }

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/p3api/getagentskills', $payload);

        return response()->json($response->json() ?? ['_raw' => $response->body()], $response->status());
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Step 4 â€” Create ASM Session
    // Returns SIP username, password, dial_url to browser
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function session(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('access_token');

        $payload = [
            'huntGroupSid'    => (int) $request->input('huntGroupSid', 0),
            'skills'          => $request->input('skills', (object)[]),
            'subsession_type' => 'VOICE',
        ];

        // For outbound calls: pass destination phone number so TCN can configure
        // the PSTN leg on their side before the SIP INVITE arrives.
        if ($request->filled('phoneNumber')) {
            $payload['phoneNumber'] = $request->input('phoneNumber');
            $payload['countryCode'] = $request->input('countryCode', '91');
        }

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v1alpha1/asm/asm/createsession', $payload);

        return response()->json($response->json(), $response->status());
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Step 5 â€” Get Huntgroup Settings (caller ID / dial settings)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function huntgroupSettings(Request $request): JsonResponse
    {
        $token    = $request->bearerToken() ?? $request->input('access_token');
        $endpoint = self::API_BASE . '/api/v0alpha/p3api/gethuntgroupsettings';
        $payload  = ['huntGroupSid' => (int) $request->input('huntGroupSid', 0)];

        Log::info('TCN huntgroupSettings â†’', ['endpoint' => $endpoint, 'payload' => $payload]);

        $response = Http::withToken($token)->post($endpoint, $payload);

        Log::info('TCN huntgroupSettings â†', [
            'status'            => $response->status(),
            'body'              => $response->body(),
            'is_default_backend' => str_contains($response->body(), 'default backend'),
        ]);

        $json = $response->json() ?? ['_raw' => $response->body()];
        return response()->json($json, $response->status());
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Keep Alive â€” every 30 seconds
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function keepalive(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');

        if (blank($sessionSid) || (string) $sessionSid === '0') {
            Log::warning('TCN keepalive rejected invalid sessionSid', [
                'sessionSid' => $sessionSid,
            ]);

            return response()->json([
                'keepAliveSucceeded' => false,
                'statusDesc' => 'INVALID_SESSION',
                'currentSessionId' => 0,
                'message' => 'Missing or invalid sessionSid',
            ], 422);
        }

        Log::info('TCN keepalive request', [
            'sessionSid' => (string) $sessionSid,
        ]);

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/acd/agentgetstatus', [
                'sessionSid'       => (string) $sessionSid,
                'performKeepAlive' => true,   // boolean â€” NOT the string "true"
            ]);
        Log::info('TCN keepalive response', [
            'requestedSessionSid' => (string) $sessionSid,
            'status' => $response->status(),
            'body' => $response->json() ?? ['_raw' => $response->body()],
        ]);


        return response()->json($response->json(), $response->status());
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Agent Status (used before click-to-call to refresh session ID)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function agentStatus(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/acd/agentgetstatus', [
                'sessionSid'       => (string) $sessionSid,
                'performKeepAlive' => 'false',
            ]);

        return response()->json($response->json(), $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Set Agent Status — pause (UNAVAILABLE) or resume (READY)
    // Route: POST /tcn/set-status
    // Generates a fresh server-side access_token — browser never needs secrets.
    // ─────────────────────────────────────────────────────────────────────

    public function setAgentStatus(Request $request): JsonResponse
    {
        $status = strtoupper($request->input('status', 'READY'));
        if (!in_array($status, ['READY', 'UNAVAILABLE'])) {
            return response()->json(['error' => 'status must be READY or UNAVAILABLE'], 422);
        }

        $user    = Auth::user();
        $account = TcnUserAccount::forUser($user->id);

        if (!$account || blank($account->refresh_token_plain)) {
            return response()->json(['error' => 'TCN account not configured', 'configured' => false], 422);
        }

        $clientId     = Setting::getSecure('tcn_client_id',     env('TCN_CLIENT_ID'));
        $clientSecret = Setting::getSecure('tcn_client_secret', env('TCN_CLIENT_SECRET'));

        $tokenResp = Http::asForm()->post(self::AUTH_URL, [
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $account->refresh_token_plain,
        ]);

        if (!$tokenResp->successful()) {
            Log::error('TCN setAgentStatus: token refresh failed', [
                'user_id' => $user->id,
                'body'    => $tokenResp->body(),
            ]);
            return response()->json(['error' => 'Token refresh failed'], 500);
        }

        $accessToken = $tokenResp->json()['access_token'] ?? null;
        if (!$accessToken) {
            return response()->json(['error' => 'No access_token in refresh response'], 500);
        }

        $endpoint = self::API_BASE . '/api/v0alpha/acd/setagentstatus';
        $payload  = [
            'agentSid'   => (int) ($account->agent_id ?? 0),
            'statusCode' => $status,
        ];

        $resp = Http::withToken($accessToken)->post($endpoint, $payload);

        Log::info('TCN set-agent-status', [
            'user_id'  => $user->id,
            'status'   => $status,
            'http'     => $resp->status(),
            'body'     => $resp->body(),
        ]);

        if (!$resp->successful()) {
            // Return 200 with warning so widget can still toggle locally
            return response()->json([
                'ok'        => false,
                'warning'   => 'TCN returned ' . $resp->status() . ' — local status toggled only',
                '_endpoint' => $endpoint,
                '_http'     => $resp->status(),
            ]);
        }

        return response()->json(['ok' => true, 'status' => $status]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Click-to-Call Step 1 â€” Dial Manual Prepare
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function dialPrepare(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');
        $endpoint   = self::API_BASE . '/api/v1alpha1/asm/manualdial/prepare';
        $payload    = ['sessionSid' => (string) $sessionSid];

        Log::info('TCN dialPrepare â†’', ['endpoint' => $endpoint, 'payload' => $payload]);

        $response = Http::withToken($token)->post($endpoint, $payload);

        Log::info('TCN dialPrepare â†', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if (!$response->successful()) {
            Log::error('TCN dialPrepare FAILED', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        $json = $response->json() ?? ['_raw' => $response->body()];
        return response()->json($json, $response->status());
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Click-to-Call Step 2 â€” Process Manual Dial
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function dialProcess(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');
        $phone      = $request->input('phone');
        $endpoint   = self::API_BASE . '/api/v1alpha1/asm/manualdial/process';
        $payload    = [
            'sessionSid'  => (string) $sessionSid,
            'phoneNumber' => (string) $request->input('phoneNumber', $phone),
            'countryCode' => $request->input('countryCode', '91'),
        ];

        Log::info('TCN dialProcess â†’', ['endpoint' => $endpoint, 'payload' => $payload]);

        $response = Http::withToken($token)->post($endpoint, $payload);

        Log::info('TCN dialProcess â†', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if (!$response->successful()) {
            Log::error('TCN dialProcess FAILED', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        $json = $response->json() ?? ['_raw' => $response->body()];
        return response()->json($json, $response->status());
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Click-to-Call Step 3 â€” Manual Dial Start
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function dialStart(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');
        $endpoint   = self::API_BASE . '/api/v1alpha1/asm/manualdial/start';

        $payload = [
            'sessionSid'          => (string) $sessionSid,
            'callerIdCountryCode' => $request->input('callerIdCountryCode', '91'),
            'countryCode'         => $request->input('countryCode', '91'),
            'phoneNumber'         => $request->input('phoneNumber', $request->input('phone')),
            'callerIdPhoneNumber' => $request->input('callerIdPhoneNumber', ''),
            'huntGroupSid'        => (int) $request->input('huntGroupSid', 0),
        ];

        Log::info('TCN dialStart â†’', ['endpoint' => $endpoint, 'payload' => $payload]);

        $response = Http::withToken($token)->post($endpoint, $payload);

        Log::info('TCN dialStart â†', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if (!$response->successful()) {
            Log::error('TCN dialStart FAILED', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        $json = $response->json() ?? ['_raw' => $response->body()];
        return response()->json($json, $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Outbound Call — 3-step manualdial (prepare → process → start)
    //
    // All three steps use the confirmed-working v1alpha1/asm/ namespace.
    // Step 1 (prepare) — initialises the dial context.
    // Step 2 (process) — attaches phoneNumber + countryCode.
    // Step 3 (start)   — triggers the PSTN call; TCN sends a SIP INVITE
    //                    to the agent UA to bridge audio.
    // ─────────────────────────────────────────────────────────────────────

    public function dial(Request $request): JsonResponse
    {
        // Accept both 'phone' (preferred) and 'phoneNumber' (legacy softphone field)
        $rawPhone   = $request->input('phone') ?? $request->input('phoneNumber', '');
        $sessionSid = $request->input('sessionSid');

        // ── Validate ──────────────────────────────────────────────
        if (blank($rawPhone)) {
            return response()->json(['error' => 'phone is required.'], 422);
        }

        // Normalise to E.164 (+91XXXXXXXXXX)
        $digits = preg_replace('/\D/', '', $rawPhone);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) < 7) {
            return response()->json(['error' => 'Invalid phone number: ' . $rawPhone], 422);
        }
        $e164 = '+91' . $digits;

        // ── Per-user TCN config ────────────────────────────────────
        // Never trust the access_token from the browser — it may be expired.
        // Always generate a fresh one server-side from the stored refresh_token.
        $user    = Auth::user();
        $account = TcnUserAccount::forUser($user->id);

        if (!$account || blank($account->refresh_token_plain)) {
            return response()->json([
                'error'      => 'TCN account not configured. Ask admin to connect your TCN account.',
                'configured' => false,
            ], 422);
        }

        $clientId     = Setting::getSecure('tcn_client_id',     env('TCN_CLIENT_ID'));
        $clientSecret = Setting::getSecure('tcn_client_secret', env('TCN_CLIENT_SECRET'));

        if (blank($clientId) || blank($clientSecret)) {
            return response()->json(['error' => 'TCN global credentials not configured.'], 422);
        }

        // ── Generate fresh access_token ────────────────────────────
        $tokenResp = Http::asForm()->post(self::AUTH_URL, [
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $account->refresh_token_plain,
        ]);

        if (!$tokenResp->successful()) {
            Log::error('TCN dial: token exchange failed', [
                'user_id' => $user->id,
                'status'  => $tokenResp->status(),
                'body'    => $tokenResp->body(),
            ]);
            return response()->json(['error' => 'Failed to generate TCN access token. Please reconnect.'], 502);
        }

        $accessToken = $tokenResp->json()['access_token'] ?? null;
        if (!$accessToken) {
            return response()->json(['error' => 'Empty access_token from TCN.'], 502);
        }

        // ── Build payload ──────────────────────────────────────────
        $agentId      = $account->agent_id;
        $huntGroupSid = (int) ($account->hunt_group_id ?? 0);
        $callerId     = Setting::get('tcn_caller_id', env('TCN_CALLER_ID', ''));

        $payload = [
            'phoneNumber'  => $e164,
            'huntGroupSid' => $huntGroupSid,
            'callerId'     => $callerId,
            'countryCode'  => '91',
        ];
        if ($agentId) {
            $payload['agentSid'] = (int) $agentId;
        }
        if ($sessionSid) {
            $payload['sessionSid'] = (string) $sessionSid;
        }

        $endpoint = self::API_BASE . '/api/v1alpha1/asm/asm/manualdial/execute';

        Log::info('TCN dial →', [
            'user_id'  => $user->id,
            'agent_id' => $agentId,
            'endpoint' => $endpoint,
            'payload'  => $payload,
        ]);

        // ── Call TCN ───────────────────────────────────────────────
        $resp = Http::withToken($accessToken)->post($endpoint, $payload);

        Log::info('TCN dial ←', [
            'status' => $resp->status(),
            'body'   => $resp->body(),
        ]);

        if (!$resp->successful()) {
            Log::error('TCN dial FAILED', [
                'user_id'  => $user->id,
                'status'   => $resp->status(),
                'body'     => $resp->body(),
                'endpoint' => $endpoint,
            ]);
            return response()->json(
                $resp->json() ?? ['error' => 'TCN dial failed', '_raw' => $resp->body(), '_endpoint' => $endpoint],
                $resp->status()
            );
        }

        return response()->json($resp->json() ?? ['success' => true]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Diagnostic â€” probe all candidate URLs for manualdial/prepare
    //
    // Official TCN API doc (v1.2) only documents 5 APIs â€” none are
    // manualdial. However, the installation guide describes a 4-step
    // click-to-call flow. This probe finds the real endpoint.
    //
    // Observations so far:
    //  - v1alpha1/asm/asm/manualdial/prepare  â†’ 404 "default backend"
    //  - v1alpha1/asm/manualdial/prepare      â†’ 404 "default backend"
    //
    // The doc shows ALL working acd endpoints use v0alpha/acd/.
    // The doc confirms sessionSid = voiceSessionSid (string) for acd calls.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function dialDebug(Request $request): JsonResponse
    {
        $token    = $request->bearerToken() ?? $request->input('access_token');
        $asmSid   = $request->input('asmSessionSid');
        $voiceSid = $request->input('voiceSessionSid');

        // Test two SID variants:
        // - asmSessionSid  (asm namespace hint)
        // - voiceSessionSid (what the official doc says to use for acd calls)
        $sidVariants = [
            'asm'   => (string) $asmSid,
            'voice' => (string) $voiceSid,
        ];

        // Candidate paths to probe â€” path only (base host appended below)
        $paths = [
            // v1alpha1/asm (single asm) — same namespace as prepare/process/start
            'v1a1_asm_execute'         => '/api/v1alpha1/asm/manualdial/execute',
            'v1a1_asm_prepare'         => '/api/v1alpha1/asm/manualdial/prepare',
            'v1a1_asm_process'         => '/api/v1alpha1/asm/manualdial/process',
            'v1a1_asm_start'           => '/api/v1alpha1/asm/manualdial/start',
            // v0alpha/acd — same namespace as agentgetstatus + agentdisconnect
            'v0a_acd_manualdialstart'  => '/api/v0alpha/acd/manualdialstart',
            'v0a_acd_manualdial_start' => '/api/v0alpha/acd/manualdial/start',
            'v0a_acd_manualdial_exec'  => '/api/v0alpha/acd/manualdial/execute',
            'v0a_acd_manualdial_pre'   => '/api/v0alpha/acd/manualdial/prepare',
            'v0a_acd_agentmanualdial'  => '/api/v0alpha/acd/agentmanualdial',
            // v0alpha/p3api — same namespace as getcurrentagent / getagentskills
            'v0a_p3_manualdial'        => '/api/v0alpha/p3api/manualdial',
            // v1alpha1/asm/asm — same namespace as createsession (double asm)
            'v1a1_asm_asm_execute'     => '/api/v1alpha1/asm/asm/manualdial/execute', // ← PRIMARY (used by dial())
            'v1a1_asm_asm_manualdial'  => '/api/v1alpha1/asm/asm/manualdial',
            'v1a1_asm_asm_pre'         => '/api/v1alpha1/asm/asm/manualdial/prepare',
        ];

        // Candidate base hosts â€” "bom" datacenter prefix may not apply to all services
        $bases = [
            'bom'   => 'https://api.bom.tcn.com',
            'tcn'   => 'https://api.tcn.com',
            'tcnp3' => 'https://api.tcnp3.com',
        ];

        $results = [];
        foreach ($bases as $baseLabel => $base) {
            foreach ($paths as $pathLabel => $path) {
                $url = $base . $path;
                foreach ($sidVariants as $sidLabel => $sidValue) {
                    $key     = $baseLabel . '|' . $pathLabel . '|' . $sidLabel . 'Sid';
                    $payload = ['sessionSid' => $sidValue];

                    try {
                        $r    = Http::withToken($token)->timeout(5)->post($url, $payload);
                        $body = $r->body();
                        $isDefaultBackend = ($r->status() === 404 && str_contains($body, 'default backend'));

                        $results[$key] = [
                            'url'         => $url,
                            'sessionSid'  => $sidValue,
                            'status'      => $r->status(),
                            'path_exists' => !$isDefaultBackend,
                            'body'        => $body,
                        ];

                        Log::info("TCN dialDebug [{$key}]", [
                            'url'         => $url,
                            'sessionSid'  => $sidValue,
                            'status'      => $r->status(),
                            'path_exists' => !$isDefaultBackend,
                            'body'        => $body,
                        ]);
                    } catch (\Throwable $e) {
                        $results[$key] = [
                            'url'         => $url,
                            'sessionSid'  => $sidValue,
                            'status'      => 0,
                            'path_exists' => false,
                            'body'        => 'Connection error: ' . $e->getMessage(),
                        ];
                        Log::warning("TCN dialDebug [{$key}] connection error", ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        // Highlight any path that is NOT "default backend" â€” those exist on TCN's gateway
        $found = array_filter($results, fn($r) => $r['path_exists']);

        return response()->json([
            'summary'     => empty($found) ? 'ALL paths returned default backend - 404' : array_keys($found),
            'found_count' => count($found),
            'results'     => $results,
        ]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Agent Disconnect (end call)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function disconnect(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/acd/agentdisconnect', [
                'sessionSid' => (string) $sessionSid,
            ]);

        return response()->json($response->json(), $response->status());
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Call Log â€” create entry when call starts
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function createCallLog(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => 'nullable|integer|exists:leads,id',
            'phone'   => 'required|string|max:20',
            'call_sid' => 'nullable|string|max:255',
        ]);

        $lead = $data['lead_id'] ? Lead::findOrFail($data['lead_id']) : null;

        $callSid = filled($data['call_sid'] ?? null) ? (string) $data['call_sid'] : null;

        try {
            if ($callSid) {
                $existing = CallLog::query()
                    ->where('user_id', Auth::id())
                    ->where('provider', 'tcn')
                    ->where('call_sid', $callSid)
                    ->latest('id')
                    ->first();

                if ($existing) {
                    return response()->json([
                        'call_log_id' => $existing->id,
                        'existing'    => true,
                    ]);
                }
            }

            $callLog = CallLog::create([
                'lead_id'         => $lead?->id,
                'user_id'         => Auth::id(),
                'provider'        => 'tcn',
                'call_sid'        => $callSid,
                'customer_number' => $data['phone'],
                'direction'       => 'outbound',
                'status'          => 'initiated',
            ]);

            return response()->json(['call_log_id' => $callLog->id]);
        } catch (\Throwable $e) {
            Log::error('TCN createCallLog DB error', [
                'user_id' => Auth::id(),
                'phone'   => $data['phone'],
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to create call log: ' . $e->getMessage()], 500);
        }
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Call Log â€” update when call ends
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function updateCallLog(Request $request, int $id): JsonResponse
    {
        $callLog = CallLog::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $data = $request->validate([
            'status'      => 'nullable|in:initiated,ringing,answered,completed,failed,canceled,rejected,missed',
            'outcome'     => 'nullable|in:interested,not_interested,wrong_number,call_back_later,switched_off',
            'duration'    => 'nullable|integer|min:0',
            'answered_at' => 'nullable|string',
            'ended_at'    => 'nullable|string',
            'call_sid'    => 'nullable|string|max:255',
            'end_reason'  => 'nullable|string|max:255',
        ]);

        $status = $data['status'] ?? $callLog->status;

        $answeredAt = array_key_exists('answered_at', $data) && filled($data['answered_at'])
            ? Carbon::parse($data['answered_at'])
            : $callLog->answered_at;

        $endedAt = array_key_exists('ended_at', $data) && filled($data['ended_at'])
            ? Carbon::parse($data['ended_at'])
            : $callLog->ended_at;

        $duration = array_key_exists('duration', $data)
            ? (int) $data['duration']
            : $callLog->duration;

        if ($status === 'answered' && !$answeredAt) {
            $answeredAt = now('Asia/Kolkata');
        }

        if ($status === 'completed') {
            if (!$answeredAt) {
                throw ValidationException::withMessages([
                    'status' => 'A completed TCN call must include answered_at.',
                ]);
            }

            if (!$endedAt) {
                $endedAt = now('Asia/Kolkata');
            }

            if ($duration === null) {
                $duration = $answeredAt->diffInSeconds($endedAt);
            }

            if ($duration < 1) {
                $status = 'failed';
            }
        }

        if (in_array($status, ['failed', 'canceled', 'rejected', 'missed'], true)) {
            if (!$endedAt) {
                $endedAt = now('Asia/Kolkata');
            }

            if (!$answeredAt) {
                $duration = 0;
            } elseif ($duration === null) {
                $duration = $answeredAt->diffInSeconds($endedAt);
            }
        }

        if (($duration ?? 0) < 1 && in_array($status, ['answered', 'completed'], true) && $endedAt) {
            $status = 'failed';
            $duration = 0;
        }

        if ($endedAt && $answeredAt && $endedAt->lt($answeredAt)) {
            throw ValidationException::withMessages([
                'ended_at' => 'ended_at cannot be earlier than answered_at.',
            ]);
        }

        if (($duration ?? 0) > 0 && !$answeredAt) {
            throw ValidationException::withMessages([
                'duration' => 'Duration cannot be greater than zero when answered_at is missing.',
            ]);
        }

        if (array_key_exists('status', $data))      $callLog->status      = $status;
        if (array_key_exists('outcome', $data))     $callLog->outcome     = $data['outcome'];
        if (array_key_exists('duration', $data) || $duration !== $callLog->duration) {
            $callLog->duration = $duration;
        }
        if (array_key_exists('call_sid', $data) && filled($data['call_sid'])) {
            $callLog->call_sid = $data['call_sid'];
        }
        if (array_key_exists('end_reason', $data)) {
            $callLog->end_reason = $data['end_reason'];
        }
        if (array_key_exists('answered_at', $data) || ($status === 'answered' && !$callLog->answered_at)) {
            $callLog->answered_at = $answeredAt;
        }
        if (array_key_exists('ended_at', $data) || in_array($status, ['completed', 'failed', 'canceled', 'rejected', 'missed'], true)) {
            $callLog->ended_at = $endedAt;
        }

        $callLog->save();

        return response()->json(['success' => true]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Test Page â€” serve the standalone diagnostic console
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function testPage()
    {
        return view('admin.tcn-test');
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Test Token â€” generate token from manually-entered credentials
    // (admin-only; credentials passed in request body, never stored)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function testToken(Request $request): JsonResponse
    {
        $clientId     = $request->input('client_id')     ?: Setting::getSecure('tcn_client_id',     env('TCN_CLIENT_ID'));
        $clientSecret = $request->input('client_secret') ?: Setting::getSecure('tcn_client_secret', env('TCN_CLIENT_SECRET'));
        $refreshToken = $request->input('refresh_token') ?: Setting::getSecure('tcn_refresh_token', env('TCN_REFRESH_TOKEN'));

        if (!$clientId || !$clientSecret || !$refreshToken) {
            return response()->json(['error' => 'client_id, client_secret, and refresh_token are required.'], 422);
        }

        $response = Http::asForm()->post(self::AUTH_URL, [
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        Log::info('TCN test token request', ['status' => $response->status()]);

        return response()->json($response->json(), $response->status());
    }

    // ---------------------------------------------------------------
    // Softphone iframe page — renders the floating softphone UI.
    // Loaded once per session inside <iframe src=”/softphone”>.
    // ---------------------------------------------------------------

    public function softphonePage(): \Illuminate\View\View
    {
        return view('softphone');
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Config â€” return non-sensitive TCN config to the browser
    // (client_id only â€” client_secret stays server-side)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function config(): JsonResponse
    {
        return response()->json([
            'client_id'    => Setting::getSecure('tcn_client_id', env('TCN_CLIENT_ID')),
            'redirect_uri' => Setting::get('tcn_redirect_uri', env('TCN_REDIRECT_URI')),
            'caller_id'    => Setting::get('tcn_caller_id', env('TCN_CALLER_ID', '')),
        ]);
    }

    // ---------------------------------------------------------------
    // GET /api/tcn/config
    // Returns access_token + agent/hunt_group info for the logged-in
    // user.  Uses per-user refresh_token + global client credentials.
    // NEVER exposes client_secret or refresh_token to the browser.
    // ---------------------------------------------------------------

    public function userConfig(): JsonResponse
    {
        $user    = Auth::user();
        $account = TcnUserAccount::forUser($user->id);

        if (!$account || blank($account->refresh_token_plain)) {
            return response()->json([
                'error' => 'TCN account not configured for this user.',
                'configured' => false,
            ], 422);
        }

        // Global credentials (server-side only)
        $clientId     = Setting::getSecure('tcn_client_id',     env('TCN_CLIENT_ID'));
        $clientSecret = Setting::getSecure('tcn_client_secret', env('TCN_CLIENT_SECRET'));
        $authUrl      = Setting::get('tcn_auth_url', self::AUTH_URL);

        if (blank($clientId) || blank($clientSecret)) {
            return response()->json([
                'error' => 'TCN global credentials not configured.',
                'configured' => false,
            ], 422);
        }

        try {
            $cacheKey = 'tcn:user_access_token:' . $user->id;
            $cachedToken = Cache::get($cacheKey);

            if (is_array($cachedToken) && filled($cachedToken['access_token'] ?? null)) {
                $accessToken = (string) $cachedToken['access_token'];
                $expiresIn   = (int) ($cachedToken['expires_in'] ?? 3300);
            } else {
                $response = Http::asForm()->post($authUrl, [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $account->refresh_token_plain,
                ]);

                if (!$response->successful()) {
                    Log::error('TCN userConfig token exchange failed', [
                        'user_id' => $user->id,
                        'status'  => $response->status(),
                        'body'    => $response->body(),
                    ]);
                    return response()->json([
                        'error' => 'Token exchange failed. Please reconnect your TCN account.',
                        'configured' => true,
                    ], 502);
                }

                $tokenData   = $response->json();
                $accessToken = $tokenData['access_token'] ?? null;
                $expiresIn   = max(60, (int) ($tokenData['expires_in'] ?? 3600));

                if (blank($accessToken)) {
                    return response()->json(['error' => 'Empty access_token from TCN.'], 502);
                }

                Cache::put($cacheKey, [
                    'access_token' => $accessToken,
                    'expires_in'   => $expiresIn,
                ], now()->addSeconds(max(60, $expiresIn - 300)));
            }
        } catch (\Throwable $e) {
            Log::error('TCN userConfig exception', ['user_id' => $user->id, 'msg' => $e->getMessage()]);
            return response()->json(['error' => 'Token generation failed.'], 500);
        }

        // Return only what the browser needs — no secrets
        return response()->json([
            'configured'     => true,
            'access_token'   => $accessToken,
            'expires_in'     => $expiresIn ?? 3300,
            'agent_id'       => $account->agent_id,
            'hunt_group_id'  => $account->hunt_group_id,
            'tcn_username'   => $account->tcn_username,
            'caller_id'      => Setting::get('tcn_caller_id', env('TCN_CALLER_ID', '')),
        ]);
    }
}
