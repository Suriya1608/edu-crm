@extends('layouts.app')

@section('page_title', 'Automation - Lead Assignment Rules')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">alt_route</span></div>
                <div class="stat-label">Round Robin</div>
                <div class="stat-value">{{ $values['enabled'] ? 'Enabled' : 'Disabled' }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">manage_accounts</span></div>
                <div class="stat-label">Assign To</div>
                <div class="stat-value">{{ $values['active_only'] ? 'Active Managers' : 'All Managers' }}</div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <h3>Lead Assignment Rules</h3>
            <p>Control system-level manager assignment automation.</p>
        </div>
        <form method="POST" action="{{ route('admin.automation.lead-assignment.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                            {{ $values['enabled'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="enabled">Enable auto lead assignment</label>
                    </div>
                    <small class="text-muted">When enabled, incoming leads are assigned using round-robin logic.</small>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active_only" name="active_only" value="1"
                            {{ $values['active_only'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="active_only">Assign only to active managers</label>
                    </div>
                    <small class="text-muted">If disabled, inactive managers are included in assignment pool.</small>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Rules</button>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </div>
@endsection
