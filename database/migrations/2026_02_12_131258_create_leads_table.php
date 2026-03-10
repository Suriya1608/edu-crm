<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('course')->nullable();
            $table->string('source')->default('meta_ads');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->enum('status', [
                'new',
                'assigned',
                'contacted',
                'interested',
                'not_interested',
                'converted'
            ])->default('new');
            $table->date('next_followup')->nullable();
            $table->timestamps();

            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
