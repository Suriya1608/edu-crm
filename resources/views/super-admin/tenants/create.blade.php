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

                    {{-- Database section --}}
                    <hr class="my-3">
                    <p class="fw-600 mb-2" style="font-size:.85rem;color:#0f172a">Database</p>

                    <div class="mb-3">
                        <label class="form-label fw-600">Database Name <span class="text-danger">*</span></label>
                        <input type="text" name="db_name" id="dbName" class="form-control" value="{{ old('db_name') }}"
                            placeholder="e.g. u492210898_client1_db" required>
                        <div class="form-text">On shared hosting, create the database in your hosting panel first, then enter the exact name here.</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="existing_db" id="existingDb" value="1" {{ old('existing_db') ? 'checked' : '' }}>
                            <label class="form-check-label fw-600" for="existingDb">
                                Database already exists
                                <span class="text-muted fw-400" style="font-size:.8rem">(skip CREATE DATABASE step)</span>
                            </label>
                        </div>
                        <div class="form-text ms-4">Check this on shared hosting where you cannot create databases programmatically.</div>
                    </div>

                    {{-- Admin user --}}
                    <hr class="my-3">
                    <p class="fw-600 mb-2" style="font-size:.85rem;color:#0f172a">Initial Admin User <span class="text-muted fw-400">(optional)</span></p>

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

        <div class="alert alert-warning mt-3 small">
            <strong>Shared hosting (Hostinger)?</strong><br>
            Create the database in <strong>hPanel → Databases → MySQL Databases</strong> first,
            then enter the exact database name above and check <em>"Database already exists"</em>.
        </div>
    </div>
</div>
@endsection
