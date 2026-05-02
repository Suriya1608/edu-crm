@extends('layouts.manager.app')

@section('page_title', 'Lead Bulk Import')

@section('content')

{{-- ── Instructions card ───────────────────────────────────────────────────── --}}
<div class="chart-card mb-4">
    <div class="chart-header mb-3">
        <h3 class="d-flex align-items-center gap-2">
            <span class="material-icons" style="color:#6366f1;">upload_file</span>
            Bulk Import Leads
        </h3>
        <p>Upload an <strong>.xlsx</strong> or <strong>.csv</strong> file. Download the sample template to see the exact column format and valid values.</p>
    </div>

    {{-- Column reference ──────────────────────────────────────────────────── --}}
    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered mb-0" style="font-size:12.5px;">
            <thead style="background:#f8fafc;">
                <tr>
                    <th style="width:90px;">Column</th>
                    <th>A — Name</th>
                    <th>B — Phone</th>
                    <th>C — Email</th>
                    <th>D — Course</th>
                    <th>E — Source</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-semibold text-muted">Required?</td>
                    <td><span class="badge bg-danger">Required</span></td>
                    <td><span class="badge bg-danger">Required</span></td>
                    <td><span class="badge bg-secondary">Optional</span></td>
                    <td><span class="badge bg-secondary">Optional</span></td>
                    <td><span class="badge bg-secondary">Optional</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Notes</td>
                    <td>Full name</td>
                    <td>10-digit mobile</td>
                    <td>Email address</td>
                    <td>Exact course name — see <em>Valid Courses</em> sheet in sample</td>
                    <td>See <em>Valid Sources</em> sheet in sample for accepted values</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Upload form + sample download ────────────────────────────────────── --}}
    <form method="POST" action="{{ route('manager.leads.import.preview') }}"
          enctype="multipart/form-data"
          class="d-flex align-items-center gap-2 flex-wrap"
          id="previewForm">
        @csrf
        <input type="file" name="file" class="form-control form-control-sm"
               style="max-width:280px;" accept=".xlsx,.csv" required>
        <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" id="previewBtn">
            <span class="spinner-border spinner-border-sm d-none" id="previewSpinner" role="status"></span>
            <span class="material-icons" style="font-size:16px;">preview</span>
            Preview
        </button>
        <a href="{{ route('manager.leads.import.sample') }}"
           class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size:16px;">download</span>
            Download Sample Excel
        </a>
    </form>
</div>

