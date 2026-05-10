<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central_mysql';

    public function up(): void
    {
        // Drop the old JSON-blob columns (all values are NULL — no data loss)
        Schema::connection('central_mysql')->table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'site_config', 'smtp_config', 'tcn_config',
                'whatsapp_config', 'zoom_config', 'google_config', 'broadcast_config',
            ]);
        });

        Schema::connection('central_mysql')->table('tenants', function (Blueprint $table) {
            // ── Site (non-sensitive) ───────────────────────────────────────────
            $table->string('site_url', 255)->nullable()->after('plan');
            $table->string('site_timezone', 100)->default('UTC')->after('site_url');
            $table->text('site_logo')->nullable()->after('site_timezone');
            $table->text('site_favicon')->nullable()->after('site_logo');
            $table->string('site_telephony_provider', 20)->default('tcn')->after('site_favicon');

            // ── SMTP ──────────────────────────────────────────────────────────
            $table->string('smtp_mailer', 20)->default('smtp')->after('site_telephony_provider');
            $table->string('smtp_host', 255)->nullable()->after('smtp_mailer');
            $table->unsignedSmallInteger('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_encryption', 10)->nullable()->after('smtp_port');
            $table->string('smtp_username', 255)->nullable()->after('smtp_encryption');
            $table->text('smtp_password')->nullable()->after('smtp_username');           // encrypted
            $table->string('smtp_from_address', 255)->nullable()->after('smtp_password');
            $table->string('smtp_from_name', 255)->nullable()->after('smtp_from_address');

            // ── TCN Softphone ─────────────────────────────────────────────────
            $table->text('tcn_client_id')->nullable()->after('smtp_from_name');          // encrypted
            $table->text('tcn_client_secret')->nullable()->after('tcn_client_id');       // encrypted
            $table->text('tcn_refresh_token')->nullable()->after('tcn_client_secret');   // encrypted
            $table->string('tcn_client_sid', 100)->nullable()->after('tcn_refresh_token');
            $table->string('tcn_caller_id', 30)->nullable()->after('tcn_client_sid');
            $table->string('tcn_redirect_uri', 500)->nullable()->after('tcn_caller_id');

            // ── Meta WhatsApp ─────────────────────────────────────────────────
            $table->text('wa_token')->nullable()->after('tcn_redirect_uri');             // encrypted
            $table->string('wa_phone_number_id', 100)->nullable()->after('wa_token');
            $table->string('wa_business_account_id', 100)->nullable()->after('wa_phone_number_id');
            $table->string('wa_verify_token', 255)->nullable()->after('wa_business_account_id');
            $table->string('wa_template_name', 100)->default('hello_world')->after('wa_verify_token');
            $table->string('wa_template_language', 20)->default('en')->after('wa_template_name');

            // ── Zoom ──────────────────────────────────────────────────────────
            $table->text('zoom_account_id')->nullable()->after('wa_template_language');  // encrypted
            $table->text('zoom_client_id')->nullable()->after('zoom_account_id');        // encrypted
            $table->text('zoom_client_secret')->nullable()->after('zoom_client_id');     // encrypted

            // ── Google ────────────────────────────────────────────────────────
            $table->string('google_client_id', 255)->nullable()->after('zoom_client_secret');
            $table->text('google_client_secret')->nullable()->after('google_client_id'); // encrypted

            // ── Real-time Broadcasting ────────────────────────────────────────
            $table->string('broadcast_driver', 20)->default('null')->after('google_client_secret');
            $table->string('pusher_app_id', 100)->nullable()->after('broadcast_driver');
            $table->string('pusher_key', 100)->nullable()->after('pusher_app_id');
            $table->text('pusher_secret')->nullable()->after('pusher_key');              // encrypted
            $table->string('pusher_cluster', 20)->nullable()->after('pusher_secret');
            $table->string('reverb_app_id', 100)->nullable()->after('pusher_cluster');
            $table->string('reverb_key', 100)->nullable()->after('reverb_app_id');
            $table->text('reverb_secret')->nullable()->after('reverb_key');             // encrypted
            $table->string('reverb_host', 255)->nullable()->after('reverb_secret');
            $table->unsignedSmallInteger('reverb_port')->nullable()->after('reverb_host');
            $table->string('reverb_scheme', 10)->default('http')->after('reverb_port');
        });
    }

    public function down(): void
    {
        Schema::connection('central_mysql')->table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'site_url', 'site_timezone', 'site_logo', 'site_favicon', 'site_telephony_provider',
                'smtp_mailer', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
                'smtp_password', 'smtp_from_address', 'smtp_from_name',
                'tcn_client_id', 'tcn_client_secret', 'tcn_refresh_token',
                'tcn_client_sid', 'tcn_caller_id', 'tcn_redirect_uri',
                'wa_token', 'wa_phone_number_id', 'wa_business_account_id',
                'wa_verify_token', 'wa_template_name', 'wa_template_language',
                'zoom_account_id', 'zoom_client_id', 'zoom_client_secret',
                'google_client_id', 'google_client_secret',
                'broadcast_driver',
                'pusher_app_id', 'pusher_key', 'pusher_secret', 'pusher_cluster',
                'reverb_app_id', 'reverb_key', 'reverb_secret',
                'reverb_host', 'reverb_port', 'reverb_scheme',
            ]);
        });

        Schema::connection('central_mysql')->table('tenants', function (Blueprint $table) {
            $table->json('site_config')->nullable()->after('plan');
            $table->text('smtp_config')->nullable()->after('site_config');
            $table->text('tcn_config')->nullable()->after('smtp_config');
            $table->text('whatsapp_config')->nullable()->after('tcn_config');
            $table->text('zoom_config')->nullable()->after('whatsapp_config');
            $table->text('google_config')->nullable()->after('zoom_config');
            $table->text('broadcast_config')->nullable()->after('google_config');
        });
    }
};
