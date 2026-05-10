@extends('super-admin.layout')
@section('title', 'Tenants')
@section('page-title', 'Tenants')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="fw-700 mb-0" style="color:#0f172a">All Tenants</h5>
        <small class="text-muted">{{ $tenants->count() }} client(s) registered</small>
    </div>
    <a href="{{ route('superadmin.tenants.create') }}" class="btn btn-primary btn-sm px-3">
        + New Tenant
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead style="background:#f8fafc; font-size:.8rem; text-transform:uppercase; color:#64748b; letter-spacing:.05em">
                <tr>
                    <th class="py-3 ps-4">Name</th>
                    <th>Subdomain</th>
                    <th>Database</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenants as $tenant)
                <tr>
                    <td class="ps-4 fw-600" style="color:#0f172a">{{ $tenant->name }}</td>
                    <td>
                        <a href="https://{{ $tenant->subdomain }}.{{ env('APP_DOMAIN','insighttechnology.in') }}" target="_blank" class="text-decoration-none text-primary">
                            {{ $tenant->subdomain }}.{{ env('APP_DOMAIN','insighttechnology.in') }}
                        </a>
                    </td>
                    <td><code class="small">{{ $tenant->db_name }}</code></td>
                    <td>{{ $tenant->plan ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $tenant->is_active ? 'badge-active' : 'badge-inactive' }} px-2 py-1 rounded-pill">
                            {{ $tenant->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="text-muted small">{{ $tenant->created_at->format('d M Y') }}</td>
                    <td class="pe-4">
                        <div class="d-flex gap-1">
                            <a href="{{ route('superadmin.tenants.edit', $tenant) }}" class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:2px 8px">Edit</a>

                            <form method="POST" action="{{ route('superadmin.tenants.migrate', $tenant) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 8px"
                                    onclick="return confirm('Run migrations for {{ $tenant->subdomain }}?')">Migrate</button>
                            </form>

                            <form method="POST" action="{{ route('superadmin.tenants.toggle', $tenant) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-xs {{ $tenant->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" style="font-size:.75rem;padding:2px 8px">
                                    {{ $tenant->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">No tenants yet. <a href="{{ route('superadmin.tenants.create') }}">Create the first one.</a></td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
