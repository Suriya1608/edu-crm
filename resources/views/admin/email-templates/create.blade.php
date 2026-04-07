@extends('layouts.app')
@section('page_title', 'Create Email Template')

@section('content')
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="{{ route('admin.email-templates.index') }}" class="btn btn-sm btn-light">
        <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back
    </a>
    <div>
        <h2 class="page-header-title mb-0">Create Email Template</h2>
        <p class="page-header-subtitle mb-0">Build a reusable email template</p>
    </div>
</div>

<form id="templateForm" action="{{ route('admin.email-templates.store') }}" method="POST">
    @csrf
    <input type="hidden" name="body"          id="hiddenBody">
    <input type="hidden" name="template_type" id="hiddenTemplateType" value="simple">

    {{-- ── Meta fields ──────────────────────────────────────────────────────── --}}
    <div class="chart-card mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-1">Template Name <span class="text-danger">*</span></label>
                <input type="text" name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name') }}" placeholder="e.g. Course Promotion">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold mb-1">Email Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject"
                    class="form-control @error('subject') is-invalid @enderror"
                    value="{{ old('subject') }}" placeholder="e.g. Exciting Courses — Apply Now!">
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   {{ old('status','active')==='active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status')==='inactive'          ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
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
            <span class="material-icons me-1" style="font-size:16px;">save</span>Save Template
        </button>
    </div>
</form>

@include('admin.email-templates._preview-modal')

@push('scripts')
<script>
// Restore body content if validation failed and page was re-rendered
(function () {
    const ta = document.getElementById('simpleBodyTextarea');
    const savedBody = @json(old('body', ''));
    if (ta && savedBody && !ta.value.trim()) {
        ta.value = savedBody;
    }
})();
</script>
@endpush
@endsection
