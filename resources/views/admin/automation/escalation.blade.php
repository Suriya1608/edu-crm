@extends('layouts.app')

@section('page_title', 'Automation - Escalation Rules')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon red"><span class="material-icons">priority_high</span></div>
                <div class="stat-label">Escalation Engine</div>
                <div class="stat-value">{{ $values['enabled'] ? 'Enabled' : 'Disabled' }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">support_agent</span></div>
                <div class="stat-label">Telecaller SLA</div>
                <div class="stat-value">{{ $values['response_sla_minutes'] }} min</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon orange"><span class="material-icons">manage_accounts</span></div>
                <div class="stat-label">Manager SLA</div>
                <div class="stat-value">{{ $values['manager_sla_minutes'] }} min</div>
            </div>
        </div>
    </div>

    {{-- Hierarchy diagram --}}
    <div class="chart-card mb-3">
        <div class="chart-header mb-3">
            <h3>Escalation Hierarchy</h3>
            <p class="text-muted mb-0">How unresponded leads flow up the chain automatically.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="p-3 rounded-3 text-center" style="background:#eff6ff;border:1.5px solid #bfdbfe;min-width:140px;">
                <span class="material-icons d-block mb-1" style="color:#3b82f6;font-size:22px;">support_agent</span>
                <div class="fw-semibold small">Telecaller</div>
                <div class="text-muted" style="font-size:11px;">Must respond in</div>
                <div class="fw-bold" style="color:#3b82f6;">{{ $values['response_sla_minutes'] }} min</div>
            </div>
            <div class="text-center px-1">
                <span class="material-icons text-danger" style="font-size:20px;">arrow_forward</span>
                <div class="text-muted" style="font-size:10px;">no response</div>
            </div>
            <div class="p-3 rounded-3 text-center" style="background:#fff7ed;border:1.5px solid #fed7aa;min-width:140px;">
                <span class="material-icons d-block mb-1" style="color:#f97316;font-size:22px;">manage_accounts</span>
                <div class="fw-semibold small">Manager</div>
                <div class="text-muted" style="font-size:11px;">Must respond in</div>
                <div class="fw-bold" style="color:#f97316;">{{ $values['manager_sla_minutes'] }} min</div>
            </div>
            <div class="text-center px-1">
                <span class="material-icons text-danger" style="font-size:20px;">arrow_forward</span>
                <div class="text-muted" style="font-size:10px;">no response</div>
            </div>
            <div class="p-3 rounded-3 text-center" style="background:#fef2f2;border:1.5px solid #fecaca;min-width:140px;">
                <span class="material-icons d-block mb-1" style="color:#ef4444;font-size:22px;">admin_panel_settings</span>
                <div class="fw-semibold small">Admin / Report Viewer</div>
                <div class="text-muted" style="font-size:11px;">Critical alert sent</div>
                <div class="fw-bold" style="color:#ef4444;">Final escalation</div>
            </div>
        </div>
        <div class="mt-3 p-3 rounded-2" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;">
            <span class="material-icons align-middle me-1" style="font-size:15px;color:#6366f1;">info</span>
            <strong>SLA reset:</strong> Any activity on the lead (call, note, WhatsApp, status change, follow-up) by any user resets the escalation clock.
            Lead <em>assignment</em> to a telecaller does <strong>not</strong> count as a response.
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <h3>Escalation Rules</h3>
            <p>Configure SLA timeframes and toggle escalation types.</p>
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
                    <small class="text-muted">Runs the full hierarchy below when leads go unanswered.</small>
                </div>

                <div class="col-12">
                    <hr class="my-1">
                    <div class="fw-semibold mb-2 small text-uppercase text-muted" style="letter-spacing:.04em;">SLA Timeframes</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        <span class="material-icons align-middle me-1" style="font-size:16px;color:#3b82f6;">support_agent</span>
                        Telecaller response SLA (minutes)
                    </label>
                    <input type="number" name="response_sla_minutes" min="5" max="10080" class="form-control"
                        value="{{ $values['response_sla_minutes'] }}">
                    <div class="form-text">If telecaller has no activity within this time → notify manager.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        <span class="material-icons align-middle me-1" style="font-size:16px;color:#f97316;">manage_accounts</span>
                        Manager response SLA (minutes)
                    </label>
                    <input type="number" name="manager_sla_minutes" min="5" max="10080" class="form-control"
                        value="{{ $values['manager_sla_minutes'] }}">
                    <div class="form-text">If still no activity after manager is notified → alert admin &amp; report viewers.</div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Rules</button>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </div>
@endsection
