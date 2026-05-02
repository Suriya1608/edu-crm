<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE lead_activities MODIFY COLUMN type ENUM('note','call','whatsapp','sms','status_change','followup','meeting','assignment') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lead_activities MODIFY COLUMN type ENUM('note','call','whatsapp','sms','status_change','followup','meeting') NOT NULL");
    }
};
