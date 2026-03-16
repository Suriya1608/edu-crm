<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('page_id')->unique();          // Meta Page ID
            $table->string('instagram_user_id')->nullable(); // IG Business account ID
            $table->string('name');                       // Display name
            $table->text('access_token');                 // Encrypted Page Access Token
            $table->text('app_secret')->nullable();       // Encrypted App Secret (for webhook sig)
            $table->string('verify_token', 128);          // Custom verify token for webhook setup
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_accounts');
    }
};
