@extends('super-admin.layout')
@section('title', 'Add Client')
@section('page-title', 'Add Client')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="d-flex align-items-center mb-4 gap-2">
            <a href="{{ route('superadmin.tenants.index') }}" class="text-muted text-decoration-none">&larr; Back</a>
            <h5 class="fw-700 mb-0 ms-2" style="color:#0f172a">Add New Client</h5>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if($errors->has('general'))
                    <div class="alert alert-danger py-2 small">{{ $errors->first('general') }}</div>
                @elseif($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('superadmin.tenants.store') }}" id="createForm">
                    @csrf

                    {{-- Client Info --}}
                    <div class="mb-3">
                        <label class="form-label fw-600">Client Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                            placeholder="e.g. Sunrise Engineering College" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-600">Subdomain <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="subdomain" id="subdomain" class="form-control"
                                value="{{ old('subdomain') }}" placeholder="client1"
                                pattern="[a-z0-9][a-z0-9\-]*[a-z0-9]|[a-z0-9]" required>
                            <span class="input-group-text text-muted">.{{ env('APP_DOMAIN','insighttechnology.in') }}</span>
                        </div>
                        <div class="form-text">Lowercase letters, numbers and hyphens only.</div>
                    </div>

                    {{-- Database --}}
                    <hr class="my-3">
                    <p class="fw-600 mb-2" style="font-size:.85rem;color:#0f172a">Database</p>

                    @php $dbPrefix = env('TENANT_DB_PREFIX', ''); @endphp

                    @if($dbPrefix)
                    <div class="alert alert-warning border-0 py-2 small mb-3" style="background:#fffbeb">
                        <strong>Shared hosting detected.</strong> You must create this database manually in cPanel/hPanel first, then enter the exact name below. Also assign your DB user to it with all privileges.
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-600">Database Name <span class="text-muted fw-400">(auto-generated if blank)</span></label>
                        <input type="text" name="db_name" id="dbName" class="form-control"
                            value="{{ old('db_name') }}" placeholder="{{ $dbPrefix }}crm_client1">
                        <div class="form-text">
                            @if($dbPrefix)
                                On Hostinger, DB names must start with <code>{{ $dbPrefix }}</code> — e.g. <code id="dbNameHint">{{ $dbPrefix }}crm_client1</code>. Create it in hPanel first.
                            @else
                                Leave blank to auto-generate as <code>crm_{subdomain}</code>.
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="existing_db" id="existingDb"
                                value="1" {{ (old('existing_db') || $dbPrefix) ? 'checked' : '' }}>
                            <label class="form-check-label fw-600" for="existingDb">
                                Database already created in cPanel (skip CREATE DATABASE)
                                <span class="text-muted fw-400" style="font-size:.8rem">— migrations will still run</span>
                            </label>
                        </div>
                        <div class="form-text ms-4">Always check this on shared hosting (Hostinger, cPanel) — databases must be pre-created there.</div>
                    </div>

                    {{-- Admin User --}}
                    <hr class="my-3">
                    <p class="fw-600 mb-2" style="font-size:.85rem;color:#0f172a">Initial Admin User <span class="text-muted fw-400">(optional)</span></p>

                    <div class="mb-3">
                        <label class="form-label fw-600">Admin Email</label>
                        <input type="email" name="admin_email" class="form-control"
                            value="{{ old('admin_email') }}" placeholder="admin@college.edu">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-600">Admin Password</label>
                        <input type="text" name="admin_password" class="form-control" placeholder="min. 8 characters">
                    </div>

                    {{-- What will be provisioned --}}
                    <div class="alert alert-light border mb-4 small" style="background:#f8fafc">
                        <strong style="color:#0f172a">On submit, the system will:</strong>
                        <ul class="mb-0 mt-1 ps-3" style="color:#475569">
                            <li>Create the MySQL database</li>
                            <li>Run all migrations on it</li>
                            <li>Create <code>storage/app/public/tenants/{subdomain}/</code> folder</li>
                            <li>Create the admin user (if email provided)</li>
                        </ul>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                            <span id="btnText">Create Client</span>
                            <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                        </button>
                        <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const DB_PREFIX = '{{ env('TENANT_DB_PREFIX', '') }}';
// Auto-fill DB name hint from subdomain
document.getElementById('subdomain').addEventListener('input', function () {
    const dbField = document.getElementById('dbName');
    const hintEl  = document.getElementById('dbNameHint');
    if (dbField.dataset.userEdited) return;
    const slug = this.value.replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
    const generated = slug ? DB_PREFIX + 'crm_' + slug : DB_PREFIX + 'crm_client1';
    dbField.placeholder = generated;
    if (hintEl) hintEl.textContent = generated;
});
document.getElementById('dbName').addEventListener('input', function () {
    this.dataset.userEdited = this.value ? '1' : '';
});

// Loading state on submit
document.getElementById('createForm').addEventListener('submit', function () {
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('btnText').textContent = 'Provisioning…';
    document.getElementById('btnSpinner').classList.remove('d-none');
});
</script>
@endsection
