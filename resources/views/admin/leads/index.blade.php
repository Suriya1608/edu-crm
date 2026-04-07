@extends('layouts.app')

@section('page_title', 'Lead Management')

@section('header_actions')
    <a href="{{ route('admin.leads.import.form') }}" class="btn btn-sm btn-outline-secondary">Import Leads</a>
    <a href="{{ route('admin.leads.export') }}" class="btn btn-sm btn-outline-success">Export Excel</a>
    <a href="{{ route('admin.leads.export', ['format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger" target="_blank">Export PDF</a>
@endsection

@section('content')
    <div class="chart-card mb-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.leads.all') }}" class="btn btn-sm {{ $scope === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All Leads</a>
            <a href="{{ route('admin.leads.unassigned') }}" class="btn btn-sm {{ $scope === 'unassigned' ? 'btn-primary' : 'btn-outline-primary' }}">Unassigned Leads</a>
            <a href="{{ route('admin.leads.assigned') }}" class="btn btn-sm {{ $scope === 'assigned' ? 'btn-primary' : 'btn-outline-primary' }}">Assigned Leads</a>
            <a href="{{ route('admin.leads.converted') }}" class="btn btn-sm {{ $scope === 'converted' ? 'btn-success' : 'btn-outline-success' }}">Converted Leads</a>
            <a href="{{ route('admin.leads.lost') }}" class="btn btn-sm {{ $scope === 'lost' ? 'btn-danger' : 'btn-outline-danger' }}">Lost Leads</a>
            <a href="{{ route('admin.leads.duplicates') }}" class="btn btn-sm {{ $scope === 'duplicates' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' }}">Duplicate Leads</a>
        </div>
    </div>

    <div class="chart-card mb-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                    placeholder="Lead code, name, phone, email">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary w-100">Apply</button>
                <a href="{{ route('admin.leads.' . $scope) }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="chart-card mb-3">
        <div class="chart-header mb-2">
            <h3>Bulk Assign</h3>
            <p>Assign selected leads to manager and/or telecaller</p>
        </div>

        <form method="POST" action="{{ route('admin.leads.bulk-assign') }}" id="bulkAssignForm" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label">Manager</label>
                <select class="form-select" name="manager_id">
                    <option value="">Keep unchanged</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Telecaller</label>
                <select class="form-select" name="telecaller_id">
                    <option value="">Keep unchanged</option>
                    @foreach ($telecallers as $telecaller)
                        <option value="{{ $telecaller->id }}">{{ $telecaller->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Bulk Assign</button>
            </div>
        </form>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>{{ $title }}</h3>
            <span class="text-muted" style="font-size:12px;">{{ $leads->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllLeads"></th>
                        <th>S.No</th>
                        <th>Lead</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Manager</th>
                        <th>Telecaller</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $index => $lead)
                        <tr>
                            <td>
                                <input type="checkbox" class="lead-checkbox" value="{{ $lead->id }}">
                            </td>
                            <td>{{ ($leads->currentPage() - 1) * $leads->perPage() + $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                                    {{ $lead->name }}
                                    @if($scope === 'duplicates')
                                        {{-- On duplicates page every row is a duplicate — show reason --}}
                                        @php
                                            $isPhoneDup = $duplicatePhones->contains($lead->phone);
                                            $isEmailDup = $duplicateEmails->contains($lead->email);
                                        @endphp
                                        @if($isPhoneDup && $isEmailDup)
                                            <span class="badge" style="background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;font-size:10px;font-weight:600;padding:2px 6px;border-radius:5px;">DUP PHONE+EMAIL</span>
                                        @elseif($isPhoneDup)
                                            <span class="badge" style="background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;font-size:10px;font-weight:600;padding:2px 6px;border-radius:5px;">DUP PHONE</span>
                                        @elseif($isEmailDup)
                                            <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:10px;font-weight:600;padding:2px 6px;border-radius:5px;">DUP EMAIL</span>
                                        @endif
                                    @elseif($lead->is_duplicate)
                                        <span class="badge" style="background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;font-size:10px;font-weight:600;padding:2px 6px;border-radius:5px;">DUPLICATE</span>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-wrap mt-1">
                                    <small class="text-muted">{{ $lead->lead_code }}</small>
                                    <x-aging-badge :days="$lead->days_aged" />
                                </div>
                            </td>
                            <td>{{ $lead->phone }}</td>
                            <td>
                                @php $stCls = str_replace('_', '-', $lead->status); @endphp
                                <span class="lead-status status-{{ $stCls }}">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span>
                            </td>
                            <td>{{ $lead->assignedBy->name ?? '-' }}</td>
                            <td>{{ $lead->assignedUser->name ?? '-' }}</td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="{{ route('admin.leads.show', encrypt($lead->id)) }}" class="btn btn-sm btn-outline-primary">View</a>

                                    <button class="btn btn-sm btn-outline-secondary assign-manager-btn"
                                        data-id="{{ encrypt($lead->id) }}"
                                        data-manager-id="{{ $lead->assigned_by ?? '' }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#assignManagerModal">Manager</button>

                                    <button class="btn btn-sm btn-outline-dark assign-telecaller-btn"
                                        data-id="{{ encrypt($lead->id) }}"
                                        data-telecaller-id="{{ $lead->assigned_to ?? '' }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#assignTelecallerModal">Telecaller</button>

                                    @if($scope === 'duplicates')
                                        <button class="btn btn-sm btn-outline-warning merge-btn"
                                            data-source="{{ $lead->id }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#mergeModal">Merge</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No leads found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Showing {{ $leads->firstItem() ?? 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} results
            </small>
            {{ $leads->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <div class="modal fade" id="assignManagerModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="assignManagerForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Lead to Manager</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <select class="form-select" name="manager_id" required>
                            <option value="">Select Manager</option>
                            @foreach ($managers as $manager)
                                <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="assignTelecallerModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="assignTelecallerForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reassign Telecaller</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <select class="form-select" name="telecaller_id" required>
                            <option value="">Select Telecaller</option>
                            @foreach ($telecallers as $telecaller)
                                <option value="{{ $telecaller->id }}">{{ $telecaller->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Merge Modal (duplicates scope) --}}
    <div class="modal fade" id="mergeModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="mergeForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Merge Duplicate Lead</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">Select the <strong>target</strong> lead to keep. All activities, call logs, and follow-ups from the source lead will be moved to the target.</p>
                        <label class="form-label">Target Lead ID (to merge INTO)</label>
                        <input type="number" class="form-control" id="mergeTargetId" placeholder="Enter target Lead ID" required min="1">
                        <div class="form-text text-danger mt-1">The source lead will be marked as "merged" and hidden.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Confirm Merge</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const bulkAssignForm = document.getElementById('bulkAssignForm');
            const selectAll = document.getElementById('selectAllLeads');
            const checkboxes = () => Array.from(document.querySelectorAll('.lead-checkbox'));

            selectAll?.addEventListener('change', function() {
                checkboxes().forEach(cb => cb.checked = this.checked);
            });

            bulkAssignForm?.addEventListener('submit', function(e) {
                checkboxes().filter(cb => cb.checked).forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'lead_ids[]';
                    input.value = cb.value;
                    this.appendChild(input);
                });
            });

            document.querySelectorAll('.assign-manager-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('assignManagerForm').action =
                        "{{ url('admin/leads') }}/" + this.dataset.id + "/assign-manager";

                    const currentManagerId = this.dataset.managerId;
                    const select = document.querySelector('#assignManagerModal select[name="manager_id"]');

                    // Reset all options first
                    Array.from(select.options).forEach(opt => {
                        opt.disabled = false;
                        opt.selected = false;
                    });

                    if (currentManagerId) {
                        const currentOpt = select.querySelector('option[value="' + currentManagerId + '"]');
                        if (currentOpt) {
                            currentOpt.selected = true;
                            currentOpt.disabled = true;
                            currentOpt.textContent = currentOpt.textContent.replace(' (current)', '') + ' (current)';
                        }
                    } else {
                        select.value = '';
                    }
                });
            });

            // Clean up label text when modal closes
            document.getElementById('assignManagerModal').addEventListener('hidden.bs.modal', function() {
                const select = this.querySelector('select[name="manager_id"]');
                Array.from(select.options).forEach(opt => {
                    opt.disabled = false;
                    opt.textContent = opt.textContent.replace(' (current)', '');
                });
            });

            document.querySelectorAll('.assign-telecaller-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('assignTelecallerForm').action =
                        "{{ url('admin/leads') }}/" + this.dataset.id + "/reassign-telecaller";

                    const currentTelecallerId = this.dataset.telecallerId;
                    const select = document.querySelector('#assignTelecallerModal select[name="telecaller_id"]');

                    // Reset all options first
                    Array.from(select.options).forEach(opt => {
                        opt.disabled = false;
                        opt.selected = false;
                        opt.textContent = opt.textContent.replace(' (current)', '');
                    });

                    if (currentTelecallerId) {
                        const currentOpt = select.querySelector('option[value="' + currentTelecallerId + '"]');
                        if (currentOpt) {
                            currentOpt.selected = true;
                            currentOpt.disabled = true;
                            currentOpt.textContent = currentOpt.textContent + ' (current)';
                        }
                    } else {
                        select.value = '';
                    }
                });
            });

            // Clean up label text when modal closes
            document.getElementById('assignTelecallerModal').addEventListener('hidden.bs.modal', function() {
                const select = this.querySelector('select[name="telecaller_id"]');
                Array.from(select.options).forEach(opt => {
                    opt.disabled = false;
                    opt.textContent = opt.textContent.replace(' (current)', '');
                });
            });

            document.querySelectorAll('.merge-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sourceId = this.dataset.source;
                    document.getElementById('mergeTargetId').value = '';
                    const form = document.getElementById('mergeForm');
                    form.onsubmit = function(e) {
                        e.preventDefault();
                        const targetId = document.getElementById('mergeTargetId').value;
                        if (!targetId) return;
                        form.action = "{{ url('admin/leads') }}/" + sourceId + "/merge/" + targetId;
                        form.submit();
                    };
                });
            });
        })();
    </script>
@endsection

