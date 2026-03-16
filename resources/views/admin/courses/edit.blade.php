@extends('layouts.app')

@section('page_title', 'Edit Course')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back
        </a>
        <div>
            <h2 class="page-header-title mb-0">Edit Course</h2>
            <p class="page-header-subtitle mb-0">{{ $course->name }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card" style="max-width:600px;">
        <form action="{{ route('admin.courses.update', $course->id) }}" method="POST">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold">Course Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $course->name) }}" placeholder="e.g. Bachelor of Computer Applications">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Short Code</label>
                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                    value="{{ old('code', $course->code) }}" placeholder="e.g. BCA" style="max-width:160px;">
                <div class="form-text">Optional short identifier shown in compact views.</div>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3"
                    placeholder="Optional details about this course">{{ old('description', $course->description) }}</textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control"
                        value="{{ old('sort_order', $course->sort_order) }}" min="0">
                    <div class="form-text">Lower numbers appear first in dropdowns.</div>
                </div>
                <div class="col-sm-6 d-flex align-items-end pb-1">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                            {{ old('is_active', $course->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="isActive">Active</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:16px;">save</span>Update Course
                </button>
                <a href="{{ route('admin.courses.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
@endsection
