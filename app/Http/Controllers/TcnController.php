<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\TcnUserAccount;
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

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Step 2 â€” Get Agent Skills
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
    // Agent Disconnect (end call)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function disconnect(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/acd/agentdisconnect', [
                'sessionSid' => (string) $sessionSid,
                'reason'     => 'endcall',
            ]);

        return response()->json($response->json(), $response->status());
    }

    // ─────────────────────────────────────────────────────────────
    // Hold — PUT simple hold on the active call (Operator API)
    // ─────────────────────────────────────────────────────────────

    public function hold(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');
        $holdType   = strtoupper($request->input('holdType', 'SIMPLE'));
        if (!in_array($holdType, ['SIMPLE', 'MULTI'])) $holdType = 'SIMPLE';

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/acd/agentputcallonhold', [
                'sessionSid' => (string) $sessionSid,
                'holdType'   => $holdType,
            ]);

        return response()->json($response->json() ?? [], $response->status());
    }

    // ─────────────────────────────────────────────────────────────
    // Resume — take call off hold (Operator API)
    // ─────────────────────────────────────────────────────────────

    public function resume(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/acd/agentgetcallfromhold', [
                'sessionSid' => (string) $sessionSid,
            ]);

        return response()->json($response->json() ?? [], $response->status());
    }

    // ─────────────────────────────────────────────────────────────
    // DTMF — send tone during active call (Operator API)
    // ─────────────────────────────────────────────────────────────

    public function dtmf(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');
        $digit      = (string) $request->input('digit');

        // TCN spec: * = 10, # = 11, digits 0-9 as integer
        $digitMap = ['*' => 10, '#' => 11];
        $tone = isset($digitMap[$digit]) ? $digitMap[$digit] : (int) $digit;

        $response = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/acd/playdtmf?sessionSid=' . urlencode((string) $sessionSid), [
                'dtmfDigits' => [$tone],
            ]);

        return response()->json($response->json() ?? [], $response->status());
    }

    // ─────────────────────────────────────────────────────────────
    // Manual Dial — unified 3-step Operator API call initiation
    // Route: POST /tcn/dial
    //
    // Steps:
    //   1. dialmanualprepare     (sessionSid)
    //   2. processmanualdialcall (phone, agentSid, clientSid, callerId…)
    //   3. manualdialstart       (agentSessionSid, huntGroupSid, simpleCallData)
    //
    // Requires TCN_CLIENT_SID in .env or tcn_client_sid in settings.
    // ─────────────────────────────────────────────────────────────

    public function dial(Request $request): JsonResponse
    {
        $token      = $request->bearerToken() ?? $request->input('access_token');
        $sessionSid = $request->input('sessionSid');
        $rawPhone   = preg_replace('/\D/', '', (string) $request->input('phone', ''));

        // Normalise to exactly 10 local digits (strip leading country code "91" if present).
        // phoneNumber in TCN API must be 10 digits; countryCode is a separate field.
        // Sending 12 digits (e.g. 916383702482) + countryCode="91" = duplicate → "Invalid".
        $phone = $rawPhone;
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) !== 10) {
            return response()->json([
                'error' => 'Phone must be exactly 10 local digits (without country code). Got: ' . $rawPhone,
            ], 422);
        }

        if (blank($sessionSid)) {
            return response()->json(['error' => 'sessionSid and phone are required'], 422);
        }

        $user    = Auth::user();
        $account = TcnUserAccount::forUser($user->id);

        if (!$account) {
            return response()->json(['error' => 'TCN account not configured for this user'], 422);
        }

        $agentSid     = (int) ($account->agent_id    ?? 0);
        $huntGroupSid = (int) ($account->hunt_group_id ?? 0);
        $callerId     = Setting::get('tcn_caller_id',  env('TCN_CALLER_ID', ''));
        $clientSid    = Setting::get('tcn_client_sid', env('TCN_CLIENT_SID', ''));
        $countryCode  = '91';
        $countrySid   = '10'; // India

        if (blank($clientSid)) {
            return response()->json([
                'error' => 'TCN client SID not configured. Add TCN_CLIENT_SID=<your_client_sid> to .env or set tcn_client_sid in admin settings.',
            ], 422);
        }

        // ── Step 1: Prepare manual dial ───────────────────────────
        $prepareResp = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/acd/dialmanualprepare', [
                'sessionSid' => (string) $sessionSid,
            ]);

        Log::info('TCN dialmanualprepare', [
            'sessionSid' => $sessionSid,
            'http'       => $prepareResp->status(),
            'body'       => $prepareResp->body(),
        ]);

        if (!$prepareResp->successful()) {
            return response()->json([
                'error'      => 'dialmanualprepare failed',
                'tcn_status' => $prepareResp->status(),
                'tcn_body'   => $prepareResp->json() ?? ['_raw' => $prepareResp->body()],
            ], $prepareResp->status() ?: 500);
        }

        // ── Step 2: Process manual dial ───────────────────────────
        $processResp = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/callqueue/processmanualdialcall', [
                'call' => [
                    'agentSid'             => $agentSid,
                    'callerId'             => $callerId,
                    'clientSid'            => (string) $clientSid,
                    'doRecord'             => 'true',
                    'phoneNumber'          => $phone,
                    'callerIdCountryCode'  => $countryCode,
                    'countryCode'          => $countryCode,
                    'countrySid'           => $countrySid,
                    'doDnclScrub'          => 'true',
                    'callDataType'         => 'manual',
                    'doCellPhoneScrub'     => 'false',
                    'callerIdCountrySid'   => $countrySid,
                ],
            ]);

        $processData = $processResp->json() ?? [];

        // Extract validation scrub flags — these are the key indicators of why
        // TCN marks a call "Invalid" and drops it immediately to WRAPUP with 0 min.
        $scrubbedCall       = $processData['scrubbedCall'] ?? $processData ?? [];
        $isDialValidationOk = $scrubbedCall['isDialValidationOk'] ?? null;
        $isDnclScrubOk      = $scrubbedCall['isDnclScrubOk']      ?? null;
        $isTimeZoneScrubOk  = $scrubbedCall['isTimeZoneScrubOk']  ?? null;
        $callSid            = $scrubbedCall['callSid']             ?? null;
        $taskGroupSid       = $scrubbedCall['taskGroupSid']        ?? null;

        Log::info('TCN processmanualdialcall', [
            'phone'               => $phone,
            'http'                => $processResp->status(),
            'isDialValidationOk'  => $isDialValidationOk,
            'isDnclScrubOk'       => $isDnclScrubOk,
            'isTimeZoneScrubOk'   => $isTimeZoneScrubOk,
            'callSid'             => $callSid,
            'body'                => $processResp->body(),
        ]);

        if (!$processResp->successful()) {
            return response()->json([
                'error'      => 'processmanualdialcall failed',
                'tcn_status' => $processResp->status(),
                'tcn_body'   => $processData,
            ], $processResp->status() ?: 500);
        }

        // Hard-stop: if TCN's own validation rejected the call there is no point
        // proceeding to manualdialstart — the call will be "Invalid" with 0 duration.
        if ($isDialValidationOk === false) {
            $reason = match(true) {
                $isDnclScrubOk === false     => 'Number is on the DNCL (Do Not Call List)',
                $isTimeZoneScrubOk === false => 'Call blocked by timezone scrub (outside allowed hours)',
                default                      => 'TCN dial validation failed (isDialValidationOk=false)',
            };
            Log::warning('TCN call blocked by validation', [
                'phone'              => $phone,
                'isDialValidationOk' => $isDialValidationOk,
                'isDnclScrubOk'      => $isDnclScrubOk,
                'isTimeZoneScrubOk'  => $isTimeZoneScrubOk,
            ]);
            return response()->json([
                'error'              => $reason,
                'validationError'    => $reason,
                'isDialValidationOk' => $isDialValidationOk,
                'isDnclScrubOk'      => $isDnclScrubOk,
                'isTimeZoneScrubOk'  => $isTimeZoneScrubOk,
                'tcn_body'           => $processData,
            ], 422);
        }

        if (blank($callSid)) {
            return response()->json([
                'error'    => 'processmanualdialcall did not return a callSid',
                'tcn_body' => $processData,
            ], 500);
        }

        // ── Step 3: Start manual dial ─────────────────────────────
        $startResp = Http::withToken($token)
            ->post(self::API_BASE . '/api/v0alpha/p3api/manualdialstart', [
                'agentSessionSid' => (int) $sessionSid,
                'huntGroupSid'    => $huntGroupSid,
                'simpleCallData'  => [
                    'callSid'             => (int) $callSid,
                    'agentSid'            => $agentSid,
                    'taskGroupSid'        => (int) ($taskGroupSid ?? 0),
                    'callerId'            => $callerId,
                    'clientSid'           => (int) $clientSid,
                    'doRecord'            => true,
                    'phoneNumber'         => $phone,
                    'callerIdCountryCode' => $countryCode,
                    'countryCode'         => $countryCode,
                    'callDataType'        => 'manual',
                    'callerIdCountrySid'  => (int) $countrySid,
                    'countrySid'          => (int) $countrySid,
                ],
            ]);

        Log::info('TCN manualdialstart', [
            'sessionSid'   => $sessionSid,
            'phone'        => $phone,
            'callSid'      => $callSid,
            'taskGroupSid' => $taskGroupSid,
            'http'         => $startResp->status(),
            'body'         => $startResp->body(),
        ]);

        return response()->json([
            'ok'                 => $startResp->successful(),
            'callSid'            => $callSid,
            'taskGroupSid'       => $taskGroupSid,
            'sessionSid'         => $sessionSid,
            'isDialValidationOk' => $isDialValidationOk,
            'isDnclScrubOk'      => $isDnclScrubOk,
            'isTimeZoneScrubOk'  => $isTimeZoneScrubOk,
            'tcn_status'         => $startResp->status(),
            'tcn_body'           => $startResp->json() ?? ['_raw' => $startResp->body()],
        ], $startResp->successful() ? 200 : ($startResp->status() ?: 500));
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
