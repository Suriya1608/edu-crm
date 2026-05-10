<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central_mysql';

    public function up(): void
    {
        Schema::connection('central_mysql')->table('tenants', function (Blueprint $table) {
            // Site details (non-sensitive, plain JSON)
            $table->json('site_config')->nullable()->after('plan');

            // All sensitive credential blocks stored as encrypted text
            $table->text('smtp_config')->nullable()->after('site_config');
            $table->text('tcn_config')->nullable()->after('smtp_config');
            $table->text('whatsapp_config')->nullable()->after('tcn_config');
            $table->text('zoom_config')->nullable()->after('whatsapp_config');
            $table->text('google_config')->nullable()->after('zoom_config');
            $table->text('broadcast_config')->nullable()->after('google_config');
        });
    }

    public function down(): void
    {
        Schema::connection('central_mysql')->table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'site_config', 'smtp_config', 'tcn_config',
                'whatsapp_config', 'zoom_config', 'google_config', 'broadcast_config',
            ]);
        });
    }
};
