@extends('layouts.app')

@section('page_title', 'Edit Academic Year')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.academic-years.index') }}" class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back
        </a>
        <div>
            <h2 class="page-header-title mb-0">Edit Academic Year</h2>
            <p class="page-header-subtitle mb-0">{{ $academicYear->name }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card" style="max-width:560px;">
        <form action="{{ route('admin.academic-years.update', $academicYear->id) }}" method="POST">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold">Year Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $academicYear->name) }}" placeholder="e.g. 2026-27" style="max-width:180px;">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Start Date</label>
                    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
                        value="{{ old('start_date', $academicYear->start_date?->format('Y-m-d')) }}">
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">End Date</label>
                    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
                        value="{{ old('end_date', $academicYear->end_date?->format('Y-m-d')) }}">
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                        {{ old('is_active', $academicYear->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActive">Active Year</label>
                </div>
                <div class="form-text">Enabling this will deactivate any other currently active year.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:16px;">save</span>Update
                </button>
                <a href="{{ route('admin.academic-years.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
@endsection
