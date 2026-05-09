@extends('layouts.app')

@section('page_title', 'Lead Import')

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-2 d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3>Import Leads (Excel / CSV)</h3>
                <p>Columns: Name, Phone, Email, Course, Source</p>
            </div>
            <a href="{{ route('admin.leads.import.sample') }}"
               class="btn btn-outline-success d-flex align-items-center gap-2" style="white-space:nowrap;">
                <span class="material-icons" style="font-size:18px;">download</span>
                Download Sample Excel
            </a>
        </div>

        <form method="POST" action="{{ route('admin.leads.import.preview') }}" enctype="multipart/form-data" id="previewForm">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year_id" class="form-select" required>
                        <option value="">— Select Academic Year —</option>
                        @foreach ($academicYears ?? [] as $ay)
                            <option value="{{ $ay->id }}"
                                {{ (old('academic_year_id', $academicYearId ?? '') == $ay->id) ? 'selected' : '' }}>
                                {{ $ay->name }}{{ $ay->is_active ? ' (Active)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Select File</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.csv" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2" id="previewBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="previewSpinner" role="status"></span>
                        Preview
                    </button>
                </div>
            </div>
        </form>
    </div>

    @if (isset($enriched) && count($enriched) > 0)
        @php
            $importCount = collect($enriched)->filter(fn($e) => !$e['duplicate'])->count();
            $dupCount    = collect($enriched)->filter(fn($e) => $e['duplicate'])->count();
        @endphp

        @if ($dupCount > 0)
            <div class="alert d-flex align-items-center gap-2 mb-3"
                 style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:13px;border-radius:10px;">
                <span class="material-icons" style="font-size:18px;flex-shrink:0;">block</span>
                <div>
                    <strong>{{ $dupCount }} duplicate row(s) detected</strong> — highlighted below and will be <strong>skipped</strong>.
                    Duplicates are matched by Phone or Email against existing leads and within the file itself.
                </div>
            </div>
        @endif

        <div class="custom-table">
            <div class="table-header">
                <h3>Preview Leads</h3>
                <span class="text-muted" style="font-size:12px;">
                    {{ count($enriched) }} rows
                    @if ($dupCount > 0)
                        &nbsp;·&nbsp;<span style="color:#dc2626;">{{ $dupCount }} duplicate</span>
                        &nbsp;·&nbsp;<span style="color:#16a34a;">{{ $importCount }} to import</span>
                    @endif
                </span>
            </div>

            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Source</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($enriched as $index => $e)
                            <tr @if ($e['duplicate']) style="background:#fef9f9;" @endif>
                                <td class="text-muted" style="font-size:12px;">{{ $index + 1 }}</td>
                                <td @if ($e['duplicate']) style="opacity:.55;" @endif>{{ $e['row'][0] ?? '' }}</td>
                                <td @if ($e['duplicate']) style="opacity:.55;" @endif>{{ $e['row'][1] ?? '' }}</td>
                                <td @if ($e['duplicate']) style="opacity:.55;" @endif>{{ $e['row'][2] ?? '' }}</td>
                                <td @if ($e['duplicate']) style="opacity:.55;" @endif>{{ $e['row'][3] ?? '' }}</td>
                                <td @if ($e['duplicate']) style="opacity:.55;" @endif>{{ $e['row'][4] ?? '' }}</td>
                                <td>
                                    @if ($e['duplicate'])
                                        <span class="badge d-inline-flex align-items-center gap-1"
                                              style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;font-size:11px;font-weight:600;">
                                            <span class="material-icons" style="font-size:12px;">block</span>
                                            {{ $e['duplicate_reason'] }}
                                        </span>
                                    @else
                                        <span class="badge d-inline-flex align-items-center gap-1"
                                              style="background:#f0fdf4;color:#16a34a;border:1px solid #86efac;font-size:11px;font-weight:600;">
                                            <span class="material-icons" style="font-size:12px;">check_circle</span>
                                            Ready
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3">
                <form method="POST" action="{{ route('admin.leads.import.store') }}" id="confirmForm" data-turbo="false">
                    @csrf
                    <input type="hidden" name="leads_data" value="{{ json_encode($cleanRows) }}">
                    <input type="hidden" name="academic_year_id" value="{{ $academicYearId ?? '' }}">
                    @if ($importCount > 0)
                        <button class="btn btn-success d-flex align-items-center gap-2" id="confirmBtn">
                            <span class="spinner-border spinner-border-sm d-none" id="confirmSpinner" role="status"></span>
                            Confirm &amp; Save {{ $importCount }} Lead(s)
                            @if ($dupCount > 0)
                                <span style="font-size:11px;opacity:.8;">({{ $dupCount }} skipped)</span>
                            @endif
                        </button>
                    @else
                        <div class="alert alert-warning mb-0" style="font-size:13px;">
                            <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;">info</span>
                            All rows are duplicates — nothing to import.
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif

@push('scripts')
<script>
(function () {
    var previewBtn  = document.getElementById('previewBtn');
    var previewForm = document.getElementById('previewForm');
    if (previewBtn && previewForm) {
        previewBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!previewForm.checkValidity()) {
                previewForm.reportValidity();
                return;
            }
            document.getElementById('previewSpinner').classList.remove('d-none');
            previewBtn.disabled = true;
            HTMLFormElement.prototype.submit.call(previewForm);
        });
    }

    document.getElementById('confirmForm')?.addEventListener('submit', function () {
        document.getElementById('confirmSpinner').classList.remove('d-none');
        document.getElementById('confirmBtn').disabled = true;
    });
})();
</script>
@endpush
@endsection