{{-- ── Preview table (shown after file upload) ─────────────────────────────── --}}
@isset($enriched)
@if (count($enriched) > 0)

    @php
        $unmatchedCourses = collect($enriched)
            ->filter(fn($e) => !$e['course_matched'] && $e['course_name'] !== '')
            ->count();

        $sourceBadgeMap = [
            'facebook_ads'  => ['label' => 'Facebook Ads',  'bg' => '#eff6ff', 'color' => '#2563eb'],
            'instagram_ads' => ['label' => 'Instagram Ads', 'bg' => '#fdf2f8', 'color' => '#db2777'],
            'google_ads'    => ['label' => 'Google Ads',    'bg' => '#fef2f2', 'color' => '#dc2626'],
            'social_media'  => ['label' => 'Social Media',  'bg' => '#f0f9ff', 'color' => '#0ea5e9'],
            'walk_in'       => ['label' => 'Walk-in',       'bg' => '#f0fdf4', 'color' => '#16a34a'],
            'referral'      => ['label' => 'Referral',      'bg' => '#faf5ff', 'color' => '#7c3aed'],
            'newspaper'     => ['label' => 'Newspaper',     'bg' => '#f8fafc', 'color' => '#475569'],
            'tv'            => ['label' => 'TV Advert',     'bg' => '#fffbeb', 'color' => '#d97706'],
            'other'         => ['label' => 'Other',         'bg' => '#f8fafc', 'color' => '#94a3b8'],
        ];
    @endphp

    @if ($unmatchedCourses > 0)
        <div class="alert d-flex align-items-center gap-2 mb-3"
             style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;border-radius:10px;">
            <span class="material-icons" style="font-size:18px;flex-shrink:0;">warning</span>
            <div>
                <strong>{{ $unmatchedCourses }} row(s)</strong> have an unrecognised course name and will be imported with <strong>no course assigned</strong>.
                You can update them from the lead detail page after import.
                Refer to the <em>Valid Courses</em> sheet in the sample file for the exact spelling.
            </div>
        </div>
    @endif

    <div class="custom-table mb-4">
        <div class="table-header">
            <h3>Preview — {{ count($enriched) }} rows</h3>
            <span class="text-muted" style="font-size:12px;">Review carefully before confirming</span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enriched as $i => $e)
                        <tr>
                            <td class="text-muted" style="font-size:12px;">{{ $i + 1 }}</td>
                            <td class="fw-semibold">{{ $e['row'][0] ?? '—' }}</td>
                            <td>{{ $e['row'][1] ?? '—' }}</td>
                            <td class="text-muted" style="font-size:12px;">{{ $e['row'][2] ?? '—' }}</td>

                            {{-- Course with match indicator --}}
                            <td>
                                @if ($e['course_name'] === '')
                                    <span class="text-muted">—</span>
                                @elseif ($e['course_matched'])
                                    <span class="d-flex align-items-center gap-1" style="color:#16a34a;font-size:13px;">
                                        <span class="material-icons" style="font-size:15px;">check_circle</span>
                                        {{ $e['course_name'] }}
                                    </span>
                                @else
                                    <span class="d-flex align-items-center gap-1" style="color:#d97706;font-size:13px;">
                                        <span class="material-icons" style="font-size:15px;">warning</span>
                                        {{ $e['course_name'] }}
                                        <small class="text-muted ms-1">(not found)</small>
                                    </span>
                                @endif
                            </td>

                            {{-- Source with mapped badge --}}
                            <td>
                                @php
                                    $b = $sourceBadgeMap[$e['source_mapped']] ?? ['label' => $e['source_raw'] ?: 'Other', 'bg' => '#f8fafc', 'color' => '#94a3b8'];
                                @endphp
                                @if ($e['source_raw'] !== '')
                                    <span class="badge"
                                          style="background:{{ $b['bg'] }};color:{{ $b['color'] }};
                                                 border:1px solid {{ $b['color'] }}33;font-size:11px;font-weight:600;">
                                        {{ $b['label'] }}
                                    </span>
                                @else
                                    <span class="text-muted" style="font-size:12px;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2"
             style="background:#f8fafc;">
            <small class="text-muted">
                <span class="material-icons" style="font-size:14px;vertical-align:middle;color:#16a34a;">check_circle</span> = course matched &nbsp;
                <span class="material-icons" style="font-size:14px;vertical-align:middle;color:#d97706;">warning</span> = course not found (will import without course)
            </small>
            <form method="POST" action="{{ route('manager.leads.import.store') }}" id="confirmForm">
                @csrf
                <input type="hidden" name="leads_data" value="{{ json_encode($rows) }}">
                <button class="btn btn-success btn-sm px-4 d-flex align-items-center gap-1" id="confirmBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="confirmSpinner" role="status"></span>
                    <span class="material-icons" style="font-size:16px;">check_circle</span>
                    Confirm &amp; Import {{ count($enriched) }} Leads
                </button>
            </form>
        </div>
    </div>

@else
    <div class="alert alert-warning" style="font-size:13px;">
        <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;">info</span>
        The uploaded file appears to be empty (no data rows found after the header).
    </div>
@endif
@endisset

@push('scripts')
<script>
    document.getElementById('previewForm')?.addEventListener('submit', function () {
        document.getElementById('previewSpinner').classList.remove('d-none');
        document.getElementById('previewBtn').disabled = true;
    });
    document.getElementById('confirmForm')?.addEventListener('submit', function () {
        document.getElementById('confirmSpinner').classList.remove('d-none');
        document.getElementById('confirmBtn').disabled = true;
    });
</script>
@endpush

@endsection
