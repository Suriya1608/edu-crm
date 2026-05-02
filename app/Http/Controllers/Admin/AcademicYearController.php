<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index()
    {
        $years = AcademicYear::orderByDesc('id')->paginate(20);

        return view('admin.academic-years.index', compact('years'));
    }

    public function create()
    {
        return view('admin.academic-years.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:20|unique:academic_years,name',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'is_active'  => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if ($data['is_active']) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
        }

        AcademicYear::create($data);

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Academic year created successfully.');
    }

    public function edit(AcademicYear $academicYear)
    {
        return view('admin.academic-years.edit', compact('academicYear'));
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:20|unique:academic_years,name,' . $academicYear->id,
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'is_active'  => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if ($data['is_active']) {
            AcademicYear::where('is_active', true)
                ->where('id', '!=', $academicYear->id)
                ->update(['is_active' => false]);
        }

        $academicYear->update($data);

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Academic year updated.');
    }

    public function toggleActive(AcademicYear $academicYear)
    {
        if (!$academicYear->is_active) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
            $msg = '"' . $academicYear->name . '" is now the active year.';
        } else {
            $academicYear->update(['is_active' => false]);
            $msg = '"' . $academicYear->name . '" deactivated.';
        }

        return back()->with('success', $msg);
    }

    public function destroy(AcademicYear $academicYear)
    {
        if ($academicYear->intakes()->exists()) {
            return back()->with('error', 'Cannot delete — this year has course intakes attached. Remove intakes first.');
        }

        $academicYear->delete();

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Academic year deleted.');
    }
}
