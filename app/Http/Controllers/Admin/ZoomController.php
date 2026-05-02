<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ZoomService;
use Illuminate\Http\Request;

class ZoomController extends Controller
{
    public function __construct(private readonly ZoomService $zoom) {}

    public function settings()
    {
        $accountId = Setting::getSecure('zoom_account_id', '');
        $clientId  = Setting::getSecure('zoom_client_id', '');

        return view('admin.settings.zoom', [
            'configured'  => $this->zoom->isConfigured(),
            'account_id'  => $accountId,
            'client_id'   => $clientId,
            'has_creds'   => (bool) ($accountId && $clientId),
        ]);
    }

    public function saveCredentials(Request $request)
    {
        $request->validate([
            'account_id'    => 'required|string',
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'secret_token'  => 'nullable|string',
        ]);

        Setting::setSecure('zoom_account_id',    $request->account_id);
        Setting::setSecure('zoom_client_id',     $request->client_id);
        Setting::setSecure('zoom_client_secret', $request->client_secret);

        if ($request->filled('secret_token')) {
            Setting::setSecure('zoom_secret_token', $request->secret_token);
        }

        // Clear any cached token so it refreshes with new credentials
        Setting::set('zoom_access_token',    null);
        Setting::set('zoom_token_expires_at', null);

        return back()->with('success', 'Zoom credentials saved successfully.');
    }

    public function testConnection()
    {
        $token = $this->zoom->getAccessToken();

        if (!$token) {
            return back()->with('error', 'Could not obtain Zoom access token. Check your credentials.');
        }

        return back()->with('success', 'Zoom connection successful! Access token obtained.');
    }

    public function disconnect()
    {
        Setting::set('zoom_account_id',     null);
        Setting::set('zoom_client_id',      null);
        Setting::set('zoom_client_secret',  null);
        Setting::set('zoom_secret_token',   null);
        Setting::set('zoom_access_token',   null);
        Setting::set('zoom_token_expires_at', null);

        return back()->with('success', 'Zoom credentials removed.');
    }
}
