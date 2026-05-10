<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $connection = 'central_mysql';
    protected $table      = 'tenants';

    protected $fillable = [
        'name', 'subdomain', 'db_name', 'is_active', 'plan',
        // Site
        'site_url', 'site_timezone', 'site_logo', 'site_favicon', 'site_telephony_provider',
        // SMTP
        'smtp_mailer', 'smtp_host', 'smtp_port', 'smtp_encryption',
        'smtp_username', 'smtp_password', 'smtp_from_address', 'smtp_from_name',
        // TCN
        'tcn_client_id', 'tcn_client_secret', 'tcn_refresh_token',
        'tcn_client_sid', 'tcn_caller_id', 'tcn_redirect_uri',
        // WhatsApp
        'wa_token', 'wa_phone_number_id', 'wa_business_account_id',
        'wa_verify_token', 'wa_template_name', 'wa_template_language',
        // Zoom
        'zoom_account_id', 'zoom_client_id', 'zoom_client_secret',
        // Google
        'google_client_id', 'google_client_secret',
        // Broadcast
        'broadcast_driver',
        'pusher_app_id', 'pusher_key', 'pusher_secret', 'pusher_cluster',
        'reverb_app_id', 'reverb_key', 'reverb_secret',
        'reverb_host', 'reverb_port', 'reverb_scheme',
    ];

    protected $casts = [
        'is_active' => 'boolean',

        // Laravel auto-encrypts on write, decrypts on read — null stays null
        'smtp_password'       => 'encrypted',
        'tcn_client_id'       => 'encrypted',
        'tcn_client_secret'   => 'encrypted',
        'tcn_refresh_token'   => 'encrypted',
        'wa_token'            => 'encrypted',
        'zoom_account_id'     => 'encrypted',
        'zoom_client_id'      => 'encrypted',
        'zoom_client_secret'  => 'encrypted',
        'google_client_secret' => 'encrypted',
        'pusher_secret'       => 'encrypted',
        'reverb_secret'       => 'encrypted',
    ];
}
