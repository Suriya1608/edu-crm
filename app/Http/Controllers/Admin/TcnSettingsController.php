<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TcnSettingsController extends Controller
{
    // ---------------------------------------------------------------
    // Show global TCN settings form
    // ---------------------------------------------------------------

    public function index(): View
    {
        return view('admin.settings.tcn', [
            'client_id'    => Setting::getSecure('tcn_client_id',    env('TCN_CLIENT_ID', '')),
            'client_secret'=> Setting::getSecure('tcn_client_secret', env('TCN_CLIENT_SECRET', '')),
            'auth_url'     => Setting::get('tcn_auth_url',  'https://auth.tcn.com/token'),
            'base_url'     => Setting::get('tcn_base_url',  'https://api.bom.tcn.com'),
            'redirect_uri' => Setting::get('tcn_redirect_uri', env('TCN_REDIRECT_URI', '')),
            'caller_id'    => Setting::get('tcn_caller_id', env('TCN_CALLER_ID', '')),
            'connected'    => !empty(Setting::getSecure('tcn_refresh_token')),
        ]);
    }

    // ---------------------------------------------------------------
    // Save global TCN settings
    // ---------------------------------------------------------------

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'client_id'     => 'required|string|max:255',
            'client_secret' => 'nullable|string|max:500',
            'auth_url'      => 'required|url|max:255',
            'base_url'      => 'required|url|max:255',
            'redirect_uri'  => 'nullable|url|max:500',
            'caller_id'     => 'nullable|string|max:50',
        ]);

        Setting::setSecure('tcn_client_id', $request->client_id);
        Setting::get('tcn_auth_url') !== null
            ? Setting::set('tcn_auth_url', $request->auth_url)
            : Setting::set('tcn_auth_url', $request->auth_url);
        Setting::set('tcn_base_url',     $request->base_url);
        Setting::set('tcn_redirect_uri', $request->redirect_uri ?? '');
        Setting::set('tcn_caller_id',    $request->caller_id   ?? '');

        // Only overwrite client_secret if a new value is provided
        if ($request->filled('client_secret')) {
            Setting::setSecure('tcn_client_secret', $request->client_secret);
        }

        return redirect()->route('admin.settings.tcn')
            ->with('success', 'TCN global settings saved.');
    }
}
