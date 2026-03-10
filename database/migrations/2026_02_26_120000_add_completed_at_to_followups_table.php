<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            if (!Schema::hasColumn('followups', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('next_followup');
            }
        });
    }

    public function down(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            if (Schema::hasColumn('followups', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};

