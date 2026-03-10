<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            if (!Schema::hasColumn('followups', 'reminder_notified_at')) {
                $table->timestamp('reminder_notified_at')->nullable()->after('escalated_at');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'sla_escalated_at')) {
                $table->timestamp('sla_escalated_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            if (Schema::hasColumn('followups', 'reminder_notified_at')) {
                $table->dropColumn('reminder_notified_at');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'sla_escalated_at')) {
                $table->dropColumn('sla_escalated_at');
            }
        });
    }
};
