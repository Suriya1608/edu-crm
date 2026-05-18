<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('email');
            $table->date('dob')->nullable()->after('gender');
            $table->text('address')->nullable()->after('dob');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('district', 100)->nullable()->after('city');
            $table->string('state', 100)->nullable()->after('district');
            $table->string('pincode', 10)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['gender', 'dob', 'address', 'city', 'district', 'state', 'pincode']);
        });
    }
};
