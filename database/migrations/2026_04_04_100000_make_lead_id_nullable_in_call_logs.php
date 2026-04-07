<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            // Allow TCN/softphone calls that are not linked to a specific lead
            // (e.g. dialled directly from the softphone UI without a lead context).
            $table->unsignedBigInteger('lead_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable(false)->change();
        });
    }
};
