<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires redefining the full enum to add a new value
        DB::statement("ALTER TABLE email_campaign_recipients MODIFY COLUMN status ENUM('pending','sent','failed','bounced','opened') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert — rows with status='opened' become 'sent' first to avoid constraint error
        DB::statement("UPDATE email_campaign_recipients SET status = 'sent' WHERE status = 'opened'");
        DB::statement("ALTER TABLE email_campaign_recipients MODIFY COLUMN status ENUM('pending','sent','failed','bounced') NOT NULL DEFAULT 'pending'");
    }
};
