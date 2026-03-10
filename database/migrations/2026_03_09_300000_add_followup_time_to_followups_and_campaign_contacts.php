<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add followup_time to followups table
        Schema::table('followups', function (Blueprint $table) {
            $table->time('followup_time')->nullable()->after('next_followup');
        });

        // Add followup_time to campaign_contacts table
        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->time('followup_time')->nullable()->after('next_followup');
        });
    }

    public function down(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            $table->dropColumn('followup_time');
        });

        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->dropColumn('followup_time');
        });
    }
};
