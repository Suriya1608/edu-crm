@extends('layouts.app')

@section('page_title', 'Email Templates')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-header-title mb-0">Email Templates</h2>
            <p class="page-header-subtitle mb-0">Manage reusable HTML email templates for campaigns</p>
        </div>
        <a href="{{ route('admin.email-templates.create') }}" class="btn btn-primary btn-sm">
            <span class="material-icons me-1" style="font-size:16px;">add</span>New Template
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card">
        @if ($templates->isEmpty())
            <div class="text-center py-5 text-muted">
                <span class="material-icons" style="font-size:48px;opacity:.3;">email</span>
                <p class="mt-2">No email templates yet. <a href="{{ route('admin.email-templates.create') }}">Create one</a>.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $template)
                            <tr>
                                <td class="fw-semibold">{{ $template->name }}</td>
                                <td class="text-muted">{{ $template->subject }}</td>
                                <td>
                                    <span class="badge {{ $template->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ ucfirst($template->status) }}
                                    </span>
                                </td>
                                <td>{{ $template->creator?->name ?? '—' }}</td>
                                <td class="text-muted" style="font-size:13px;">{{ $template->created_at->format('d M Y') }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        {{-- Toggle status --}}
                                        <form action="{{ route('admin.email-templates.toggle-status', $template) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $template->status === 'active' ? 'btn-outline-success' : 'btn-outline-warning' }}"
                                                title="{{ $template->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                                <span class="material-icons" style="font-size:15px;">
                                                    {{ $template->status === 'active' ? 'toggle_on' : 'toggle_off' }}
                                                </span>
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.email-templates.edit', $template) }}"
                                            class="btn btn-sm btn-outline-primary" title="Edit">
                                            <span class="material-icons" style="font-size:15px;">edit</span>
                                        </a>
                                        <form action="{{ route('admin.email-templates.destroy', $template) }}" method="POST"
                                            onsubmit="return confirm('Delete this template?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
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

            @if ($templates->hasPages())
                <div class="mt-3 px-2">
                    {{ $templates->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
