<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_intakes', function (Blueprint $table) {
            $table->unsignedSmallInteger('management_seats')->default(0)->after('academic_year_id');
            $table->unsignedSmallInteger('counselling_seats')->default(0)->after('management_seats');
            $table->unsignedSmallInteger('management_enrolled')->default(0)->after('counselling_seats');
            $table->unsignedSmallInteger('counselling_enrolled')->default(0)->after('management_enrolled');
        });

        // Transfer existing data: split total_seats evenly, move enrolled to management
        DB::statement('
            UPDATE course_intakes SET
                management_seats     = CEIL(total_seats / 2),
                counselling_seats    = FLOOR(total_seats / 2),
                management_enrolled  = enrolled_seats,
                counselling_enrolled = 0
        ');

        Schema::table('course_intakes', function (Blueprint $table) {
            $table->dropColumn(['total_seats', 'enrolled_seats']);
        });
    }

    public function down(): void
    {
        Schema::table('course_intakes', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_seats')->default(0)->after('academic_year_id');
            $table->unsignedSmallInteger('enrolled_seats')->default(0)->after('total_seats');
        });

        DB::statement('
            UPDATE course_intakes SET
                total_seats    = management_seats + counselling_seats,
                enrolled_seats = management_enrolled + counselling_enrolled
        ');

        Schema::table('course_intakes', function (Blueprint $table) {
            $table->dropColumn(['management_seats', 'counselling_seats', 'management_enrolled', 'counselling_enrolled']);
        });
    }
};
