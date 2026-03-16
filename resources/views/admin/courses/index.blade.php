@extends('layouts.app')

@section('page_title', 'Course Management')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-header-title mb-0">Courses</h2>
            <p class="page-header-subtitle mb-0">Manage courses available for leads and campaigns</p>
        </div>
        <a href="{{ route('admin.courses.create') }}" class="btn btn-primary btn-sm">
            <span class="material-icons me-1" style="font-size:16px;">add</span>New Course
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card">
        @if ($courses->isEmpty())
            <div class="text-center py-5 text-muted">
                <span class="material-icons" style="font-size:48px;opacity:.3;">school</span>
                <p class="mt-2">No courses added yet.</p>
                <a href="{{ route('admin.courses.create') }}" class="btn btn-primary btn-sm mt-1">Add First Course</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Course Name</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($courses as $course)
                            <tr>
                                <td class="text-muted" style="font-size:13px;">{{ $course->id }}</td>
                                <td class="fw-semibold">{{ $course->name }}</td>
                                <td class="text-muted" style="font-size:13px;">{{ $course->code ?: '—' }}</td>
                                <td class="text-muted" style="font-size:13px;">
                                    {{ $course->description ? Str::limit($course->description, 60) : '—' }}
                                </td>
                                <td class="text-muted" style="font-size:13px;">{{ $course->sort_order }}</td>
                                <td>
                                    <form action="{{ route('admin.courses.toggle-status', $course->id) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="badge border-0 {{ $course->is_active ? 'bg-success' : 'bg-secondary' }}"
                                            style="cursor:pointer;" title="Click to toggle">
                                            {{ $course->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.courses.edit', $course->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <span class="material-icons" style="font-size:15px;">edit</span>
                                        </a>
                                        <form action="{{ route('admin.courses.destroy', $course->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Delete this course?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                <span class="material-icons" style="font-size:15px;">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($courses->hasPages())
                <div class="mt-3 px-2">{{ $courses->links() }}</div>
            @endif
        @endif
    </div>
@endsection
