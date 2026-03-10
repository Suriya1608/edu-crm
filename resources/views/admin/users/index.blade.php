@extends('layouts.app')

@section('page_title', 'User Management')

@section('header_actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary d-flex align-items-center gap-1">
        <span class="material-icons" style="font-size:16px;">add</span>
        Add User
    </a>
@endsection

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">admin_panel_settings</span></div>
                <div class="stat-label">Admin Users</div>
                <div class="stat-value">{{ $counts['admins'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">manage_accounts</span></div>
                <div class="stat-label">Managers</div>
                <div class="stat-value">{{ $counts['managers'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">support_agent</span></div>
                <div class="stat-label">Telecallers</div>
                <div class="stat-value">{{ $counts['telecallers'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card highlight-success">
                <div class="stat-icon red"><span class="material-icons">check_circle</span></div>
                <div class="stat-label">Active Accounts</div>
                <div class="stat-value">{{ $counts['active'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    <div class="chart-card mb-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.users.admins') }}"
                class="btn btn-sm {{ $scope === 'admin' ? 'btn-primary' : 'btn-outline-primary' }}">
                Admin Users
            </a>
            <a href="{{ route('admin.users.managers') }}"
                class="btn btn-sm {{ $scope === 'manager' ? 'btn-primary' : 'btn-outline-primary' }}">
                Managers
            </a>
            <a href="{{ route('admin.users.telecallers') }}"
                class="btn btn-sm {{ $scope === 'telecaller' ? 'btn-primary' : 'btn-outline-primary' }}">
                Telecallers
            </a>
        </div>
    </div>

    <div class="chart-card mb-3">
        <form method="GET" action="{{ url()->current() }}">
            <div class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                        placeholder="Search name, email, phone">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary w-100">Apply</button>
                </div>
            </div>
        </form>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>{{ $title ?? 'Users' }}</h3>
            <span class="text-muted" style="font-size:12px;">{{ $users->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Account</th>
                        <th>Online</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $index => $user)
                        <tr>
                            <td>{{ ($users->currentPage() - 1) * $users->perPage() + $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $user->name }}</div>
                                <small class="text-muted">{{ $user->email }} | {{ $user->phone ?: '-' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">{{ ucfirst($user->role) }}</span>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <button class="btn btn-sm toggle-status-btn {{ $user->status ? 'btn-success' : 'btn-danger' }}"
                                        data-id="{{ $user->id }}">
                                        {{ $user->status ? 'Active' : 'Inactive' }}
                                    </button>
                                    @if ($user->locked_until && \Carbon\Carbon::parse($user->locked_until)->isFuture())
                                        <span class="badge bg-danger" style="font-size:10px;">
                                            <span class="material-icons" style="font-size:11px;vertical-align:middle;">lock</span>
                                            Locked
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $user->presence_state === 'online' ? 'bg-success' : 'bg-secondary' }}"
                                      data-user-id="{{ $user->id }}">
                                    {{ ucfirst($user->presence_state) }}
                                </span>
                            </td>
                            <td>{{ optional($user->created_at)->format('d M Y') }}</td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="{{ route('admin.users.edit', encrypt($user->id)) }}"
                                        class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                        <span class="material-icons" style="font-size:16px;">edit</span>
                                        <span>Edit</span>
                                    </a>
                                    <button class="btn btn-sm btn-outline-warning force-logout-btn d-flex align-items-center gap-1"
                                        data-id="{{ $user->id }}">
                                        <span class="material-icons" style="font-size:16px;">logout</span>
                                        <span>Force Logout</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-dark reset-password-btn d-flex align-items-center gap-1" data-id="{{ $user->id }}"
                                        data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                        <span class="material-icons" style="font-size:16px;">lock_reset</span>
                                        <span>Reset Password</span>
                                    </button>
                                    @if ($user->locked_until && \Carbon\Carbon::parse($user->locked_until)->isFuture())
                                        <button class="btn btn-sm btn-outline-success unlock-account-btn d-flex align-items-center gap-1"
                                            data-id="{{ $user->id }}">
                                            <span class="material-icons" style="font-size:16px;">lock_open</span>
                                            <span>Unlock</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} results
            </small>
            {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="resetPasswordForm">
                @csrf
                <input type="hidden" id="resetPasswordUserId">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reset Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">New Password</label>
                        <input type="password" id="newPasswordInput" class="form-control" required minlength="6">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const csrfToken = '{{ csrf_token() }}';

            document.querySelectorAll('.toggle-status-btn').forEach((button) => {
                button.addEventListener('click', async function() {
                    const userId = this.dataset.id;
                    const res = await fetch("{{ route('admin.users.toggle') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            id: userId
                        })
                    });
                    const data = await res.json();
                    if (data.status) {
                        this.classList.remove('btn-danger');
                        this.classList.add('btn-success');
                        this.textContent = 'Active';
                    } else {
                        this.classList.remove('btn-success');
                        this.classList.add('btn-danger');
                        this.textContent = 'Inactive';
                    }
                });
            });

            document.querySelectorAll('.force-logout-btn').forEach((button) => {
                button.addEventListener('click', async function() {
                    if (!confirm('Force logout this user now?')) return;
                    await fetch("{{ route('admin.users.force-logout') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            id: this.dataset.id
                        })
                    });
                    alert('User forced offline/logout request completed.');
                    window.location.reload();
                });
            });

            document.querySelectorAll('.reset-password-btn').forEach((button) => {
                button.addEventListener('click', function() {
                    document.getElementById('resetPasswordUserId').value = this.dataset.id;
                    document.getElementById('newPasswordInput').value = '';
                });
            });

            document.querySelectorAll('.unlock-account-btn').forEach((button) => {
                button.addEventListener('click', async function() {
                    if (!confirm('Unlock this account and reset failed login attempts?')) return;
                    const res = await fetch("{{ route('admin.users.unlock') }}", {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ id: this.dataset.id })
                    });
                    if (res.ok) {
                        alert('Account unlocked successfully.');
                        window.location.reload();
                    } else {
                        alert('Failed to unlock account.');
                    }
                });
            });

            document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const id = document.getElementById('resetPasswordUserId').value;
                const password = document.getElementById('newPasswordInput').value;

                const res = await fetch("{{ route('admin.users.reset-password') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        id,
                        password
                    })
                });

                if (!res.ok) {
                    alert('Failed to reset password.');
                    return;
                }

                alert('Password reset successful.');
                const modal = bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal'));
                modal?.hide();
            });
        })();
    </script>

    <script>
        // Live presence polling — refresh online/offline badges every 20 seconds
        (function () {
            const presenceUrl = @json(route('admin.users.presence-snapshot'));

            async function refreshPresence() {
                try {
                    const res = await fetch(presenceUrl, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    const presence = data.presence || {};

                    document.querySelectorAll('[data-user-id]').forEach(function (badge) {
                        const uid = badge.dataset.userId;
                        if (uid in presence) {
                            const isOnline = presence[uid] === 'online';
                            badge.className = 'badge ' + (isOnline ? 'bg-success' : 'bg-secondary');
                            badge.textContent = isOnline ? 'Online' : 'Offline';
                        }
                    });
                } catch (_) {}
            }

            refreshPresence();
            setInterval(refreshPresence, 20000);
        })();
    </script>
@endsection
