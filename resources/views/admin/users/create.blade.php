@extends('layouts.app')

@section('page_title', 'Add User')

@section('content')

<div class="card p-4">
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf

        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label fw-semibold">Employee ID</label>
                <input type="text" class="form-control bg-light" value="{{ $previewId }}" readonly>
                <small class="text-muted">Auto-generated on save</small>
            </div>

            <div class="col-md-6"></div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Name *</label>
                <input type="text" name="name" class="form-control"
                       value="{{ old('name') }}" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Email *</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email') }}" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Phone *</label>
                <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           placeholder="10-digit number" maxlength="10"
                           value="{{ old('phone') }}" required>
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Role *</label>
                <select name="role" class="form-select" required>
                    <option value="">Select Role</option>
                    <option value="manager">Manager</option>
                    <option value="telecaller">Telecaller</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Password *</label>
                <input type="password" name="password"
                       class="form-control" required>
            </div>

        </div>

        {{-- ── TCN Account Configuration ─────────────────────────── --}}
        <hr class="my-4">
        <h6 class="fw-bold mb-1">
            <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">settings_phone</span>
            TCN Account
            <span class="badge bg-secondary ms-1" style="font-size:10px;font-weight:500;">Optional</span>
        </h6>
        <p class="text-muted small mb-3">
            You can assign TCN credentials now or configure them later by editing the user.
        </p>

        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label fw-semibold">TCN Username</label>
                <input type="text" name="tcn_username" class="form-control"
                       value="{{ old('tcn_username') }}" placeholder="agent@company.com">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Agent ID</label>
                <input type="text" name="tcn_agent_id" class="form-control"
                       value="{{ old('tcn_agent_id') }}" placeholder="12345">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Hunt Group ID</label>
                <input type="text" name="tcn_hunt_group_id" class="form-control"
                       value="{{ old('tcn_hunt_group_id') }}" placeholder="67890">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Refresh Token
                    <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">Encrypted</span>
                </label>
                <div class="input-group">
                    <input type="password" name="tcn_refresh_token" id="tcnTokenInput"
                           class="form-control" placeholder="Paste refresh token">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="toggleTcnToken()" title="Show / Hide">
                        <span class="material-icons" id="tcnTokenEye" style="font-size:18px;">visibility</span>
                    </button>
                </div>
            </div>

        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons me-1" style="font-size:18px;">save</span>
                Save User
            </button>

            <a href="{{ route('admin.users') }}" class="btn btn-secondary">
                Cancel
            </a>
        </div>

    </form>
</div>

@push('scripts')
<script>
function toggleTcnToken() {
    const input = document.getElementById('tcnTokenInput');
    const icon  = document.getElementById('tcnTokenEye');
    input.type  = (input.type === 'password') ? 'text' : 'password';
    icon.textContent = (input.type === 'text') ? 'visibility_off' : 'visibility';
}
</script>
@endpush

@endsection
