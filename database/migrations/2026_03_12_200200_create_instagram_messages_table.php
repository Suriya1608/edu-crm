<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('mid')->unique();              // Meta message ID — prevents duplicates
            $table->enum('direction', ['inbound', 'outbound']);
            $table->text('body');
            $table->unsignedBigInteger('sent_by')->nullable(); // CRM user who replied (null = inbound)
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'sent_at']);

            $table->foreign('conversation_id')
                ->references('id')->on('instagram_conversations')->cascadeOnDelete();
            $table->foreign('sent_by')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_messages');
    }
};
