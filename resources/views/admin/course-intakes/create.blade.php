@extends('layouts.app')

@section('page_title', 'Add Course Intake')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.course-intakes.index') }}" class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back
        </a>
        <div>
            <h2 class="page-header-title mb-0">Add Course Intake</h2>
            <p class="page-header-subtitle mb-0">Set seat allocation for a course in an academic year</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($years->isEmpty())
        <div class="alert alert-warning">
            No academic years found. <a href="{{ route('admin.academic-years.create') }}">Create one first.</a>
        </div>
    @elseif ($courses->isEmpty())
        <div class="alert alert-warning">
            No active courses found. <a href="{{ route('admin.courses.create') }}">Create a course first.</a>
        </div>
    @else
        <div class="chart-card" style="max-width:560px;">
            <form action="{{ route('admin.course-intakes.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror">
                        <option value="">— Select Year —</option>
                        @foreach ($years as $y)
                            <option value="{{ $y->id }}" {{ old('academic_year_id') == $y->id ? 'selected' : '' }}>
                                {{ $y->name }}{{ $y->is_active ? ' (Current)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('academic_year_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror">
                        <option value="">— Select Course —</option>
                        @foreach ($courses as $c)
                            <option value="{{ $c->id }}" {{ old('course_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}{{ $c->code ? ' (' . $c->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Quota seat split --}}
                <div class="p-3 rounded mb-4" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <div class="fw-semibold mb-3" style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;">
                        Seat Allocation by Quota
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">
                                <span class="badge me-1" style="background:#6366f1;">M</span>Management Seats <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="management_seats"
                                class="form-control @error('management_seats') is-invalid @enderror"
                                value="{{ old('management_seats', 0) }}" min="0" max="9999"
                                placeholder="e.g. 30">
                            @error('management_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">
                                <span class="badge me-1" style="background:#10b981;">C</span>Counselling Seats <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="counselling_seats"
                                class="form-control @error('counselling_seats') is-invalid @enderror"
                                value="{{ old('counselling_seats', 0) }}" min="0" max="9999"
                                placeholder="e.g. 30">
                            @error('counselling_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mt-2 text-muted" style="font-size:12px;">
                        <span class="material-icons" style="font-size:14px;vertical-align:middle;">info</span>
                        Total seats = Management + Counselling. Enrolled leads are tracked per quota.
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons me-1" style="font-size:16px;">save</span>Save Intake
                    </button>
                    <a href="{{ route('admin.course-intakes.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    @endif
@endsection
