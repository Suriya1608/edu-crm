<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('call_logs', 'outcome')) {
                $table->enum('outcome', [
                    'interested',
                    'not_interested',
                    'wrong_number',
                    'call_back_later',
                    'switched_off',
                ])->nullable()->after('duration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            if (Schema::hasColumn('call_logs', 'outcome')) {
                $table->dropColumn('outcome');
            }
        });
    }
};
