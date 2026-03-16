<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_campaign_id');
            $table->foreign('email_campaign_id')->references('id')->on('email_campaigns')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('tracking_token', 64)->unique();
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index('email_campaign_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
    }
};
