<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->after('course');
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE leads l
                INNER JOIN courses c ON LOWER(TRIM(l.course)) = LOWER(TRIM(c.name))
                SET l.course_id = c.id
                WHERE l.course IS NOT NULL AND l.course != ''
            ");
        } else {
            $courseMap = DB::table('courses')
                ->select('id', 'name')
                ->get()
                ->mapWithKeys(fn ($course) => [mb_strtolower(trim((string) $course->name)) => $course->id]);

            DB::table('leads')
                ->select('id', 'course')
                ->whereNotNull('course')
                ->orderBy('id')
                ->lazy()
                ->each(function ($lead) use ($courseMap) {
                    $normalized = mb_strtolower(trim((string) $lead->course));

                    if ($normalized !== '' && $courseMap->has($normalized)) {
                        DB::table('leads')
                            ->where('id', $lead->id)
                            ->update(['course_id' => $courseMap->get($normalized)]);
                    }
                });
        }

        if (Schema::hasColumn('leads', 'course')) {
            try {
                DB::statement('DROP INDEX leads_course_index');
            } catch (\Throwable $e) {
                // Ignore when the index does not exist for the active driver.
            }
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('course');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('course')->nullable()->after('course_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE leads l
                INNER JOIN courses c ON l.course_id = c.id
                SET l.course = c.name
                WHERE l.course_id IS NOT NULL
            ");
        } else {
            DB::table('leads')
                ->join('courses', 'leads.course_id', '=', 'courses.id')
                ->select('leads.id', 'courses.name')
                ->orderBy('leads.id')
                ->lazy()
                ->each(function ($lead) {
                    DB::table('leads')
                        ->where('id', $lead->id)
                        ->update(['course' => $lead->name]);
                });
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }
};
