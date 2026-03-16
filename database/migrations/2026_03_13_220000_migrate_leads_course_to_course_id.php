<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1 — add course_id FK column
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->after('course');
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });

        // Step 2 — migrate existing string values to IDs (case-insensitive match)
        DB::statement("
            UPDATE leads l
            INNER JOIN courses c ON LOWER(TRIM(l.course)) = LOWER(TRIM(c.name))
            SET l.course_id = c.id
            WHERE l.course IS NOT NULL AND l.course != ''
        ");

        // Step 3 — drop the old string column
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('course');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('course')->nullable()->after('course_id');
        });

        DB::statement("
            UPDATE leads l
            INNER JOIN courses c ON l.course_id = c.id
            SET l.course = c.name
            WHERE l.course_id IS NOT NULL
        ");

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }
};
