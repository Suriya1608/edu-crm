<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central_mysql';

    public function up(): void
    {
        Schema::connection('central_mysql')->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain', 100)->unique();
            $table->string('db_name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('plan', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central_mysql')->dropIfExists('tenants');
    }
};
