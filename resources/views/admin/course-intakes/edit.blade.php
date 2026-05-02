@extends('layouts.app')

@section('page_title', 'Edit Intake')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.course-intakes.index', ['year_id' => $courseIntake->academic_year_id]) }}"
            class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back
        </a>
        <div>
            <h2 class="page-header-title mb-0">Edit Intake</h2>
            <p class="page-header-subtitle mb-0">
                {{ $courseIntake->course?->name }} — {{ $courseIntake->academicYear?->name }}
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card" style="max-width:560px;">

        {{-- Current stats (read-only) --}}
        <div class="row g-2 mb-4 pb-3" style="border-bottom:1px solid #e2e8f0;">
            <div class="col-4 text-center">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Total Seats</div>
                <div class="fw-bold" style="font-size:22px;">{{ $courseIntake->total_seats }}</div>
            </div>
            <div class="col-4 text-center">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Enrolled</div>
                <div class="fw-bold text-success" style="font-size:22px;">{{ $courseIntake->total_enrolled }}</div>
            </div>
            <div class="col-4 text-center">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Balance</div>
                <div class="fw-bold {{ $courseIntake->balance_seats <= 0 ? 'text-danger' : 'text-primary' }}" style="font-size:22px;">
                    {{ $courseIntake->balance_seats }}
                </div>
            </div>
        </div>

        {{-- Per-quota breakdown (read-only) --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <div class="p-3 rounded" style="background:#eef2ff;border:1px solid #c7d2fe;">
                    <div class="fw-semibold mb-2" style="font-size:12px;color:#4f46e5;">
                        <span class="badge me-1" style="background:#6366f1;">M</span>Management
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div style="font-size:11px;color:#64748b;">Enrolled / Seats</div>
                            <div class="fw-bold">{{ $courseIntake->management_enrolled }} / {{ $courseIntake->management_seats }}</div>
                        </div>
                        <div class="text-end">
                            <div style="font-size:11px;color:#64748b;">Balance</div>
                            <div class="fw-bold {{ $courseIntake->management_balance <= 0 ? 'text-danger' : 'text-primary' }}">
                                {{ $courseIntake->management_balance }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="p-3 rounded" style="background:#ecfdf5;border:1px solid #a7f3d0;">
                    <div class="fw-semibold mb-2" style="font-size:12px;color:#059669;">
                        <span class="badge me-1" style="background:#10b981;">C</span>Counselling
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div style="font-size:11px;color:#64748b;">Enrolled / Seats</div>
                            <div class="fw-bold">{{ $courseIntake->counselling_enrolled }} / {{ $courseIntake->counselling_seats }}</div>
                        </div>
                        <div class="text-end">
                            <div style="font-size:11px;color:#64748b;">Balance</div>
                            <div class="fw-bold {{ $courseIntake->counselling_balance <= 0 ? 'text-danger' : 'text-success' }}">
                                {{ $courseIntake->counselling_balance }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.course-intakes.update', $courseIntake->id) }}" method="POST">
            @csrf @method('PUT')

            <div class="fw-semibold mb-3" style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;">
                Adjust Seat Allocation
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">
                        <span class="badge me-1" style="background:#6366f1;">M</span>Management Seats
                    </label>
                    <input type="number" name="management_seats"
                        class="form-control @error('management_seats') is-invalid @enderror"
                        value="{{ old('management_seats', $courseIntake->management_seats) }}"
                        min="{{ $courseIntake->management_enrolled }}" max="9999">
                    <div class="form-text">Min: {{ $courseIntake->management_enrolled }} (enrolled)</div>
                    @error('management_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">
                        <span class="badge me-1" style="background:#10b981;">C</span>Counselling Seats
                    </label>
                    <input type="number" name="counselling_seats"
                        class="form-control @error('counselling_seats') is-invalid @enderror"
                        value="{{ old('counselling_seats', $courseIntake->counselling_seats) }}"
                        min="{{ $courseIntake->counselling_enrolled }}" max="9999">
                    <div class="form-text">Min: {{ $courseIntake->counselling_enrolled }} (enrolled)</div>
                    @error('counselling_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:16px;">save</span>Update
                </button>
                <a href="{{ route('admin.course-intakes.index', ['year_id' => $courseIntake->academic_year_id]) }}"
                    class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
@endsection
