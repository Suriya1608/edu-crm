<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('user_id');

            $table->enum('type', [
                'note',
                'call',
                'whatsapp',
                'sms',
                'status_change',
                'followup'
            ]);

            $table->text('description')->nullable();
            $table->json('meta_data')->nullable();

            $table->timestamp('activity_time')->useCurrent();

            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
