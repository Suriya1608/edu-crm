<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_meetings', function (Blueprint $table) {
            $table->enum('meeting_type', ['google', 'zoom'])->default('google')->after('status');
            $table->string('zoom_meeting_id', 64)->nullable()->after('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('lead_meetings', function (Blueprint $table) {
            $table->dropColumn(['meeting_type', 'zoom_meeting_id']);
        });
    }
};
