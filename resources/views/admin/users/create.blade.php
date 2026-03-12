@extends('layouts.app')

@section('page_title', 'Add User')

@section('content')

<div class="card p-4">
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf

        <div class="row g-3">

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

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons me-1"
                      style="font-size:18px;">save</span>
                Save User
            </button>

            <a href="{{ route('admin.users') }}"
               class="btn btn-secondary">
                Cancel
            </a>
        </div>

    </form>
</div>

@endsection
