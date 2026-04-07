<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasIndex = function (string $table, string $indexName): bool {
            if (DB::getDriverName() === 'sqlite') {
                foreach (DB::select("PRAGMA index_list('{$table}')") as $index) {
                    if (($index->name ?? null) === $indexName) return true;
                }
                return false;
            }
            return !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]));
        };

        // call_logs: queried heavily by user_id and (user_id, created_at) in dashboard + panelSnapshot
        if (Schema::hasTable('call_logs')) {
            Schema::table('call_logs', function (Blueprint $table) use ($hasIndex) {
                if (!$hasIndex('call_logs', 'call_logs_user_id_index')) {
                    $table->index('user_id', 'call_logs_user_id_index');
                }
                if (!$hasIndex('call_logs', 'call_logs_lead_id_index')) {
                    $table->index('lead_id', 'call_logs_lead_id_index');
                }
                if (!$hasIndex('call_logs', 'call_logs_user_created_index')) {
                    $table->index(['user_id', 'created_at'], 'call_logs_user_created_index');
                }
            });
        }

        // followups: queried heavily by next_followup date and (lead_id, next_followup)
        if (Schema::hasTable('followups')) {
            Schema::table('followups', function (Blueprint $table) use ($hasIndex) {
                if (!$hasIndex('followups', 'followups_next_followup_index')) {
                    $table->index('next_followup', 'followups_next_followup_index');
                }
                if (!$hasIndex('followups', 'followups_lead_next_index')) {
                    $table->index(['lead_id', 'next_followup'], 'followups_lead_next_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('call_logs')) {
            Schema::table('call_logs', function (Blueprint $table) {
                $table->dropIndexIfExists('call_logs_user_id_index');
                $table->dropIndexIfExists('call_logs_lead_id_index');
                $table->dropIndexIfExists('call_logs_user_created_index');
            });
        }

        if (Schema::hasTable('followups')) {
            Schema::table('followups', function (Blueprint $table) {
                $table->dropIndexIfExists('followups_next_followup_index');
                $table->dropIndexIfExists('followups_lead_next_index');
            });
        }
    }
};
