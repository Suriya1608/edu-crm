<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('course')->nullable();
            $table->string('city')->nullable();
            $table->enum('status', [
                'pending',
                'called',
                'interested',
                'not_interested',
                'no_answer',
                'callback',
                'converted',
            ])->default('pending');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('next_followup')->nullable();
            $table->unsignedInteger('call_count')->default(0);
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index(['campaign_id', 'assigned_to']);
            $table->index(['phone', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_contacts');
    }
};
