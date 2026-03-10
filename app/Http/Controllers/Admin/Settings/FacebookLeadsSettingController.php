<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class FacebookLeadsSettingController extends Controller
{
    public function show()
    {
        return view('admin.settings.facebook-leads', [
            'appId'       => Setting::get('fb_leads_app_id', ''),
            'appSecret'   => Setting::getSecure('fb_leads_app_secret', ''),
            'pageToken'   => Setting::getSecure('fb_leads_page_token', ''),
            'pageId'      => Setting::getSecure('fb_leads_page_id', ''),
            'verifyToken' => Setting::get('fb_leads_verify_token', ''),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'fb_leads_app_id'       => 'nullable|string|max:255',
            'fb_leads_app_secret'   => 'nullable|string|max:255',
            'fb_leads_page_token'   => 'nullable|string|max:1000',
            'fb_leads_page_id'      => 'nullable|string|max:255',
            'fb_leads_verify_token' => 'nullable|string|max:255',
        ]);

        Setting::set('fb_leads_app_id', $request->input('fb_leads_app_id'));
        Setting::set('fb_leads_verify_token', $request->input('fb_leads_verify_token'));

        if ($request->filled('fb_leads_app_secret')) {
            Setting::setSecure('fb_leads_app_secret', $request->input('fb_leads_app_secret'));
        }
        if ($request->filled('fb_leads_page_token')) {
            Setting::setSecure('fb_leads_page_token', $request->input('fb_leads_page_token'));
        }
        if ($request->filled('fb_leads_page_id')) {
            Setting::setSecure('fb_leads_page_id', $request->input('fb_leads_page_id'));
        }

        return redirect()->route('admin.settings.facebook-leads')
            ->with('success', 'Facebook Lead Ads settings saved.');
    }
}
