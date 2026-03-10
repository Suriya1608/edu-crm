<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class PageController extends Controller
{
    public function privacyPolicy()
    {
        return view('pages.privacy-policy', [
            'content'  => Setting::get('privacy_policy_content', ''),
            'siteName' => Setting::get('site_name', 'Admission CRM'),
        ]);
    }

    public function termsOfService()
    {
        return view('pages.terms-of-service', [
            'content'  => Setting::get('terms_of_service_content', ''),
            'siteName' => Setting::get('site_name', 'Admission CRM'),
        ]);
    }
}
