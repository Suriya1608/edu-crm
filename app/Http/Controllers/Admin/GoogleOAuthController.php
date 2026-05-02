<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GoogleMeetService;
use Illuminate\Http\Request;

class GoogleOAuthController extends Controller
{
    public function __construct(private readonly GoogleMeetService $meetService) {}

    public function settings()
    {
        $clientId = Setting::getSecure('google_client_id', config('services.google.client_id', ''));

        return view('admin.settings.google-meet', [
            'connected'  => $this->meetService->isConnected(),
            'client_id'  => $clientId,
            'has_client' => (bool) $clientId,
        ]);
    }

    public function saveCredentials(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
        ]);

        Setting::setSecure('google_client_id',     $request->client_id);
        Setting::setSecure('google_client_secret', $request->client_secret);

        return back()->with('success', 'Google credentials saved. Click "Connect Google Account" to authorize.');
    }

    public function redirect()
    {
        $clientId = Setting::getSecure('google_client_id', config('services.google.client_id', ''));
        if (!$clientId) {
            return back()->with('error', 'Google Client ID not configured. Please save credentials first.');
        }

        $url = $this->meetService->getAuthUrl(route('admin.google.callback'));
        return redirect($url);
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('admin.settings.google-meet')
                ->with('error', 'Google authorization denied: ' . $request->error);
        }

        $ok = $this->meetService->handleCallback(
            $request->code,
            route('admin.google.callback')
        );

        if (!$ok) {
            return redirect()->route('admin.settings.google-meet')
                ->with('error', 'Token exchange failed. Check your credentials and try again.');
        }

        return redirect()->route('admin.settings.google-meet')
            ->with('success', 'Google account connected successfully! Meet links will now be generated automatically.');
    }

    public function disconnect()
    {
        Setting::set('google_access_token',  null);
        Setting::set('google_refresh_token', null);
        Setting::set('google_token_expires_at', null);

        return back()->with('success', 'Google account disconnected.');
    }
}
