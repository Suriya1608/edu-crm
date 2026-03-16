<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_name', 255);      // snapshot at send time
            $table->string('template_subject', 255);   // snapshot at send time
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->enum('status', ['pending', 'sending', 'completed', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('email_templates')->nullOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();

            $table->index('campaign_id');
            $table->index('sent_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_email_logs');
    }
};
