@extends('layouts.app')

@section('page_title', 'Automation - Escalation Rules')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon red"><span class="material-icons">priority_high</span></div>
                <div class="stat-label">Escalation Engine</div>
                <div class="stat-value">{{ $values['enabled'] ? 'Enabled' : 'Disabled' }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">timer</span></div>
                <div class="stat-label">Response SLA</div>
                <div class="stat-value">{{ $values['response_sla_minutes'] }} min</div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <h3>Escalation Rules</h3>
            <p>Notify managers for missed follow-ups and response-time SLA breaches.</p>
        </div>
        <form method="POST" action="{{ route('admin.automation.escalation.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                            {{ $values['enabled'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="enabled">Enable escalation automation</label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="missed_followups" name="missed_followups"
                            value="1" {{ $values['missed_followups'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="missed_followups">Escalate missed follow-ups</label>
                    </div>
                    <small class="text-muted">Creates in-app + email manager notification for follow-up misses.</small>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="response_sla" name="response_sla"
                            value="1" {{ $values['response_sla'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="response_sla">Escalate leads not contacted in time</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Lead response SLA (minutes)</label>
                    <input type="number" name="response_sla_minutes" min="5" max="10080" class="form-control"
                        value="{{ $values['response_sla_minutes'] }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Rules</button>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </div>
@endsection
