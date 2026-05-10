<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $host      = $request->getHost();
        $appDomain = env('APP_DOMAIN', 'insighttechnology.in');

        // Central/super-admin panel — never switch tenant DB
        $centralHost = env('CENTRAL_DOMAIN', 'educrm') . '.' . $appDomain;
        if ($host === $centralHost) {
            app()->instance('tenant', null);
            view()->share('globalSettings', collect());
            return $next($request);
        }

        $subdomain = $this->resolveSubdomain($host, $appDomain);

        if (!$subdomain) {
            app()->instance('tenant', null);
            view()->share('globalSettings', collect());
            return $next($request);
        }

        $tenant = Tenant::on('central_mysql')
            ->where('subdomain', $subdomain)
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            abort(404, 'Client not found. Please check your URL.');
        }

        // Switch the default mysql connection to this tenant's database
        config(['database.connections.mysql.database' => $tenant->db_name]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        app()->instance('tenant', $tenant);

        $this->applyTenantConfig($tenant);

        return $next($request);
    }

    private function resolveSubdomain(string $host, string $appDomain): ?string
    {
        $suffix = '.' . $appDomain;

        if (str_ends_with($host, $suffix)) {
            $sub = substr($host, 0, strlen($host) - strlen($suffix));
            return ($sub !== '' && $sub !== $appDomain) ? $sub : null;
        }

        return env('DEFAULT_TENANT_SUBDOMAIN') ?: null;
    }

    private function applyTenantConfig(Tenant $tenant): void
    {
        // ── Site ───────────────────────────────────────────────────────────────
        $timezone = $tenant->site_timezone ?? 'UTC';
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        view()->share('globalSettings', collect([
            'site_name'          => $tenant->name,
            'site_logo'          => $tenant->site_logo          ?? '',
            'site_favicon'       => $tenant->site_favicon       ?? '',
            'site_url'           => $tenant->site_url           ?? '',
            'telephony_provider' => $tenant->site_telephony_provider ?? 'tcn',
            'system_timezone'    => $timezone,
        ]));

        // ── SMTP ──────────────────────────────────────────────────────────────
        if ($tenant->smtp_host) {
            config([
                'mail.default'                 => $tenant->smtp_mailer       ?? env('MAIL_MAILER', 'smtp'),
                'mail.mailers.smtp.host'       => $tenant->smtp_host,
                'mail.mailers.smtp.port'       => (int) ($tenant->smtp_port  ?? env('MAIL_PORT', 587)),
                'mail.mailers.smtp.encryption' => $tenant->smtp_encryption   ?? env('MAIL_ENCRYPTION'),
                'mail.mailers.smtp.username'   => $tenant->smtp_username     ?? env('MAIL_USERNAME'),
                'mail.mailers.smtp.password'   => $tenant->smtp_password     ?? env('MAIL_PASSWORD'),
                'mail.from.address'            => $tenant->smtp_from_address ?? env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'mail.from.name'               => $tenant->smtp_from_name    ?? env('MAIL_FROM_NAME', 'CRM'),
            ]);
        }

        // ── TCN Softphone ─────────────────────────────────────────────────────
        config([
            'tcn.client_id'     => $tenant->tcn_client_id     ?? env('TCN_CLIENT_ID', ''),
            'tcn.client_secret' => $tenant->tcn_client_secret ?? env('TCN_CLIENT_SECRET', ''),
            'tcn.refresh_token' => $tenant->tcn_refresh_token ?? env('TCN_REFRESH_TOKEN', ''),
            'tcn.redirect_uri'  => $tenant->tcn_redirect_uri  ?? env('TCN_REDIRECT_URI', ''),
            'tcn.client_sid'    => $tenant->tcn_client_sid    ?? env('TCN_CLIENT_SID', ''),
            'tcn.caller_id'     => $tenant->tcn_caller_id     ?? env('TCN_FROM', ''),
        ]);

        // ── Meta WhatsApp ────────────────────────────────────────────────────
        config([
            'whatsapp.token'               => $tenant->wa_token               ?? env('META_WHATSAPP_TOKEN', ''),
            'whatsapp.phone_number_id'     => $tenant->wa_phone_number_id     ?? env('META_WHATSAPP_PHONE_NUMBER_ID', ''),
            'whatsapp.business_account_id' => $tenant->wa_business_account_id ?? env('META_WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
            'whatsapp.verify_token'        => $tenant->wa_verify_token        ?? env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
            'whatsapp.template_name'       => $tenant->wa_template_name       ?? env('META_WHATSAPP_DEFAULT_TEMPLATE', 'hello_world'),
            'whatsapp.template_language'   => $tenant->wa_template_language   ?? env('META_WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE', 'en'),
        ]);

        // ── Zoom ──────────────────────────────────────────────────────────────
        config([
            'zoom.account_id'    => $tenant->zoom_account_id   ?? env('ZOOM_ACCOUNT_ID', ''),
            'zoom.client_id'     => $tenant->zoom_client_id    ?? env('ZOOM_CLIENT_ID', ''),
            'zoom.client_secret' => $tenant->zoom_client_secret ?? env('ZOOM_CLIENT_SECRET', ''),
        ]);

        // ── Google ────────────────────────────────────────────────────────────
        config([
            'google.client_id'     => $tenant->google_client_id     ?? env('GOOGLE_CLIENT_ID', ''),
            'google.client_secret' => $tenant->google_client_secret ?? env('GOOGLE_CLIENT_SECRET', ''),
        ]);

        // ── Real-time Broadcasting ────────────────────────────────────────────
        $driver = $tenant->broadcast_driver ?? 'null';
        config(['broadcasting.default' => $driver]);

        if ($driver === 'pusher') {
            config([
                'broadcasting.connections.pusher.key'             => $tenant->pusher_key     ?? env('PUSHER_APP_KEY', ''),
                'broadcasting.connections.pusher.secret'          => $tenant->pusher_secret  ?? env('PUSHER_APP_SECRET', ''),
                'broadcasting.connections.pusher.app_id'          => $tenant->pusher_app_id  ?? env('PUSHER_APP_ID', ''),
                'broadcasting.connections.pusher.options.cluster' => $tenant->pusher_cluster ?? env('PUSHER_APP_CLUSTER', 'mt1'),
            ]);
        } elseif ($driver === 'reverb') {
            config([
                'broadcasting.connections.reverb.key'            => $tenant->reverb_key    ?? env('REVERB_APP_KEY', ''),
                'broadcasting.connections.reverb.secret'         => $tenant->reverb_secret ?? env('REVERB_APP_SECRET', ''),
                'broadcasting.connections.reverb.app_id'         => $tenant->reverb_app_id ?? env('REVERB_APP_ID', ''),
                'broadcasting.connections.reverb.options.host'   => $tenant->reverb_host   ?? env('REVERB_HOST', '0.0.0.0'),
                'broadcasting.connections.reverb.options.port'   => $tenant->reverb_port   ?? env('REVERB_PORT', 8080),
                'broadcasting.connections.reverb.options.scheme' => $tenant->reverb_scheme ?? env('REVERB_SCHEME', 'http'),
            ]);
        }
    }
}
