<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            if (Schema::hasColumn('call_logs', 'twilio_call_sid')) {
                $table->dropColumn('twilio_call_sid');
            }
            if (Schema::hasColumn('call_logs', 'telecaller_id')) {
                $table->dropColumn('telecaller_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('call_logs', 'telecaller_id')) {
                $table->unsignedBigInteger('telecaller_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('call_logs', 'twilio_call_sid')) {
                $table->string('twilio_call_sid')->nullable()->after('call_sid');
            }
        });
    }
};

