<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('total_seats');
            $table->unsignedSmallInteger('enrolled_seats')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['course_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_intakes');
    }
};
