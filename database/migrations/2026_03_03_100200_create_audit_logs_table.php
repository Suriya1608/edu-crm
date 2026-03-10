<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                // Nullable so system-level actions (e.g. scheduler) can still be logged
                $table->unsignedBigInteger('user_id')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

                // Dot-notation action: "lead.status_changed", "settings.updated", etc.
                $table->string('action', 100)->index();

                // Eloquent model class short name (e.g. "Lead", "User", "Setting")
                $table->string('model', 100)->nullable()->index();
                $table->unsignedBigInteger('model_id')->nullable()->index();

                // JSON snapshots — null when not applicable
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();

                $table->string('ip_address', 45)->nullable();

                // Immutable audit record — no updated_at
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
