<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::on('central_mysql')->latest()->get();
        return view('super-admin.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('super-admin.tenants.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'subdomain'      => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9\-]*[a-z0-9]$|^[a-z0-9]$/', 'unique:central_mysql.tenants,subdomain'],
            'db_name'        => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:central_mysql.tenants,db_name'],
            'admin_email'    => ['nullable', 'email'],
            'admin_password' => ['nullable', 'string', 'min:8'],
        ]);

        $args = array_filter([
            'name'             => $data['name'],
            'subdomain'        => $data['subdomain'],
            '--db'             => $data['db_name'],
            '--admin-email'    => $data['admin_email'] ?? null,
            '--admin-password' => $data['admin_password'] ?? null,
        ]);

        if ($request->boolean('existing_db')) {
            $args['--existing'] = true;
        }

        Artisan::call('tenant:create', $args);

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant '{$data['name']}' created successfully.");
    }

    public function edit(Tenant $tenant)
    {
        return view('super-admin.tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $tab = $request->input('tab', 'site');

        match ($tab) {
            'site'      => $this->saveSite($request, $tenant),
            'smtp'      => $this->saveSmtp($request, $tenant),
            'tcn'       => $this->saveTcn($request, $tenant),
            'whatsapp'  => $this->saveWhatsapp($request, $tenant),
            'zoom'      => $this->saveZoom($request, $tenant),
            'google'    => $this->saveGoogle($request, $tenant),
            'broadcast' => $this->saveBroadcast($request, $tenant),
            default     => null,
        };

        return redirect()->route('superadmin.tenants.edit', $tenant)
            ->with('success', ucfirst($tab) . ' settings saved.')
            ->withFragment($tab);
    }

    // ── Section Savers ─────────────────────────────────────────────────────────

    private function saveSite(Request $request, Tenant $tenant): void
    {
        $request->validate([
            'site.name'     => ['required', 'string', 'max:255'],
            'site.timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $tenant->update([
            'name'                    => $request->input('site.name'),
            'plan'                    => $request->input('site.plan') ?: null,
            'site_url'                => $request->input('site.url'),
            'site_timezone'           => $request->input('site.timezone', 'UTC'),
            'site_logo'               => $request->input('site.logo'),
            'site_favicon'            => $request->input('site.favicon'),
            'site_telephony_provider' => $request->input('site.telephony_provider', 'tcn'),
        ]);
    }

    private function saveSmtp(Request $request, Tenant $tenant): void
    {
        $request->validate([
            'smtp.host'         => ['required', 'string'],
            'smtp.port'         => ['required', 'integer'],
            'smtp.from_address' => ['required', 'email'],
        ]);

        $attrs = [
            'smtp_mailer'       => $request->input('smtp.mailer', 'smtp'),
            'smtp_host'         => $request->input('smtp.host'),
            'smtp_port'         => $request->input('smtp.port'),
            'smtp_encryption'   => $request->input('smtp.encryption'),
            'smtp_username'     => $request->input('smtp.username'),
            'smtp_from_address' => $request->input('smtp.from_address'),
            'smtp_from_name'    => $request->input('smtp.from_name'),
        ];

        // Only overwrite password when a new value is provided
        $password = $request->input('smtp.password');
        if (!empty($password)) {
            $attrs['smtp_password'] = $password;
        }

        $tenant->update($attrs);
    }

    private function saveTcn(Request $request, Tenant $tenant): void
    {
        $attrs = [
            'tcn_client_sid'   => $request->input('tcn.client_sid'),
            'tcn_caller_id'    => $request->input('tcn.caller_id'),
            'tcn_redirect_uri' => $request->input('tcn.redirect_uri'),
        ];

        foreach (['client_id' => 'tcn_client_id', 'client_secret' => 'tcn_client_secret', 'refresh_token' => 'tcn_refresh_token'] as $input => $column) {
            $value = $request->input("tcn.{$input}");
            if (!empty($value)) {
                $attrs[$column] = $value;
            }
        }

        $tenant->update($attrs);
    }

    private function saveWhatsapp(Request $request, Tenant $tenant): void
    {
        $attrs = [
            'wa_phone_number_id'     => $request->input('whatsapp.phone_number_id'),
            'wa_business_account_id' => $request->input('whatsapp.business_account_id'),
            'wa_verify_token'        => $request->input('whatsapp.verify_token'),
            'wa_template_name'       => $request->input('whatsapp.template_name', 'hello_world'),
            'wa_template_language'   => $request->input('whatsapp.template_language', 'en'),
        ];

        $token = $request->input('whatsapp.token');
        if (!empty($token)) {
            $attrs['wa_token'] = $token;
        }

        $tenant->update($attrs);
    }

    private function saveZoom(Request $request, Tenant $tenant): void
    {
        $attrs = [];

        foreach (['account_id' => 'zoom_account_id', 'client_id' => 'zoom_client_id', 'client_secret' => 'zoom_client_secret'] as $input => $column) {
            $value = $request->input("zoom.{$input}");
            if (!empty($value)) {
                $attrs[$column] = $value;
            }
        }

        if ($attrs) {
            $tenant->update($attrs);
        }
    }

    private function saveGoogle(Request $request, Tenant $tenant): void
    {
        $attrs = [
            'google_client_id' => $request->input('google.client_id'),
        ];

        $secret = $request->input('google.client_secret');
        if (!empty($secret)) {
            $attrs['google_client_secret'] = $secret;
        }

        $tenant->update($attrs);
    }

    private function saveBroadcast(Request $request, Tenant $tenant): void
    {
        $driver = $request->input('broadcast.driver', 'null');

        $attrs = [
            'broadcast_driver' => $driver,
            'pusher_app_id'    => $request->input('broadcast.pusher_app_id'),
            'pusher_key'       => $request->input('broadcast.pusher_key'),
            'pusher_cluster'   => $request->input('broadcast.pusher_cluster'),
            'reverb_app_id'    => $request->input('broadcast.reverb_app_id'),
            'reverb_key'       => $request->input('broadcast.reverb_key'),
            'reverb_host'      => $request->input('broadcast.reverb_host'),
            'reverb_port'      => $request->input('broadcast.reverb_port'),
            'reverb_scheme'    => $request->input('broadcast.reverb_scheme', 'http'),
        ];

        $pusherSecret = $request->input('broadcast.pusher_secret');
        if (!empty($pusherSecret)) {
            $attrs['pusher_secret'] = $pusherSecret;
        }

        $reverbSecret = $request->input('broadcast.reverb_secret');
        if (!empty($reverbSecret)) {
            $attrs['reverb_secret'] = $reverbSecret;
        }

        $tenant->update($attrs);
    }

    // ── Other Actions ──────────────────────────────────────────────────────────

    public function destroy(Tenant $tenant)
    {
        $tenant->update(['is_active' => false]);
        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant '{$tenant->name}' deactivated.");
    }

    public function toggle(Tenant $tenant)
    {
        $tenant->update(['is_active' => !$tenant->is_active]);
        $status = $tenant->is_active ? 'activated' : 'deactivated';
        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant '{$tenant->name}' {$status}.");
    }

    public function provision(Tenant $tenant)
    {
        Artisan::call('tenant:migrate', ['--tenant' => $tenant->subdomain, '--force' => true]);
        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Migrations run for '{$tenant->subdomain}'.");
    }

    public function migrate(Tenant $tenant)
    {
        Artisan::call('tenant:migrate', ['--tenant' => $tenant->subdomain, '--force' => true]);
        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Migrations run for '{$tenant->subdomain}'.");
    }
}
