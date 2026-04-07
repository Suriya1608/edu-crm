<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->timestamp('bounced_at')->nullable()->after('opened_at');
            $table->string('bounce_type', 10)->nullable()->after('bounced_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->dropColumn(['bounced_at', 'bounce_type']);
        });
    }
};
