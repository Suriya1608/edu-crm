@extends('layouts.app')

@section('page_title', 'Edit User')

@section('content')

<div class="card p-4">
    <form method="POST" action="{{ route('admin.users.update', $id) }}">
        @csrf

        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label fw-semibold">Name *</label>
                <input type="text" name="name" class="form-control"
                       value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Email *</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="{{ old('phone', $user->phone) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Role *</label>
                <select name="role" class="form-select" required>
                    <option value="manager"
                        {{ $user->role == 'manager' ? 'selected' : '' }}>
                        Manager
                    </option>
                    <option value="telecaller"
                        {{ $user->role == 'telecaller' ? 'selected' : '' }}>
                        Telecaller
                    </option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Status *</label>
                <select name="status" class="form-select" required>
                    <option value="1"
                        {{ $user->status ? 'selected' : '' }}>
                        Active
                    </option>
                    <option value="0"
                        {{ !$user->status ? 'selected' : '' }}>
                        Inactive
                    </option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Password (Leave blank to keep same)
                </label>
                <input type="password" name="password"
                       class="form-control">
            </div>

        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons me-1"
                      style="font-size:18px;">save</span>
                Update User
            </button>

            <a href="{{ route('admin.users') }}"
               class="btn btn-secondary">
                Cancel
            </a>
        </div>

    </form>
</div>

@endsection
