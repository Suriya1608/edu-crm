<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('call_logs', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('answered_at');
            }

            if (!Schema::hasColumn('call_logs', 'end_reason')) {
                $table->string('end_reason')->nullable()->after('ended_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            if (Schema::hasColumn('call_logs', 'end_reason')) {
                $table->dropColumn('end_reason');
            }

            if (Schema::hasColumn('call_logs', 'ended_at')) {
                $table->dropColumn('ended_at');
            }
        });
    }
};
