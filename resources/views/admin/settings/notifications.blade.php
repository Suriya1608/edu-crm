@extends('layouts.app')

@section('page_title', 'Notification Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <form method="POST" action="{{ route('admin.settings.notifications.update') }}">
            @csrf
            <div class="row g-4">
                <div class="col-md-4">
                    <h6 class="mb-3">Lead Assignment</h6>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="notify_inapp_lead_assignment" value="1"
                            {{ \App\Models\Setting::get('notify_inapp_lead_assignment', '1') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">In-app</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="notify_email_lead_assignment" value="1"
                            {{ \App\Models\Setting::get('notify_email_lead_assignment', '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">Email</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <h6 class="mb-3">Follow-up Reminders</h6>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="notify_inapp_followup_reminder" value="1"
                            {{ \App\Models\Setting::get('notify_inapp_followup_reminder', '1') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">In-app</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="notify_email_followup_reminder" value="1"
                            {{ \App\Models\Setting::get('notify_email_followup_reminder', '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">Email</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <h6 class="mb-3">Escalations</h6>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="notify_inapp_escalation" value="1"
                            {{ \App\Models\Setting::get('notify_inapp_escalation', '1') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">In-app</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="notify_email_escalation" value="1"
                            {{ \App\Models\Setting::get('notify_email_escalation', '1') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">Email</label>
                    </div>
                </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save Notification Settings</button></div>
        </form>
    </div>
@endsection
