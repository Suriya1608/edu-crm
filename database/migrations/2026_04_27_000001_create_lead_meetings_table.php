<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title', 255)->default('CRM Meeting');
            $table->string('meeting_link', 512)->nullable();
            $table->string('google_event_id', 255)->nullable();
            $table->dateTime('meeting_time');
            $table->unsignedSmallInteger('duration')->default(60);
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'missed'])->default('scheduled');
            $table->boolean('whatsapp_sent')->default(false);
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['lead_id', 'meeting_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_meetings');
    }
};
