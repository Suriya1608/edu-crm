@extends(auth()->user()?->role === 'manager' ? 'layouts.manager.app' : 'layouts.app')

@section('page_title', 'Change Password')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">

        <div class="chart-card">
            <div class="chart-header mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:rgba(19,127,236,.1);display:flex;align-items:center;justify-content:center;">
                        <span class="material-icons" style="color:#137fec;font-size:22px;">lock_reset</span>
                    </div>
                    <div>
                        <h3 class="mb-0">Change Password</h3>
                        <p class="text-muted mb-0" style="font-size:13px;">Update your account password</p>
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                    <span class="material-icons" style="font-size:18px;">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.change.update') }}" id="changePasswordForm" novalidate>
                @csrf

                {{-- Current Password --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="current_password">
                        Current Password <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control @error('current_password') is-invalid @enderror"
                            id="current_password"
                            name="current_password"
                            placeholder="Enter current password"
                            autocomplete="current-password"
                            required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password', this)" tabindex="-1">
                            <span class="material-icons" style="font-size:18px;vertical-align:middle;">visibility</span>
                        </button>
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- New Password --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="password">
                        New Password <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control @error('password') is-invalid @enderror"
                            id="password"
                            name="password"
                            placeholder="Enter new password"
                            autocomplete="new-password"
                            oninput="checkStrength(this.value)"
                            required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', this)" tabindex="-1">
                            <span class="material-icons" style="font-size:18px;vertical-align:middle;">visibility</span>
                        </button>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Strength meter --}}
                    <div class="mt-2" id="strengthMeter" style="display:none;">
                        <div class="progress" style="height:4px;border-radius:2px;">
                            <div class="progress-bar" id="strengthBar" role="progressbar" style="width:0%;transition:width .3s,background-color .3s;"></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <small id="req-upper"  class="req-badge"><span class="material-icons" style="font-size:12px;vertical-align:middle;">close</span> Uppercase</small>
                            <small id="req-lower"  class="req-badge"><span class="material-icons" style="font-size:12px;vertical-align:middle;">close</span> Lowercase</small>
                            <small id="req-number" class="req-badge"><span class="material-icons" style="font-size:12px;vertical-align:middle;">close</span> Number</small>
                            <small id="req-special" class="req-badge"><span class="material-icons" style="font-size:12px;vertical-align:middle;">close</span> Special (@$!%*?&)</small>
                            <small id="req-length" class="req-badge"><span class="material-icons" style="font-size:12px;vertical-align:middle;">close</span> 8+ chars</small>
                        </div>
                    </div>
                </div>

                {{-- Confirm New Password --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="password_confirmation">
                        Confirm New Password <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control"
                            id="password_confirmation"
                            name="password_confirmation"
                            placeholder="Re-enter new password"
                            autocomplete="new-password"
                            oninput="checkMatch()"
                            required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_confirmation', this)" tabindex="-1">
                            <span class="material-icons" style="font-size:18px;vertical-align:middle;">visibility</span>
                        </button>
                    </div>
                    <div id="matchMsg" class="form-text" style="display:none;"></div>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                        <span class="material-icons align-middle me-1" style="font-size:18px;">lock_reset</span>
                        Update Password
                    </button>
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>

    </div>
</div>

<style>
.req-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
    transition: background .2s, color .2s, border-color .2s;
}
.req-badge.ok {
    background: #d1fae5;
    color: #065f46;
    border-color: #6ee7b7;
}
</style>

<script>
function togglePassword(fieldId, btn) {
    var input = document.getElementById(fieldId);
    var icon  = btn.querySelector('.material-icons');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}

function checkStrength(val) {
    var meter = document.getElementById('strengthMeter');
    meter.style.display = val.length ? 'block' : 'none';

    var rules = {
        upper:   /[A-Z]/.test(val),
        lower:   /[a-z]/.test(val),
        number:  /\d/.test(val),
        special: /[@$!%*?&]/.test(val),
        length:  val.length >= 8,
    };

    setBadge('req-upper',   rules.upper);
    setBadge('req-lower',   rules.lower);
    setBadge('req-number',  rules.number);
    setBadge('req-special', rules.special);
    setBadge('req-length',  rules.length);

    var passed = Object.values(rules).filter(Boolean).length;
    var bar    = document.getElementById('strengthBar');
    var pct    = (passed / 5) * 100;
    bar.style.width = pct + '%';

    if (passed <= 2)      { bar.style.backgroundColor = '#ef4444'; }
    else if (passed <= 3) { bar.style.backgroundColor = '#f59e0b'; }
    else if (passed <= 4) { bar.style.backgroundColor = '#3b82f6'; }
    else                  { bar.style.backgroundColor = '#10b981'; }

    checkMatch();
}

function setBadge(id, ok) {
    var el   = document.getElementById(id);
    var icon = el.querySelector('.material-icons');
    el.classList.toggle('ok', ok);
    icon.textContent = ok ? 'check' : 'close';
}

function checkMatch() {
    var pw   = document.getElementById('password').value;
    var conf = document.getElementById('password_confirmation').value;
    var msg  = document.getElementById('matchMsg');
    if (!conf.length) { msg.style.display = 'none'; return; }
    msg.style.display = 'block';
    if (pw === conf) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = '#10b981';
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = '#ef4444';
    }
}
</script>
@endsection
