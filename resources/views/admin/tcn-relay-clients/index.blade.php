@extends('layouts.app')

@section('page_title', 'TCN Relay Clients')

@section('content')

@include('admin.settings.partials.nav')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="fw-bold mb-1">
            <span class="material-icons me-2" style="font-size:20px;vertical-align:middle;color:#6366f1;">hub</span>
            TCN OAuth Relay — Client Whitelist
        </h5>
        <p class="text-muted small mb-0">
            Only domains registered here are allowed to use
            <code>{{ route('tcn.auth.relay') }}</code> as their OAuth redirect URL.
        </p>
    </div>
    <button class="btn btn-primary d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addClientModal">
        <span class="material-icons" style="font-size:18px;">add</span>
        Add Client
    </button>
</div>

{{-- Alerts --}}
@if(session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
        <span class="material-icons" style="font-size:18px;">check_circle</span>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">
        <span class="material-icons" style="font-size:18px;">error</span>
        {{ session('error') }}
    </div>
@endif

{{-- Relay URL info card --}}
<div class="card p-3 mb-4" style="border-left:4px solid #6366f1;">
    <div class="d-flex align-items-start gap-3">
        <span class="material-icons mt-1" style="font-size:20px;color:#6366f1;">info</span>
        <div>
            <p class="fw-semibold mb-1 small">Single Relay URL to give TCN</p>
            <div class="d-flex align-items-center gap-2">
                <code id="relayUrlText" class="bg-light px-3 py-1 rounded border small">{{ route('tcn.auth.relay') }}</code>
                <button class="btn btn-sm btn-outline-secondary" onclick="copyRelayUrl()" title="Copy">
                    <span class="material-icons" style="font-size:16px;">content_copy</span>
                </button>
            </div>
            <p class="text-muted small mt-2 mb-0">
                Register this URL once in TCN's OAuth application settings.
                Each client installation must set <code>TCN_RELAY_URL</code> to this URL in their <code>.env</code>.
            </p>
        </div>
    </div>
</div>

{{-- Clients table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Client Name</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Last Relay</th>
                    <th>Notes</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                <tr id="row-{{ $client->id }}">
                    <td class="ps-4 fw-semibold">{{ $client->name }}</td>
                    <td>
                        <code class="small">{{ $client->domain }}</code>
                    </td>
                    <td>
                        <button type="button"
                            class="btn btn-sm px-3 toggle-btn {{ $client->is_active ? 'btn-success' : 'btn-secondary' }}"
                            data-id="{{ $client->id }}"
                            data-url="{{ route('admin.tcn-relay-clients.toggle', $client) }}">
                            {{ $client->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </td>
                    <td class="text-muted small">
                        {{ $client->last_relayed_at ? $client->last_relayed_at->diffForHumans() : 'Never' }}
                    </td>
                    <td class="text-muted small" style="max-width:200px;">
                        {{ $client->notes ? Str::limit($client->notes, 60) : '—' }}
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-secondary me-1"
                            data-bs-toggle="modal"
                            data-bs-target="#editClientModal"
                            data-id="{{ $client->id }}"
                            data-name="{{ $client->name }}"
                            data-domain="{{ $client->domain }}"
                            data-notes="{{ $client->notes }}"
                            title="Edit">
                            <span class="material-icons" style="font-size:16px;">edit</span>
                        </button>
                        <form method="POST" action="{{ route('admin.tcn-relay-clients.destroy', $client) }}"
                              class="d-inline" onsubmit="return confirm('Remove {{ addslashes($client->name) }} from whitelist?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Remove">
                                <span class="material-icons" style="font-size:16px;">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <span class="material-icons d-block mb-2" style="font-size:40px;opacity:.3;">hub</span>
                        No relay clients registered yet.<br>
                        <small>Add the first client to allow them to use the central relay.</small>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Client Modal --}}
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.tcn-relay-clients.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">
                        <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">add_circle</span>
                        Add Relay Client
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Client / Institution Name *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required placeholder="e.g. ABC Institute">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Client Domain (base URL) *</label>
                        <input type="url" name="domain" class="form-control @error('domain') is-invalid @enderror"
                               value="{{ old('domain') }}" required placeholder="https://client.example.com">
                        @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Must be an exact match — no trailing slash.</small>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Contact person, purpose, etc.">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-icons me-1" style="font-size:16px;">save</span>
                        Add Client
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Edit Client Modal --}}
<div class="modal fade" id="editClientModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editClientForm">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">
                        <span class="material-icons me-1" style="font-size:18px;vertical-align:middle;">edit</span>
                        Edit Client
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Client / Institution Name *</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Client Domain (base URL) *</label>
                        <input type="url" name="domain" id="editDomain" class="form-control" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-icons me-1" style="font-size:16px;">save</span>
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// Copy relay URL
function copyRelayUrl() {
    const text = document.getElementById('relayUrlText').textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('[onclick="copyRelayUrl()"]');
        btn.innerHTML = '<span class="material-icons" style="font-size:16px;">check</span>';
        setTimeout(() => {
            btn.innerHTML = '<span class="material-icons" style="font-size:16px;">content_copy</span>';
        }, 1800);
    });
}

// Toggle active/inactive via AJAX
document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const id  = this.dataset.id;
        const url = this.dataset.url;
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            this.textContent = data.label;
            this.classList.toggle('btn-success',   data.is_active);
            this.classList.toggle('btn-secondary', !data.is_active);
        });
    });
});

// Populate edit modal
document.getElementById('editClientModal').addEventListener('show.bs.modal', function (e) {
    const btn  = e.relatedTarget;
    const id   = btn.dataset.id;
    const form = document.getElementById('editClientForm');
    form.action = `/admin/tcn-relay-clients/${id}`;
    document.getElementById('editName').value   = btn.dataset.name;
    document.getElementById('editDomain').value = btn.dataset.domain;
    document.getElementById('editNotes').value  = btn.dataset.notes || '';
});
</script>
@endpush

@endsection
