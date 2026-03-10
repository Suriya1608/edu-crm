<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->string('location_area', 100)->nullable()->after('platform');
            $table->string('location_city', 100)->nullable()->after('location_area');
            $table->string('location_state', 100)->nullable()->after('location_city');
            $table->string('location_country', 100)->nullable()->after('location_state');
        });
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropColumn(['location_area', 'location_city', 'location_state', 'location_country']);
        });
    }
};
