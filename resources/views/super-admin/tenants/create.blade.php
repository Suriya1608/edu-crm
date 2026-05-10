@extends('super-admin.layout')
@section('title', 'New Tenant')
@section('page-title', 'New Tenant')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="d-flex align-items-center mb-4 gap-2">
            <a href="{{ route('superadmin.tenants.index') }}" class="text-muted text-decoration-none">&larr; Back</a>
            <h5 class="fw-700 mb-0 ms-2" style="color:#0f172a">Create New Tenant</h5>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('superadmin.tenants.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-600">Client Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Sunrise Engineering College" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-600">Subdomain <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="subdomain" class="form-control" value="{{ old('subdomain') }}"
                                placeholder="client1" pattern="[a-z0-9][a-z0-9\-]*[a-z0-9]|[a-z0-9]" required>
                            <span class="input-group-text text-muted">.{{ env('APP_DOMAIN','insighttechnology.in') }}</span>
                        </div>
                        <div class="form-text">Lowercase letters, numbers and hyphens only.</div>
                    </div>

                    <hr class="my-4">
                    <p class="text-muted small mb-3">Optional: create an initial admin user in the new tenant DB.</p>

                    <div class="mb-3">
                        <label class="form-label fw-600">Admin Email</label>
                        <input type="email" name="admin_email" class="form-control" value="{{ old('admin_email') }}" placeholder="admin@college.edu">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-600">Admin Password</label>
                        <input type="text" name="admin_password" class="form-control" placeholder="min. 8 characters">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">Create Tenant</button>
                        <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="alert alert-info mt-3 small">
            <strong>What happens next:</strong> A new MySQL database is created, all migrations are run, and the tenant is registered. This may take a few seconds.
        </div>
    </div>
</div>
@endsection
