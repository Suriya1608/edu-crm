<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstagramAccount;
use App\Models\Setting;
use App\Services\LeadDefaults;
use App\Services\AutomationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SystemSettingsController extends Controller
{
    public function smtp()
    {
        return view('admin.settings.smtp');
    }

    public function updateSmtp(Request $request)
    {
        $data = $request->validate([
            'smtp_mailer' => 'required|in:smtp,log,array',
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|in:tls,ssl',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_from_address' => 'required|email|max:255',
            'smtp_from_name' => 'required|string|max:255',
        ]);

        Setting::set('smtp_mailer', $data['smtp_mailer']);
        Setting::set('smtp_host', $data['smtp_host']);
        Setting::set('smtp_port', (string) $data['smtp_port']);
        Setting::set('smtp_encryption', (string) ($data['smtp_encryption'] ?? ''));
        Setting::setSecure('smtp_username', $data['smtp_username'] ?? null);
        Setting::setSecure('smtp_password', $data['smtp_password'] ?? null);
        Setting::set('smtp_from_address', $data['smtp_from_address']);
        Setting::set('smtp_from_name', $data['smtp_from_name']);

        return back()->with('success', 'SMTP settings updated successfully.');
    }

    public function testSmtp(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email|max:255',
        ]);

        try {
            Mail::raw('This is a test email from CRM System Settings.', function ($message) use ($request) {
                $message->to($request->input('test_email'))
                    ->subject('CRM SMTP Test Email');
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'SMTP test failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Test email sent successfully.');
    }

    public function sms()
    {
        return view('admin.settings.sms');
    }

    public function updateSms(Request $request)
    {
        $data = $request->validate([
            'sms_enabled' => 'nullable|boolean',
            'sms_provider' => 'required|in:twilio,msg91,textlocal,custom',
            'sms_sender_id' => 'nullable|string|max:50',
            'sms_api_key' => 'nullable|string|max:255',
            'sms_api_secret' => 'nullable|string|max:255',
            'sms_notifications_enabled' => 'nullable|boolean',
        ]);

        Setting::set('sms_enabled', $request->boolean('sms_enabled') ? '1' : '0');
        Setting::set('sms_provider', $data['sms_provider']);
        Setting::set('sms_sender_id', $data['sms_sender_id'] ?? '');
        Setting::setSecure('sms_api_key', $data['sms_api_key'] ?? null);
        Setting::setSecure('sms_api_secret', $data['sms_api_secret'] ?? null);
        Setting::set('sms_notifications_enabled', $request->boolean('sms_notifications_enabled') ? '1' : '0');

        return back()->with('success', 'SMS settings updated successfully.');
    }

    public function whatsapp()
    {
        return view('admin.settings.whatsapp', [
            'token'            => Setting::getSecure('meta_whatsapp_token', ''),
            'phoneId'          => Setting::get('meta_whatsapp_phone_number_id', ''),
            'verifyToken'      => Setting::get('meta_whatsapp_webhook_verify_token', 'crm_verify_token'),
            'templateName'     => Setting::get('meta_whatsapp_template_name', config('services.meta.whatsapp_default_template', 'hello_world')),
            'templateLanguage' => Setting::get('meta_whatsapp_template_language', config('services.meta.whatsapp_default_template_language', 'en')),
            'templateBody'     => Setting::get('meta_whatsapp_template_body', ''),
        ]);
    }

    public function updateWhatsapp(Request $request)
    {
        $data = $request->validate([
            'meta_whatsapp_token'                => 'nullable|string|max:512',
            'meta_whatsapp_phone_number_id'      => 'nullable|string|max:100',
            'meta_whatsapp_webhook_verify_token' => 'nullable|string|max:255',
            'meta_whatsapp_template_name'        => 'nullable|string|max:255',
            'meta_whatsapp_template_language'    => 'nullable|string|max:20',
            'meta_whatsapp_template_body'        => 'nullable|string|max:1024',
        ]);

        if (!empty($data['meta_whatsapp_token'])) {
            Setting::setSecure('meta_whatsapp_token', $data['meta_whatsapp_token']);
        }
        Setting::set('meta_whatsapp_phone_number_id', $data['meta_whatsapp_phone_number_id'] ?? '');
        Setting::set('meta_whatsapp_webhook_verify_token', $data['meta_whatsapp_webhook_verify_token'] ?? 'crm_verify_token');
        Setting::set('meta_whatsapp_template_name', $data['meta_whatsapp_template_name'] ?? 'welcome_template');
        Setting::set('meta_whatsapp_template_language', $data['meta_whatsapp_template_language'] ?? 'en');
        Setting::set('meta_whatsapp_template_body', $data['meta_whatsapp_template_body'] ?? '');

        return back()->with('success', 'WhatsApp settings saved successfully.');
    }

    // ── Call Settings — redirects to the unified TCN page ───────────────────

    public function callSettings()
    {
        return redirect()->route('admin.settings.tcn');
    }

    public function updateCallSettings()
    {
        return redirect()->route('admin.settings.tcn');
    }

    public function businessHours()
    {
        return view('admin.settings.business-hours');
    }

    public function updateBusinessHours(Request $request)
    {
        $data = $request->validate([
            'business_hours_enabled' => 'nullable|boolean',
            'business_start_time' => 'required|date_format:H:i',
            'business_end_time' => 'required|date_format:H:i',
        ]);

        Setting::set(AutomationSettings::BUSINESS_HOURS_ENABLED, $request->boolean('business_hours_enabled') ? '1' : '0');
        Setting::set(AutomationSettings::BUSINESS_START_TIME, $data['business_start_time']);
        Setting::set(AutomationSettings::BUSINESS_END_TIME, $data['business_end_time']);

        return back()->with('success', 'Business hours updated successfully.');
    }

    public function workingDays()
    {
        return view('admin.settings.working-days');
    }

    public function updateWorkingDays(Request $request)
    {
        $data = $request->validate([
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'integer|min:1|max:7',
        ]);

        $days = collect($data['working_days'])->map(fn($d) => (int) $d)->unique()->values()->all();
        Setting::set(AutomationSettings::WORKING_DAYS, json_encode($days));

        return back()->with('success', 'Working days updated successfully.');
    }

    public function timezone()
    {
        return view('admin.settings.timezone', [
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function updateTimezone(Request $request)
    {
        $request->validate([
            'system_timezone' => 'required|string|timezone',
        ]);

        Setting::set('system_timezone', $request->input('system_timezone'));

        return back()->with('success', 'Timezone updated successfully.');
    }

    public function defaultLeadStatus()
    {
        return view('admin.settings.default-lead-status', [
            'statuses' => LeadDefaults::allowedStatuses(),
        ]);
    }

    public function updateDefaultLeadStatus(Request $request)
    {
        $request->validate([
            'default_lead_status' => 'required|string|in:' . implode(',', LeadDefaults::allowedStatuses()),
        ]);

        Setting::set(LeadDefaults::DEFAULT_STATUS_KEY, $request->input('default_lead_status'));

        return back()->with('success', 'Default lead status updated successfully.');
    }

    public function instagram()
    {
        $account = InstagramAccount::first();
        return view('admin.settings.instagram', compact('account'));
    }

    public function updateInstagram(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'page_id'             => 'required|string|max:100',
            'instagram_user_id'   => 'nullable|string|max:100',
            'access_token'        => 'nullable|string|max:1024',
            'app_secret'          => 'nullable|string|max:255',
            'verify_token'        => 'required|string|max:128',
            'is_active'           => 'nullable|boolean',
        ]);

        $account = InstagramAccount::first();

        $fillable = [
            'name'              => $data['name'],
            'page_id'           => $data['page_id'],
            'instagram_user_id' => $data['instagram_user_id'] ?? null,
            'verify_token'      => $data['verify_token'],
            'is_active'         => $request->boolean('is_active'),
        ];

        if (!empty($data['access_token'])) {
            $fillable['access_token'] = $data['access_token'];
        }
        if (array_key_exists('app_secret', $data) && $data['app_secret'] !== null && $data['app_secret'] !== '') {
            $fillable['app_secret'] = $data['app_secret'];
        }

        if ($account) {
            $account->update($fillable);
        } else {
            if (empty($data['access_token'])) {
                return back()->withErrors(['access_token' => 'Access token is required when creating a new account.'])->withInput();
            }
            InstagramAccount::create($fillable);
        }

        return back()->with('success', 'Instagram settings saved successfully.');
    }

    public function realtime()
    {
        return view('admin.settings.realtime', [
            'driver'        => Setting::get('broadcast_driver', 'null'),
            'pusherKey'     => Setting::getSecure('pusher_app_key', ''),
            'pusherSecret'  => Setting::getSecure('pusher_app_secret', ''),
            'pusherAppId'   => Setting::getSecure('pusher_app_id', ''),
            'pusherCluster' => Setting::get('pusher_app_cluster', 'mt1'),
            'reverbKey'     => Setting::getSecure('reverb_app_key', ''),
            'reverbSecret'  => Setting::getSecure('reverb_app_secret', ''),
            'reverbAppId'   => Setting::getSecure('reverb_app_id', ''),
            'reverbHost'    => Setting::get('reverb_host', '127.0.0.1'),
            'reverbPort'    => Setting::get('reverb_port', '8080'),
            'reverbScheme'  => Setting::get('reverb_scheme', 'http'),
        ]);
    }

    public function updateRealtime(Request $request)
    {
        $data = $request->validate([
            'broadcast_driver'   => 'required|in:null,pusher,reverb',
            'pusher_app_key'     => 'nullable|string|max:255',
            'pusher_app_secret'  => 'nullable|string|max:255',
            'pusher_app_id'      => 'nullable|string|max:100',
            'pusher_app_cluster' => 'nullable|string|max:20',
            'reverb_app_key'     => 'nullable|string|max:255',
            'reverb_app_secret'  => 'nullable|string|max:255',
            'reverb_app_id'      => 'nullable|string|max:100',
            'reverb_host'        => 'nullable|string|max:255',
            'reverb_port'        => 'nullable|integer|min:1|max:65535',
            'reverb_scheme'      => 'nullable|in:http,https',
        ]);

        Setting::set('broadcast_driver', $data['broadcast_driver']);
        Setting::set('pusher_app_cluster', $data['pusher_app_cluster'] ?? 'mt1');
        Setting::set('reverb_host',   $data['reverb_host']   ?? '127.0.0.1');
        Setting::set('reverb_port',   (string) ($data['reverb_port'] ?? 8080));
        Setting::set('reverb_scheme', $data['reverb_scheme'] ?? 'http');

        if (!empty($data['pusher_app_key']))    Setting::setSecure('pusher_app_key',    $data['pusher_app_key']);
        if (!empty($data['pusher_app_secret'])) Setting::setSecure('pusher_app_secret', $data['pusher_app_secret']);
        if (!empty($data['pusher_app_id']))     Setting::setSecure('pusher_app_id',     $data['pusher_app_id']);
        if (!empty($data['reverb_app_key']))    Setting::setSecure('reverb_app_key',    $data['reverb_app_key']);
        if (!empty($data['reverb_app_secret'])) Setting::setSecure('reverb_app_secret', $data['reverb_app_secret']);
        if (!empty($data['reverb_app_id']))     Setting::setSecure('reverb_app_id',     $data['reverb_app_id']);

        return back()->with('success', 'Real-time settings updated successfully.');
    }

    public function notifications()
    {
        return view('admin.settings.notifications');
    }

    public function updateNotifications(Request $request)
    {
        $boolKeys = [
            'notify_inapp_lead_assignment',
            'notify_email_lead_assignment',
            'notify_inapp_followup_reminder',
            'notify_email_followup_reminder',
            'notify_inapp_escalation',
            'notify_email_escalation',
        ];

        foreach ($boolKeys as $key) {
            Setting::set($key, $request->boolean($key) ? '1' : '0');
        }

        return back()->with('success', 'Notification settings updated successfully.');
    }
}
