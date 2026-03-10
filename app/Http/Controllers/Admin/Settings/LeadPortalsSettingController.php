<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class LeadPortalsSettingController extends Controller
{
    public function show()
    {
        return view('admin.settings.lead-portals', [
            // Siksha
            'sikshaApiKey'      => Setting::get('siksha_api_key', ''),
            'sikshaApiSecret'   => Setting::getSecure('siksha_api_secret', ''),
            'sikshaVerifyToken' => Setting::get('siksha_verify_token', ''),

            // CollegeDunia
            'cduniaApiKey'      => Setting::get('college_dunia_api_key', ''),
            'cduniaApiSecret'   => Setting::getSecure('college_dunia_api_secret', ''),
            'cduniaVerifyToken' => Setting::get('college_dunia_verify_token', ''),

            // CollegeDekho
            'cdekhoApiKey'      => Setting::get('college_dekho_api_key', ''),
            'cdekhoApiSecret'   => Setting::getSecure('college_dekho_api_secret', ''),
            'cdekhoVerifyToken' => Setting::get('college_dekho_verify_token', ''),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            // Siksha
            'siksha_api_key'      => 'nullable|string|max:255',
            'siksha_api_secret'   => 'nullable|string|max:255',
            'siksha_verify_token' => 'nullable|string|max:255',

            // CollegeDunia
            'college_dunia_api_key'      => 'nullable|string|max:255',
            'college_dunia_api_secret'   => 'nullable|string|max:255',
            'college_dunia_verify_token' => 'nullable|string|max:255',

            // CollegeDekho
            'college_dekho_api_key'      => 'nullable|string|max:255',
            'college_dekho_api_secret'   => 'nullable|string|max:255',
            'college_dekho_verify_token' => 'nullable|string|max:255',
        ]);

        // ── Siksha ──────────────────────────────────────────
        Setting::set('siksha_api_key', $request->input('siksha_api_key'));
        Setting::set('siksha_verify_token', $request->input('siksha_verify_token'));
        if ($request->filled('siksha_api_secret')) {
            Setting::setSecure('siksha_api_secret', $request->input('siksha_api_secret'));
        }

        // ── CollegeDunia ─────────────────────────────────────
        Setting::set('college_dunia_api_key', $request->input('college_dunia_api_key'));
        Setting::set('college_dunia_verify_token', $request->input('college_dunia_verify_token'));
        if ($request->filled('college_dunia_api_secret')) {
            Setting::setSecure('college_dunia_api_secret', $request->input('college_dunia_api_secret'));
        }

        // ── CollegeDekho ─────────────────────────────────────
        Setting::set('college_dekho_api_key', $request->input('college_dekho_api_key'));
        Setting::set('college_dekho_verify_token', $request->input('college_dekho_verify_token'));
        if ($request->filled('college_dekho_api_secret')) {
            Setting::setSecure('college_dekho_api_secret', $request->input('college_dekho_api_secret'));
        }

        return redirect()->route('admin.settings.lead-portals')
            ->with('success', 'Lead portal credentials saved successfully.');
    }
}
