<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('call_logs', 'answered_at')) {
                $table->timestamp('answered_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            if (Schema::hasColumn('call_logs', 'answered_at')) {
                $table->dropColumn('answered_at');
            }
        });
    }
};
