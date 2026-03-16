@extends('layouts.app')

@section('page_title', 'Create Email Campaign')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.email-campaigns.index') }}" class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back
        </a>
        <div>
            <h2 class="page-header-title mb-0">Create Email Campaign</h2>
            <p class="page-header-subtitle mb-0">Select recipients, choose a template and send or schedule</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('admin.email-campaigns.store') }}" method="POST" id="ecForm">
        @csrf

        <div class="row g-4">
            {{-- Left: Campaign details + Template --}}
            <div class="col-lg-5">
                <div class="chart-card mb-4">
                    <h6 class="fw-semibold mb-3">Campaign Details</h6>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" placeholder="e.g. March Admission Drive">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2"
                            placeholder="Optional notes about this campaign">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Template <span class="text-danger">*</span></label>
                        <select name="template_id" id="templateSelect"
                            class="form-select @error('template_id') is-invalid @enderror"
                            onchange="previewTemplate(this)">
                            <option value="">— Choose a template —</option>
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}"
                                    data-subject="{{ $tpl->subject }}"
                                    data-body="{{ htmlspecialchars($tpl->body, ENT_QUOTES) }}"
                                    {{ old('template_id') == $tpl->id ? 'selected' : '' }}>
                                    {{ $tpl->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('template_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div id="templatePreview" style="display:none;" class="mb-3">
                        <div class="alert alert-light border p-2" style="font-size:12px;">
                            <strong>Subject:</strong> <span id="previewSubject"></span>
                        </div>
                        <div id="previewBody"
                            style="border:1px solid #e2e8f0;border-radius:6px;padding:10px;max-height:200px;overflow:auto;font-size:12px;">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Schedule</label>
                        <input type="datetime-local" name="scheduled_at" id="scheduledAt"
                            class="form-control @error('scheduled_at') is-invalid @enderror"
                            value="{{ old('scheduled_at') }}">
                        <div class="form-text">Leave blank to send immediately after saving.</div>
                        @error('scheduled_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Right: Recipient Selection --}}
            <div class="col-lg-7">
                <div class="chart-card">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h6 class="fw-semibold mb-0">Select Recipients</h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select id="sourceFilter" class="form-select form-select-sm" style="width:auto;"
                                onchange="loadEmails()">
                                <option value="all">All Sources</option>
                                <option value="leads">Leads</option>
                                <option value="campaign_contacts">Campaign Contacts</option>
                            </select>
                            <select id="courseFilter" class="form-select form-select-sm" style="width:auto;"
                                onchange="loadEmails()">
                                <option value="all">All Courses</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course }}">{{ $course }}</option>
                                @endforeach
                            </select>
                            <select id="campaignFilter" class="form-select form-select-sm" style="width:auto;"
                                onchange="loadEmails()">
                                <option value="all">All Campaigns</option>
                                @foreach ($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                                @endforeach
                            </select>
                            <span id="selectedCount" class="badge bg-primary">0 selected</span>
                        </div>
                    </div>

                    @error('recipient_emails')
                        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;">{{ $message }}</div>
                    @enderror

                    <div class="mb-2">
                        <input type="text" id="emailSearch" class="form-control form-control-sm"
                            placeholder="Search emails..." oninput="filterEmailList(this.value)">
                    </div>

                    <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                        <table class="table table-sm align-middle mb-0" id="emailTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width:36px;">
                                        <input type="checkbox" id="selectAll" class="form-check-input"
                                            onchange="toggleAll(this.checked)">
                                    </th>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody id="emailBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4" style="font-size:13px;">
                                        <span class="spinner-border spinner-border-sm me-2"></span>Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="hiddenInputs"></div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span class="material-icons me-1" style="font-size:16px;">send</span>
                        <span id="submitLabel">Send Campaign</span>
                    </button>
                    <a href="{{ route('admin.email-campaigns.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    const EMAILS_URL = '{{ route('admin.email-campaigns.email-list') }}';
    let allContacts  = [];
    let selected     = new Set();

    function loadEmails() {
        const source     = document.getElementById('sourceFilter').value;
        const course     = document.getElementById('courseFilter').value;
        const campaignId = document.getElementById('campaignFilter').value;

        const params = new URLSearchParams({ source, course, campaign_id: campaignId });

        document.getElementById('emailBody').innerHTML =
            '<tr><td colspan="5" class="text-center text-muted py-4" style="font-size:13px;">' +
            '<span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';

        fetch(EMAILS_URL + '?' + params.toString())
            .then(r => r.json())
            .then(contacts => {
                allContacts = contacts;
                renderTable(contacts);
                updateCount();
            })
            .catch(() => {
                document.getElementById('emailBody').innerHTML =
                    '<tr><td colspan="5" class="text-center text-danger py-3" style="font-size:13px;">Failed to load.</td></tr>';
            });
    }

    function sourceBadge(source) {
        if (source === 'Lead') {
            return '<span class="badge" style="background:#dbeafe;color:#1d4ed8;font-weight:600;font-size:11px;">Lead</span>';
        }
        return '<span class="badge" style="background:#ede9fe;color:#6d28d9;font-weight:600;font-size:11px;">Campaign</span>';
    }

    function renderTable(contacts) {
        if (!contacts.length) {
            document.getElementById('emailBody').innerHTML =
                '<tr><td colspan="5" class="text-center text-muted py-4" style="font-size:13px;">No email addresses found.</td></tr>';
            return;
        }
        document.getElementById('emailBody').innerHTML = contacts.map(c => {
            const chk = selected.has(c.email) ? 'checked' : '';
            return `<tr data-email="${c.email}" data-name="${c.name || ''}" data-course="${c.course || ''}">
                <td><input type="checkbox" class="form-check-input row-check" value="${c.email}"
                    data-name="${c.name || ''}" onchange="onRowCheck(this)" ${chk}></td>
                <td style="font-size:13px;">${c.email}</td>
                <td class="text-muted" style="font-size:13px;">${c.name || '—'}</td>
                <td class="text-muted" style="font-size:13px;">${c.course || '—'}</td>
                <td>${sourceBadge(c.source)}</td>
            </tr>`;
        }).join('');
        updateSelectAll();
        buildHiddenInputs();
    }

    function onRowCheck(cb) {
        if (cb.checked) {
            selected.add(cb.value);
        } else {
            selected.delete(cb.value);
        }
        updateCount();
        updateSelectAll();
        buildHiddenInputs();
    }

    function toggleAll(checked) {
        document.querySelectorAll('.row-check').forEach(cb => {
            cb.checked = checked;
            if (checked) selected.add(cb.value);
            else selected.delete(cb.value);
        });
        updateCount();
        buildHiddenInputs();
    }

    function updateSelectAll() {
        const all  = document.querySelectorAll('.row-check');
        const chkd = document.querySelectorAll('.row-check:checked');
        const sa   = document.getElementById('selectAll');
        if (sa) sa.checked = all.length > 0 && all.length === chkd.length;
    }

    function updateCount() {
        document.getElementById('selectedCount').textContent = selected.size + ' selected';
    }

    function buildHiddenInputs() {
        const container = document.getElementById('hiddenInputs');
        container.innerHTML = '';
        const nameMap = {};
        document.querySelectorAll('.row-check').forEach(cb => {
            nameMap[cb.value] = cb.dataset.name || '';
        });
        [...selected].forEach((email, i) => {
            container.innerHTML +=
                `<input type="hidden" name="recipient_emails[]" value="${email}">` +
                `<input type="hidden" name="recipient_names[]" value="${nameMap[email] || ''}">`;
        });
    }

    function filterEmailList(q) {
        q = q.toLowerCase();
        document.querySelectorAll('#emailBody tr[data-email]').forEach(row => {
            const match = row.dataset.email.toLowerCase().includes(q) ||
                          (row.dataset.name || '').toLowerCase().includes(q) ||
                          (row.dataset.course || '').toLowerCase().includes(q);
            row.style.display = match ? '' : 'none';
        });
    }

    function previewTemplate(select) {
        const opt   = select.options[select.selectedIndex];
        const block = document.getElementById('templatePreview');
        if (!opt.value) { block.style.display = 'none'; return; }
        document.getElementById('previewSubject').textContent = opt.dataset.subject;
        document.getElementById('previewBody').innerHTML      = opt.dataset.body;
        block.style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadEmails();

        // Update button label when schedule is set
        document.getElementById('scheduledAt').addEventListener('change', function () {
            document.getElementById('submitLabel').textContent =
                this.value ? 'Schedule Campaign' : 'Send Campaign';
        });

        document.getElementById('ecForm').addEventListener('submit', function () {
            buildHiddenInputs();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        });

        // Pre-select template if old value
        const sel = document.getElementById('templateSelect');
        if (sel && sel.value) previewTemplate(sel);
    });
</script>
@endpush
