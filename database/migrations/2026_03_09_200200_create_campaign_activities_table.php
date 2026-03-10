<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_contact_id');
            $table->enum('type', ['call', 'whatsapp', 'note', 'status_change', 'followup_set']);
            $table->text('description')->nullable();
            $table->json('meta')->nullable(); // call outcome, duration, old_status, new_status, etc.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('campaign_contact_id')->references('id')->on('campaign_contacts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['campaign_contact_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_activities');
    }
};
