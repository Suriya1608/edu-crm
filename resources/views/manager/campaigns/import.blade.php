@extends('layouts.manager.app')

@section('page_title', 'Import Contacts — ' . $campaign->name)

@section('content')
    <div class="mb-3">
        <a href="{{ route('manager.campaigns.show', encrypt($campaign->id)) }}" class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back to Campaign
        </a>
    </div>

    @if (!isset($preview))
        {{-- ── Step 1: Upload Form ── --}}
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="chart-card">
                    <div class="chart-header mb-4">
                        <h3>Upload Student Database</h3>
                        <p class="text-muted small mb-0">Upload an Excel or CSV file. Duplicates (same phone/email within this campaign) and invalid emails will be automatically skipped.</p>
                    </div>

                    <div class="alert alert-info d-flex gap-2 align-items-start mb-4">
                        <span class="material-icons" style="font-size:18px; margin-top:1px;">info</span>
                        <div>
                            <strong>Expected column order:</strong><br>
                            <code>Name | Mobile Number | Email ID | Course | City</code><br>
                            <small class="text-muted">Row 1 should be the header row. Only Name and Mobile Number are required. Mobile numbers will be stored with +91 prefix. Invalid email formats are skipped.</small>
                        </div>
                    </div>

                    <form action="{{ route('manager.campaigns.import.preview', encrypt($campaign->id)) }}"
                        method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Select File <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control @error('file') is-invalid @enderror"
                                accept=".xlsx,.xls,.csv" required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons me-1" style="font-size:16px;">preview</span>
                            Preview &amp; Validate
                        </button>
                    </form>
                </div>
            </div>
        </div>

    @else
        {{-- ── Step 2: Preview & Confirm ── --}}
        <div class="chart-card mb-4">
            <div class="chart-header mb-3">
                <h3>Import Preview</h3>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="stat-card">
                        <div class="stat-icon blue"><span class="material-icons">upload_file</span></div>
                        <div class="stat-label">Total in File</div>
                        <div class="stat-value">{{ $total }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card">
                        <div class="stat-icon green"><span class="material-icons">check_circle</span></div>
                        <div class="stat-label">Will Be Inserted</div>
                        <div class="stat-value">{{ $insertable }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon red"><span class="material-icons">block</span></div>
                        <div class="stat-label">Duplicates</div>
                        <div class="stat-value">{{ $duplicates }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon amber"><span class="material-icons">email</span></div>
                        <div class="stat-label">Invalid Email</div>
                        <div class="stat-value">{{ $invalid }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card">
                        @if ($insertable > 0)
                            <div class="stat-icon green"><span class="material-icons">check</span></div>
                            <div class="stat-label">Status</div>
                            <div class="stat-value" style="font-size:14px; color:#10b981;">Ready</div>
                        @else
                            <div class="stat-icon red"><span class="material-icons">warning</span></div>
                            <div class="stat-label">Status</div>
                            <div class="stat-value" style="font-size:14px; color:#ef4444;">Nothing to Import</div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($insertable > 0)
                <form action="{{ route('manager.campaigns.import.store', encrypt($campaign->id)) }}" method="POST">
                    @csrf
                    @php
                        $validRows = array_filter($preview, fn($r) => !$r['is_duplicate'] && !$r['is_invalid']);
                    @endphp
                    <input type="hidden" name="contacts_data" value="{{ json_encode(array_values($validRows)) }}">
                    <button type="submit" class="btn btn-success mb-3">
                        <span class="material-icons me-1" style="font-size:16px;">save</span>
                        Confirm & Import {{ $insertable }} Record(s)
                    </button>
                </form>
            @else
                <div class="alert alert-warning mb-3">No valid records to import. All records are either duplicates or have invalid email formats.</div>
            @endif

            <a href="{{ route('manager.campaigns.import', encrypt($campaign->id)) }}" class="btn btn-sm btn-outline-secondary mb-3">
                Upload a different file
            </a>
        </div>

        {{-- Preview Table --}}
        <div class="chart-card">
            <div class="chart-header mb-3">
                <h3>Record Preview (first 100 rows shown)</h3>
                <small class="text-muted">
                    <span class="badge bg-danger me-1">Duplicate</span>
                    <span class="badge bg-warning text-dark me-1">Invalid Email</span>
                    rows will be skipped.
                </small>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Mobile (stored as)</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>City</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($preview, 0, 100) as $i => $row)
                            <tr class="{{ $row['is_duplicate'] ? 'table-danger' : ($row['is_invalid'] ? 'table-warning' : '') }}">
                                <td class="text-muted small">{{ $i + 1 }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td><code style="font-size:12px;">{{ $row['phone'] }}</code></td>
                                <td>{{ $row['email'] ?: '—' }}</td>
                                <td>{{ $row['course'] ?: '—' }}</td>
                                <td>{{ $row['city'] ?: '—' }}</td>
                                <td>
                                    @if ($row['is_duplicate'])
                                        <span class="badge bg-danger">Duplicate ({{ $row['dup_reason'] }})</span>
                                    @elseif ($row['is_invalid'])
                                        <span class="badge bg-warning text-dark">Invalid Email</span>
                                    @else
                                        <span class="badge bg-success">New</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($preview) > 100)
                <p class="text-muted small mt-2 px-2">Showing 100 of {{ count($preview) }} rows. All valid records will be imported.</p>
            @endif
        </div>
    @endif
@endsection
