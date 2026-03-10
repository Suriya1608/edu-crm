<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'is_duplicate')) {
                $table->boolean('is_duplicate')->default(false)->after('status');
            }
            if (!Schema::hasColumn('leads', 'merged_into_lead_id')) {
                $table->unsignedBigInteger('merged_into_lead_id')->nullable()->after('is_duplicate');
                $table->foreign('merged_into_lead_id')->references('id')->on('leads')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'merged_into_lead_id')) {
                $table->dropForeign(['merged_into_lead_id']);
                $table->dropColumn('merged_into_lead_id');
            }
            if (Schema::hasColumn('leads', 'is_duplicate')) {
                $table->dropColumn('is_duplicate');
            }
        });
    }
};
