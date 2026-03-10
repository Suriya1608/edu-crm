<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Services\EnvManager;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit');
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:255',
            'site_url' => 'required|string|max:255',
            'telephony_provider' => 'required|in:twilio,exotel',

            // Logo validation
            'site_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',

            // Favicon validation
            'site_favicon' => 'nullable|image|mimes:png,ico|max:512',
        ]);

        if ($request->hasFile('site_logo')) {

            // Delete old logo if exists
            if ($oldLogo = Setting::get('site_logo')) {
                Storage::disk('public')->delete($oldLogo);
            }

            $logo = $request->file('site_logo')->store('settings', 'public');
            Setting::set('site_logo', $logo);
        }

        if ($request->hasFile('site_favicon')) {

            if ($oldFavicon = Setting::get('site_favicon')) {
                Storage::disk('public')->delete($oldFavicon);
            }

            $favicon = $request->file('site_favicon')->store('settings', 'public');
            Setting::set('site_favicon', $favicon);
        }

        foreach ($request->except('_token', 'site_logo', 'site_favicon') as $key => $value) {
            Setting::set($key, $value);
        }

        // Sync APP_URL in .env so Laravel route generation matches
        if ($request->filled('site_url')) {
            EnvManager::update(['APP_URL' => rtrim($request->input('site_url'), '/')]);
        }

        return back()->with('success', 'Settings updated successfully');
    }
}
