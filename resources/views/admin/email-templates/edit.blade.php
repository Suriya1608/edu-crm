@extends('layouts.app')
@section('page_title', 'Edit — ' . $emailTemplate->name)

@section('content')
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="{{ route('admin.email-templates.index') }}" class="btn btn-sm btn-light">
        <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back
    </a>
    <div>
        <h2 class="page-header-title mb-0">Edit Email Template</h2>
        <p class="page-header-subtitle mb-0">{{ $emailTemplate->name }}</p>
    </div>
</div>

<form id="templateForm" action="{{ route('admin.email-templates.update', $emailTemplate) }}" method="POST">
    @csrf @method('PUT')
    <input type="hidden" name="body"          id="hiddenBody">
    <input type="hidden" name="template_type" id="hiddenTemplateType" value="simple">

    {{-- ── Meta fields ──────────────────────────────────────────────────────── --}}
    <div class="chart-card mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-1">Template Name <span class="text-danger">*</span></label>
                <input type="text" name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $emailTemplate->name) }}" placeholder="e.g. Course Promotion">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold mb-1">Email Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject"
                    class="form-control @error('subject') is-invalid @enderror"
                    value="{{ old('subject', $emailTemplate->subject) }}" placeholder="e.g. Exciting Courses — Apply Now!">
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   {{ old('status', $emailTemplate->status) === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status', $emailTemplate->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
        <div class="mt-3 pt-3 border-top d-flex gap-3" style="font-size:12px;color:#64748b;">
            <span><strong>Created by:</strong> {{ $emailTemplate->creator?->name ?? '—' }}</span>
            <span><strong>Created:</strong> {{ $emailTemplate->created_at->format('d M Y, h:i A') }}</span>
            <span><strong>Updated:</strong> {{ $emailTemplate->updated_at->format('d M Y, h:i A') }}</span>
        </div>
    </div>

    @if ($errors->has('body'))
        <div class="alert alert-danger py-2 mb-3">{{ $errors->first('body') }}</div>
    @endif

    {{-- ── Email body editor ───────────────────────────────────────────────── --}}
    @include('admin.email-templates._simple-editor')

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.email-templates.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <span class="material-icons me-1" style="font-size:16px;">save</span>Update Template
        </button>
    </div>
</form>

@include('admin.email-templates._preview-modal')

@push('scripts')
<script>
// Pre-fill textarea with saved body content
(function () {
    const ta = document.getElementById('simpleBodyTextarea');
    if (ta && !ta.value.trim()) {
        ta.value = @json(old('body', $emailTemplate->body ?? ''));
    }
})();
</script>
@endpush
@endsection
