@extends('layouts.app')

@section('page_title', 'Edit User')

@section('content')

<div class="card p-4">
    <form method="POST" action="{{ route('admin.users.update', $id) }}">
        @csrf

        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label fw-semibold">Employee ID</label>
                <input type="text" class="form-control bg-light"
                       value="{{ $user->employee_id ?: '—' }}" readonly>
            </div>

            <div class="col-md-6"></div>

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

        {{-- ── TCN Account Configuration ─────────────────────────── --}}
        <hr class="my-4">
        <h6 class="fw-bold mb-1">
            <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">settings_phone</span>
            TCN Account
        </h6>
        <p class="text-muted small mb-3">
            Connect this agent's individual TCN account via OAuth.
            Agent ID and Hunt Group are fetched automatically on connect.
        </p>

        {{-- OAuth status banner --}}
        @if($tcnAccount && $tcnAccount->refresh_token)
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
                <span class="material-icons" style="font-size:18px;">check_circle</span>
                <span>
                    TCN connected &mdash;
                    Agent ID: <strong>{{ $tcnAccount->agent_id ?? '—' }}</strong>,
                    Hunt Group: <strong>{{ $tcnAccount->hunt_group_id ?? '—' }}</strong>
                    @if($tcnAccount->tcn_username)
                        &nbsp;({{ $tcnAccount->tcn_username }})
                    @endif
                </span>
            </div>
        @else
            <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
                <span class="material-icons" style="font-size:18px;">warning</span>
                <span>No TCN account connected yet.</span>
            </div>
        @endif

        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label fw-semibold">TCN Username <span class="text-muted fw-normal">(optional override)</span></label>
                <input type="text" name="tcn_username" class="form-control"
                       value="{{ old('tcn_username', $tcnAccount->tcn_username ?? '') }}"
                       placeholder="agent@company.com">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Agent ID <span class="text-muted fw-normal">(auto-filled on connect)</span></label>
                <input type="text" name="tcn_agent_id" class="form-control"
                       value="{{ old('tcn_agent_id', $tcnAccount->agent_id ?? '') }}"
                       placeholder="Auto-populated via OAuth">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Hunt Group ID <span class="text-muted fw-normal">(auto-filled on connect)</span></label>
                <input type="text" name="tcn_hunt_group_id" class="form-control"
                       value="{{ old('tcn_hunt_group_id', $tcnAccount->hunt_group_id ?? '') }}"
                       placeholder="Auto-populated via OAuth">
            </div>

            <div class="col-md-6 d-flex align-items-end">
                <a href="{{ route('tcn.user.connect', encrypt($user->id)) }}"
                   class="btn {{ ($tcnAccount && $tcnAccount->refresh_token) ? 'btn-outline-success' : 'btn-primary' }} w-100">
                    <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">
                        {{ ($tcnAccount && $tcnAccount->refresh_token) ? 'sync' : 'link' }}
                    </span>
                    {{ ($tcnAccount && $tcnAccount->refresh_token) ? 'Reconnect TCN Account' : 'Connect TCN Account' }}
                </a>
            </div>

        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons me-1" style="font-size:18px;">save</span>
                Update User
            </button>

            <a href="{{ route('admin.users') }}" class="btn btn-secondary">
                Cancel
            </a>
        </div>

    </form>
</div>


@endsection
