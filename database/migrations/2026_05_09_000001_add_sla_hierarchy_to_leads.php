<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->tinyInteger('sla_level')->unsigned()->default(0)->after('sla_escalated_at');
            $table->timestamp('sla_manager_deadline_at')->nullable()->after('sla_level');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['sla_level', 'sla_manager_deadline_at']);
        });
    }
};
