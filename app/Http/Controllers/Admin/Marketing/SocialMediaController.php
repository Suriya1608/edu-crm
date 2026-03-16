<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaController extends Controller
{
    public function index()
    {
        $facebookConnected = (bool) Setting::getSecure('fb_leads_page_token');

        return view('admin.marketing.social-media', compact('facebookConnected'));
    }

    // ──────────────────────────────────────────────────────────────
    //  Meta Facebook Lead Ads Webhook
    //  GET  /webhooks/meta/facebook  — verification challenge
    //  POST /webhooks/meta/facebook  — lead event
    // ──────────────────────────────────────────────────────────────
    public function webhook(Request $request)
    {
        if ($request->isMethod('get')) {
            return $this->handleVerification($request);
        }

        return $this->handleLeadEvent($request);
    }

    private function handleVerification(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $savedToken = Setting::get('fb_leads_verify_token', '');

        if ($mode === 'subscribe' && hash_equals((string) $savedToken, (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    private function handleLeadEvent(Request $request)
    {
        // Verify HMAC signature
        $appSecret = Setting::getSecure('fb_leads_app_secret', '');
        if ($appSecret) {
            $sig      = $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
            if (! hash_equals($expected, $sig)) {
                Log::warning('Facebook webhook HMAC mismatch');
                return response('Forbidden', 403);
            }
        }

        $pageToken = Setting::getSecure('fb_leads_page_token', '');
        if (! $pageToken) {
            Log::error('Facebook webhook: no page token configured');
            return response('EVENT_RECEIVED', 200);
        }

        $payload = $request->json()->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'leadgen') {
                    continue;
                }

                $value     = $change['value'] ?? [];
                $leadgenId = $value['leadgen_id'] ?? null;

                if (! $leadgenId) {
                    continue;
                }

                try {
                    $leadData = $this->fetchLeadDetails($leadgenId, $pageToken);
                    if ($leadData) {
                        $this->createLeadFromFacebook($leadData, $value);
                    }
                } catch (\Throwable $e) {
                    Log::error('Facebook lead processing error', [
                        'leadgen_id' => $leadgenId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        // Meta requires exactly this response
        return response('EVENT_RECEIVED', 200);
    }

    private function fetchLeadDetails(string $leadgenId, string $token): ?array
    {
        $http = Http::timeout(15);
        if (app()->environment('local')) {
            $http = $http->withoutVerifying();
        }

        $response = $http->get("https://graph.facebook.com/v19.0/{$leadgenId}", [
            'fields'       => 'field_data,created_time,ad_id,form_id',
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            Log::error('Facebook lead fetch failed', [
                'leadgen_id' => $leadgenId,
                'error'      => $response->json('error.message'),
            ]);
            return null;
        }

        return $response->json();
    }

    private function createLeadFromFacebook(array $leadData, array $changeValue): void
    {
        // Map field_data array to key => value
        $fields = [];
        foreach ($leadData['field_data'] ?? [] as $field) {
            $fields[$field['name']] = $field['values'][0] ?? null;
        }

        $name  = $fields['full_name'] ?? $fields['name'] ?? null;
        $phone = $fields['phone_number'] ?? $fields['phone'] ?? null;
        $email = $fields['email'] ?? null;

        // Require at least a phone or email to create a lead
        if (! $name && ! $phone && ! $email) {
            Log::warning('Facebook lead skipped: no usable fields', ['fields' => $fields]);
            return;
        }

        $course = $fields['course']
            ?? $fields['program']
            ?? $fields['course_interest']
            ?? $fields['area_of_interest']
            ?? null;

        // Determine source
        $adId  = $changeValue['ad_id'] ?? null;
        $source = 'facebook_ads';
        if ($adId) {
            // Check if the ad is an Instagram ad via Graph API (best-effort; not blocking)
            $source = $this->resolveAdSource($adId);
        }

        // Avoid duplicate leads by phone (if phone exists and was seen in last 10 minutes)
        if ($phone) {
            $exists = Lead::where('phone', $phone)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->exists();
            if ($exists) {
                Log::info('Facebook lead duplicate skipped', ['phone' => $phone]);
                return;
            }
        }

        $courseId = $course ? Course::where('name', trim($course))->value('id') : null;

        Lead::create([
            'name'      => $name ?? ($email ?? 'Unknown'),
            'phone'     => $phone ?? '',
            'email'     => $email,
            'course_id' => $courseId,
            'source'    => $source,
            'status'    => 'new',
        ]);

        Log::info('Facebook lead created', ['name' => $name, 'phone' => $phone, 'source' => $source]);
    }

    private function resolveAdSource(string $adId): string
    {
        // Ad IDs from Instagram placements are served through Facebook's system.
        // We use a simple heuristic: query the ad object for publisher_platforms.
        try {
            $token = Setting::getSecure('fb_leads_page_token', '');
            if (! $token) {
                return 'facebook_ads';
            }

            $http = Http::timeout(10);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $resp = $http->get("https://graph.facebook.com/v19.0/{$adId}", [
                'fields'       => 'publisher_platforms',
                'access_token' => $token,
            ]);

            if ($resp->successful()) {
                $platforms = $resp->json('publisher_platforms', []);
                if (in_array('instagram', $platforms, true) && ! in_array('facebook', $platforms, true)) {
                    return 'instagram_ads';
                }
            }
        } catch (\Throwable) {
            // Non-blocking — default to facebook_ads
        }

        return 'facebook_ads';
    }
}
