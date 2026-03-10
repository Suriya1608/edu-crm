@extends('layouts.app')

@section('page_title', 'Automation - Follow-up Reminder Rules')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">notifications_active</span></div>
                <div class="stat-label">Reminder Alerts</div>
                <div class="stat-value">{{ $values['enabled'] ? 'Enabled' : 'Disabled' }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon red"><span class="material-icons">warning</span></div>
                <div class="stat-label">Overdue Highlight</div>
                <div class="stat-value">{{ $values['highlight_overdue'] ? 'Enabled' : 'Disabled' }}</div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <h3>Follow-up Reminder Rules</h3>
            <p>Configure telecaller reminders, overdue highlighting, and panel alerts.</p>
        </div>
        <form method="POST" action="{{ route('admin.automation.followup-reminders.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                            {{ $values['enabled'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="enabled">Enable follow-up reminder notifications</label>
                    </div>
                    <small class="text-muted">Sends reminder alerts to telecaller panel notification feed.</small>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Reminder days before due date</label>
                    <input type="number" name="days_before" min="0" max="30" class="form-control"
                        value="{{ $values['days_before'] }}">
                    <small class="text-muted">0 means remind on same day only.</small>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="highlight_overdue" name="highlight_overdue"
                            value="1" {{ $values['highlight_overdue'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="highlight_overdue">Highlight overdue follow-ups in telecaller alerts</label>
                    </div>
                </div>
            </div>

            <hr class="my-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="daily_summary_email_enabled"
                            name="daily_summary_email_enabled" value="1"
                            {{ ($values['daily_summary_email_enabled'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="daily_summary_email_enabled">
                            Send daily performance summary email to managers (7 PM)
                        </label>
                    </div>
                    <small class="text-muted">Managers receive a summary of each telecaller's calls, conversions, and follow-ups every evening.</small>
                </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Rules</button>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </div>
@endsection
