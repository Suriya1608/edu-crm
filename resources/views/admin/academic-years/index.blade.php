@extends('layouts.app')

@section('page_title', 'Academic Years')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-header-title mb-0">Academic Years</h2>
            <p class="page-header-subtitle mb-0">Manage academic years — only one can be active at a time</p>
        </div>
        <a href="{{ route('admin.academic-years.create') }}" class="btn btn-primary btn-sm">
            <span class="material-icons me-1" style="font-size:16px;">add</span>New Academic Year
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card">
        @if ($years->isEmpty())
            <div class="text-center py-5 text-muted">
                <span class="material-icons" style="font-size:48px;opacity:.3;">calendar_today</span>
                <p class="mt-2">No academic years added yet.</p>
                <a href="{{ route('admin.academic-years.create') }}" class="btn btn-primary btn-sm mt-1">Add First Year</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Year</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Intakes</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($years as $year)
                            <tr>
                                <td class="text-muted" style="font-size:13px;">{{ $year->id }}</td>
                                <td class="fw-semibold">
                                    {{ $year->name }}
                                    @if ($year->is_active)
                                        <span class="badge bg-success ms-1" style="font-size:10px;">Current</span>
                                    @endif
                                </td>
                                <td class="text-muted" style="font-size:13px;">
                                    {{ $year->start_date?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="text-muted" style="font-size:13px;">
                                    {{ $year->end_date?->format('d M Y') ?? '—' }}
                                </td>
                                <td>
                                    <a href="{{ route('admin.course-intakes.index', ['year_id' => $year->id]) }}"
                                        class="btn btn-sm btn-outline-secondary" style="font-size:12px;">
                                        {{ $year->intakes()->count() }} intakes
                                    </a>
                                </td>
                                <td>
                                    <form action="{{ route('admin.academic-years.toggle-active', $year->id) }}"
                                        method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="badge border-0 {{ $year->is_active ? 'bg-success' : 'bg-secondary' }}"
                                            style="cursor:pointer;" title="Click to toggle">
                                            {{ $year->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.academic-years.edit', $year->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <span class="material-icons" style="font-size:15px;">edit</span>
                                        </a>
                                        <form action="{{ route('admin.academic-years.destroy', $year->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Delete {{ $year->name }}? This cannot be undone.')">
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

            @if ($years->hasPages())
                <div class="mt-3 px-2">{{ $years->links() }}</div>
            @endif
        @endif
    </div>
@endsection
