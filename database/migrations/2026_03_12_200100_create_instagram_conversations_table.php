<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instagram_account_id');
            $table->string('sender_id');               // Instagram PSID of the contact
            $table->string('sender_name')->nullable();
            $table->string('sender_username')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();

            $table->unique(['instagram_account_id', 'sender_id']);
            $table->index('last_message_at');

            $table->foreign('instagram_account_id')
                ->references('id')->on('instagram_accounts')->cascadeOnDelete();
            $table->foreign('assigned_to')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_conversations');
    }
};
