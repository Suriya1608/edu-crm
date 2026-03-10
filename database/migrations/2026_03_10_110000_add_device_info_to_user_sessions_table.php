<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('user_id');
            $table->string('user_agent', 500)->nullable()->after('ip_address');
            $table->string('device_type', 30)->nullable()->after('user_agent');
            $table->string('browser', 80)->nullable()->after('device_type');
            $table->string('platform', 80)->nullable()->after('browser');
        });
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'device_type', 'browser', 'platform']);
        });
    }
};
