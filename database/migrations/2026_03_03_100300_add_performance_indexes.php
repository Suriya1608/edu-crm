<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Helper: only add index if it doesn't exist (MySQL-safe)
        $hasIndex = function (string $table, string $indexName): bool {
            $result = DB::select(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
                [$indexName]
            );
            return !empty($result);
        };

        Schema::table('leads', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('leads', 'leads_course_index')) {
                $table->index('course', 'leads_course_index');
            }
            if (!$hasIndex('leads', 'leads_created_at_index')) {
                $table->index('created_at', 'leads_created_at_index');
            }
            if (!$hasIndex('leads', 'leads_phone_index')) {
                $table->index('phone', 'leads_phone_index');
            }
            if (!$hasIndex('leads', 'leads_assigned_by_status_index')) {
                $table->index(['assigned_by', 'status'], 'leads_assigned_by_status_index');
            }
            if (!$hasIndex('leads', 'leads_assigned_to_status_index')) {
                $table->index(['assigned_to', 'status'], 'leads_assigned_to_status_index');
            }
        });

        if (Schema::hasTable('call_logs') && Schema::hasColumn('call_logs', 'outcome')) {
            Schema::table('call_logs', function (Blueprint $table) use ($hasIndex) {
                if (!$hasIndex('call_logs', 'call_logs_outcome_index')) {
                    $table->index('outcome', 'call_logs_outcome_index');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndexIfExists('leads_course_index');
            $table->dropIndexIfExists('leads_created_at_index');
            $table->dropIndexIfExists('leads_phone_index');
            $table->dropIndexIfExists('leads_assigned_by_status_index');
            $table->dropIndexIfExists('leads_assigned_to_status_index');
        });

        if (Schema::hasTable('call_logs')) {
            Schema::table('call_logs', function (Blueprint $table) {
                $table->dropIndexIfExists('call_logs_outcome_index');
            });
        }
    }
};
