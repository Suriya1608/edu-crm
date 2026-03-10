@extends('layouts.manager.app')

@section('page_title', 'New Campaign')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-header mb-4">
                    <h3>Create Outbound Campaign</h3>
                    <p class="text-muted small mb-0">After creating the campaign, you can upload your student database.</p>
                </div>

                <form action="{{ route('manager.campaigns.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" placeholder="e.g. 12th Pass Students — June 2026" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3"
                            placeholder="Brief description of this campaign (optional)">{{ old('description') }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons me-1" style="font-size:16px;">arrow_forward</span>
                            Create &amp; Upload Database
                        </button>
                        <a href="{{ route('manager.campaigns.index') }}" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
