<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class PageSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.pages', [
            'privacyContent' => Setting::get('privacy_policy_content', ''),
            'termsContent'   => Setting::get('terms_of_service_content', ''),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'page'    => 'required|in:privacy_policy,terms_of_service',
            'content' => 'nullable|string|max:200000',
        ]);

        $key = $request->input('page') . '_content';
        Setting::set($key, $request->input('content', ''));

        $label = $request->input('page') === 'privacy_policy' ? 'Privacy Policy' : 'Terms of Service';

        return back()->with('success', $label . ' updated successfully.');
    }
}
