<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_bounces', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->foreign('campaign_id')->references('id')->on('email_campaigns')->nullOnDelete();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->foreign('recipient_id')->references('id')->on('email_campaign_recipients')->nullOnDelete();
            $table->string('bounce_type', 20)->default('hard'); // hard | soft
            $table->text('reason')->nullable();
            $table->string('provider', 50)->nullable(); // mailgun | sendgrid | ses | other
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_bounces');
    }
};
