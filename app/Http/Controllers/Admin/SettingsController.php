<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit');
    }

    public function security()
    {
        return view('admin.settings.security');
    }

    public function updateSecurity(Request $request)
    {
        $request->validate([
            'login_attempt_limit' => 'required|integer|min:3|max:20',
        ]);

        Setting::set('login_attempt_limit', (int) $request->login_attempt_limit);

        // 2FA per-role toggles (unchecked checkbox sends nothing, so default to false)
        Setting::set('2fa_admin',      $request->boolean('2fa_admin')      ? '1' : '0');
        Setting::set('2fa_manager',    $request->boolean('2fa_manager')    ? '1' : '0');
        Setting::set('2fa_telecaller', $request->boolean('2fa_telecaller') ? '1' : '0');

        return back()->with('success', 'Security settings updated successfully.');
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name'    => 'required|string|max:255',
            'site_url'     => 'required|string|max:255',
            'site_logo'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'site_favicon' => 'nullable|image|mimes:png,ico|max:512',
        ]);

        if ($request->hasFile('site_logo')) {
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

        return back()->with('success', 'Settings updated successfully');
    }
}
