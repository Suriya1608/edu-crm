<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseIntake;
use Illuminate\Http\Request;

class CourseIntakeController extends Controller
{
    public function index(Request $request)
    {
        $years        = AcademicYear::orderByDesc('id')->get();
        $activeYear   = AcademicYear::current();
        $selectedYear = $request->year_id
            ? AcademicYear::find($request->year_id)
            : $activeYear;

        $intakes = $selectedYear
            ? CourseIntake::with(['course', 'academicYear'])
                ->where('academic_year_id', $selectedYear->id)
                ->orderBy('id')
                ->get()
            : collect();

        return view('admin.course-intakes.index', compact('years', 'selectedYear', 'intakes', 'activeYear'));
    }

    public function create()
    {
        $years   = AcademicYear::orderByDesc('id')->get();
        $courses = Course::active()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.course-intakes.create', compact('years', 'courses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year_id'  => 'required|exists:academic_years,id',
            'course_id'         => 'required|exists:courses,id',
            'management_seats'  => 'required|integer|min:0|max:9999',
            'counselling_seats' => 'required|integer|min:0|max:9999',
        ]);

        $exists = CourseIntake::withTrashed()
            ->where('course_id', $data['course_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->first();

        if ($exists) {
            if ($exists->trashed()) {
                $exists->restore();
                $exists->update([
                    'management_seats'   => $data['management_seats'],
                    'counselling_seats'  => $data['counselling_seats'],
                    'management_enrolled'  => 0,
                    'counselling_enrolled' => 0,
                ]);
            } else {
                return back()->withErrors(['course_id' => 'An intake for this course and year already exists.'])->withInput();
            }
        } else {
            CourseIntake::create($data + [
                'management_enrolled'  => 0,
                'counselling_enrolled' => 0,
            ]);
        }

        return redirect()->route('admin.course-intakes.index', ['year_id' => $data['academic_year_id']])
            ->with('success', 'Intake created successfully.');
    }

    public function edit(CourseIntake $courseIntake)
    {
        return view('admin.course-intakes.edit', compact('courseIntake'));
    }

    public function update(Request $request, CourseIntake $courseIntake)
    {
        $data = $request->validate([
            'management_seats'  => 'required|integer|min:' . $courseIntake->management_enrolled . '|max:9999',
            'counselling_seats' => 'required|integer|min:' . $courseIntake->counselling_enrolled . '|max:9999',
        ]);

        $courseIntake->update($data);

        return redirect()->route('admin.course-intakes.index', ['year_id' => $courseIntake->academic_year_id])
            ->with('success', 'Intake updated.');
    }

    public function destroy(CourseIntake $courseIntake)
    {
        $yearId = $courseIntake->academic_year_id;
        $courseIntake->delete();

        return redirect()->route('admin.course-intakes.index', ['year_id' => $yearId])
            ->with('success', 'Intake removed (soft-deleted). Historical leads retain their course reference.');
    }
}
