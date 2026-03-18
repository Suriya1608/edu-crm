<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_campaign_id');
            $table->foreign('email_campaign_id')->references('id')->on('email_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('recipient_id');
            $table->foreign('recipient_id')->references('id')->on('email_campaign_recipients')->cascadeOnDelete();
            $table->string('tracking_token', 64)->unique();
            $table->text('url');
            $table->timestamp('clicked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->index('email_campaign_id');
            $table->index('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_clicks');
    }
};
