<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            // Speeds up latestOfMany('created_at') on Lead::lastActivity()
            // and any time-range queries on activities.
            $table->index(['lead_id', 'created_at'], 'la_lead_created_at');

            // Speeds up activity_time ordering used in telecaller/manager activity feeds.
            $table->index(['lead_id', 'activity_time'], 'la_lead_activity_time');
        });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex('la_lead_created_at');
            $table->dropIndex('la_lead_activity_time');
        });
    }
};
