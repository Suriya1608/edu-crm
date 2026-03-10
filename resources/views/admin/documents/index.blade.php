@extends('layouts.app')

@section('page_title', 'Document Management')

@section('content')
    <div class="row g-4">

        {{-- Upload Form --}}
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-header mb-3">
                    <h3>Upload Document</h3>
                    <p class="text-muted small mb-0">PDF, Word, Excel, PPT, or Image (max 20 MB)</p>
                </div>

                <form method="POST" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Document Title <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="title"
                            class="form-control @error('title') is-invalid @enderror"
                            placeholder="e.g. MBA Brochure 2026"
                            value="{{ old('title') }}"
                            required
                        >
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">File <span class="text-danger">*</span></label>
                        <input
                            type="file"
                            name="file"
                            class="form-control @error('file') is-invalid @enderror"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                            required
                        >
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted">Allowed: PDF, Word, Excel, PPT, JPG, PNG</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;">upload_file</span>
                        Upload Document
                    </button>
                </form>
            </div>
        </div>

        {{-- Document List --}}
        <div class="col-lg-8">
            <div class="custom-table">
                <div class="table-header">
                    <h3>All Documents</h3>
                    <span class="text-muted small">{{ $documents->count() }} {{ Str::plural('document', $documents->count()) }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table mb-0" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>File</th>
                                <th>Size</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documents as $doc)
                                <tr>
                                    <td class="fw-semibold">{{ $doc->title }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="material-icons" style="font-size:16px;color:#64748b;">{{ $doc->icon }}</span>
                                            <span class="text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $doc->file_name }}">
                                                {{ $doc->file_name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-muted">{{ $doc->file_size_formatted }}</td>
                                    <td>{{ $doc->uploader?->name ?? '—' }}</td>
                                    <td class="text-muted">{{ $doc->created_at->format('d M Y') }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('documents.download', $doc->id) }}"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Download">
                                                <span class="material-icons" style="font-size:15px;vertical-align:middle;">download</span>
                                            </a>
                                            <form method="POST" action="{{ route('admin.documents.destroy', $doc->id) }}"
                                                  onsubmit="return confirm('Delete this document?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <span class="material-icons" style="font-size:15px;vertical-align:middle;">delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <span class="material-icons d-block mb-1" style="font-size:32px;color:#cbd5e1;">folder_open</span>
                                        No documents uploaded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
