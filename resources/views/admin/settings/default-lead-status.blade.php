@extends('layouts.app')

@section('page_title', 'Default Lead Status')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <form method="POST" action="{{ route('admin.settings.default-lead-status.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Default Status for New Leads</label>
                    <select name="default_lead_status" class="form-select">
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" {{ \App\Models\Setting::get('default_lead_status', 'new') === $status ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save Default Status</button></div>
        </form>
    </div>
@endsection
