<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TCN Softphone Test Console</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Manrope', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            font-size: 13px;
        }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Header Ã¢â€â‚¬Ã¢â€â‚¬ */
        .header {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-logo {
            width: 32px; height: 32px;
            background: #137fec;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #fff;
        }
        .header h1 { font-size: 15px; font-weight: 700; color: #f1f5f9; }
        .header .subtitle { font-size: 11px; color: #64748b; margin-left: 4px; }
        .header-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .badge-env {
            background: #0f172a;
            border: 1px solid #334155;
            color: #94a3b8;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
        }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Layout Ã¢â€â‚¬Ã¢â€â‚¬ */
        .layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            grid-template-rows: auto 1fr;
            height: calc(100vh - 57px);
        }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Panel base Ã¢â€â‚¬Ã¢â€â‚¬ */
        .panel {
            border-right: 1px solid #1e293b;
            border-bottom: 1px solid #1e293b;
            overflow-y: auto;
        }
        .panel-title {
            padding: 10px 16px;
            background: #1e293b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Left column Ã¢â€â‚¬Ã¢â€â‚¬ */
        .left-col {
            display: flex;
            flex-direction: column;
            grid-row: 1 / 3;
            border-right: 1px solid #334155;
            overflow-y: auto;
        }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Credentials section Ã¢â€â‚¬Ã¢â€â‚¬ */
        .creds-section { padding: 16px; border-bottom: 1px solid #1e293b; }
        .field-group { margin-bottom: 12px; }
        .field-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .field-group input {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-family: 'JetBrains Mono', monospace;
            outline: none;
            transition: border-color 0.15s;
        }
        .field-group input:focus { border-color: #137fec; }
        .field-group input.filled { border-color: #10b981; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px;
            border-radius: 6px;
            border: none;
            font-family: 'Manrope', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-primary   { background: #137fec; color: #fff; }
        .btn-primary:hover { background: #0f6dd1; }
        .btn-success   { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-danger    { background: #ef4444; color: #fff; }
        .btn-danger:hover  { background: #dc2626; }
        .btn-ghost     { background: #1e293b; color: #94a3b8; border: 1px solid #334155; }
        .btn-ghost:hover { background: #334155; color: #e2e8f0; }
        .btn-warning   { background: #f59e0b; color: #000; }
        .btn-sm { padding: 5px 10px; font-size: 11px; }
        .btn:disabled  { opacity: 0.4; cursor: not-allowed; }
        .btn-full { width: 100%; justify-content: center; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Session state Ã¢â€â‚¬Ã¢â€â‚¬ */
        .state-section { padding: 16px; border-bottom: 1px solid #1e293b; }
        .state-grid { display: grid; gap: 6px; }
        .state-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: #0f172a;
            border-radius: 6px;
            border: 1px solid #1e293b;
        }
        .state-label { font-size: 10px; color: #64748b; font-weight: 600; min-width: 90px; }
        .state-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: #94a3b8;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }
        .state-value.set { color: #10b981; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Softphone controls Ã¢â€â‚¬Ã¢â€â‚¬ */
        .phone-section { padding: 16px; flex: 1; }
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            padding: 8px 12px;
            background: #0f172a;
            border-radius: 8px;
            border: 1px solid #1e293b;
        }
        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #64748b;
            flex-shrink: 0;
        }
        .status-dot.ready { background: #10b981; box-shadow: 0 0 6px #10b981; }
        .status-dot.calling { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; animation: pulse 1s infinite; }
        .status-dot.error { background: #ef4444; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .status-text { font-size: 12px; font-weight: 600; color: #94a3b8; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Right column Ã¢â€â‚¬Ã¢â€â‚¬ */
        .right-col {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ API Steps Ã¢â€â‚¬Ã¢â€â‚¬ */
        .steps-section { padding: 16px; border-bottom: 1px solid #334155; }
        .step-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: #0f172a;
            border-radius: 8px;
            border: 1px solid #1e293b;
            margin-bottom: 6px;
            transition: border-color 0.2s;
        }
        .step-row.active { border-color: #137fec; }
        .step-row.success { border-color: #10b981; }
        .step-row.error   { border-color: #ef4444; }
        .step-num {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: #1e293b;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700; color: #64748b;
            flex-shrink: 0;
        }
        .step-num.done { background: #10b981; color: #fff; }
        .step-num.fail { background: #ef4444; color: #fff; }
        .step-info { flex: 1; }
        .step-name { font-size: 12px; font-weight: 600; color: #e2e8f0; }
        .step-url  { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: #475569; margin-top: 1px; }
        .step-result { font-size: 10px; color: #64748b; margin-top: 2px; }
        .step-result.ok  { color: #10b981; }
        .step-result.err { color: #ef4444; }
        .step-actions { display: flex; gap: 6px; align-items: center; }
        .step-spinner {
            width: 16px; height: 16px;
            border: 2px solid #334155;
            border-top-color: #137fec;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Call panel Ã¢â€â‚¬Ã¢â€â‚¬ */
        .call-section {
            padding: 16px;
            border-bottom: 1px solid #334155;
            background: #1e293b22;
        }
        .call-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .call-input {
            flex: 1;
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'JetBrains Mono', monospace;
            outline: none;
        }
        .call-input:focus { border-color: #137fec; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Log panel Ã¢â€â‚¬Ã¢â€â‚¬ */
        .log-section {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .log-toolbar {
            padding: 8px 16px;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .log-body {
            flex: 1;
            overflow-y: auto;
            padding: 12px 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            line-height: 1.6;
        }
        .log-entry { margin-bottom: 4px; display: flex; gap: 8px; }
        .log-time  { color: #475569; flex-shrink: 0; }
        .log-tag   { font-weight: 700; flex-shrink: 0; }
        .log-tag.info  { color: #60a5fa; }
        .log-tag.ok    { color: #34d399; }
        .log-tag.err   { color: #f87171; }
        .log-tag.warn  { color: #fbbf24; }
        .log-tag.event { color: #a78bfa; }
        .log-msg   { color: #cbd5e1; word-break: break-all; }
        .log-json  {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 3px;
            color: #94a3b8;
            white-space: pre-wrap;
            word-break: break-all;
            font-size: 10px;
        }
        .log-json .jk { color: #93c5fd; }
        .log-json .jv { color: #86efac; }
        .log-json .js { color: #fca5a5; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ Quick run button Ã¢â€â‚¬Ã¢â€â‚¬ */
        .run-all-bar {
            padding: 12px 16px;
            border-bottom: 1px solid #334155;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .divider {
            height: 1px;
            background: #1e293b;
            margin: 8px 0;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-logo">
        <span class="material-icons" style="font-size:18px;">phone_in_talk</span>
    </div>
    <div>
        <h1>TCN Softphone Test Console</h1>
    </div>
    <span class="subtitle">- Diagnostic & Integration Test</span>
    <div class="header-right">
        <span class="badge-env">ENV: {{ app()->environment() }}</span>
        <span class="badge-env">TCN API: api.bom.tcn.com</span>
        <a href="{{ route('admin.settings.call') }}" class="btn btn-ghost btn-sm">
            <span class="material-icons" style="font-size:14px;">arrow_back</span> Back to Settings
        </a>
    </div>
</div>

<!-- Layout -->
<div class="layout">

    <!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â LEFT COLUMN Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
    <div class="left-col">

        <!-- Credentials -->
        <div class="panel-title">
            <span class="material-icons" style="font-size:13px;">key</span>
            Credentials
        </div>
        <div class="creds-section">
            <div class="field-group">
                <label>Client ID</label>
                <input type="text" id="cred-client-id" placeholder="ra4fp6z3jkaeloio"
                    value="{{ env('TCN_CLIENT_ID', '') }}">
            </div>
            <div class="field-group">
                <label>Client Secret</label>
                <input type="password" id="cred-client-secret" placeholder="************"
                    value="{{ env('TCN_CLIENT_SECRET', '') }}">
            </div>
            <div class="field-group">
                <label>Refresh Token</label>
                <input type="password" id="cred-refresh-token" placeholder="paste your refresh token"
                    value="{{ \App\Models\Setting::getSecure('tcn_refresh_token', env('TCN_REFRESH_TOKEN', '')) }}">
            </div>
            <button class="btn btn-ghost btn-sm" onclick="toggleSecrets()">
                <span class="material-icons" style="font-size:13px;" id="eye-icon">visibility</span>
                Show/Hide
            </button>
        </div>

        <!-- Session State -->
        <div class="panel-title">
            <span class="material-icons" style="font-size:13px;">memory</span>
            Session State
        </div>
        <div class="state-section">
            <div class="state-grid">
                <div class="state-row">
                    <span class="state-label">Access Token</span>
                    <span class="state-value" id="st-token">-</span>
                </div>
                <div class="state-row">
                    <span class="state-label">Agent SID</span>
                    <span class="state-value" id="st-agent">-</span>
                </div>
                <div class="state-row">
                    <span class="state-label">Hunt Group</span>
                    <span class="state-value" id="st-hg">-</span>
                </div>
                <div class="state-row">
                    <span class="state-label">Skills</span>
                    <span class="state-value" id="st-skills">-</span>
                </div>
                <div class="state-row">
                    <span class="state-label">ASM Session</span>
                    <span class="state-value" id="st-asm">-</span>
                </div>
                <div class="state-row">
                    <span class="state-label">SIP User</span>
                    <span class="state-value" id="st-sip">-</span>
                </div>
                <div class="state-row">
                    <span class="state-label">Dial URL</span>
                    <span class="state-value" id="st-dial">-</span>
                </div>
                <div class="state-row">
                    <span class="state-label">SIP Status</span>
                    <span class="state-value" id="st-sip-status">-</span>
                </div>
                <div class="state-row">
                    <span class="state-label">Caller ID</span>
                    <span class="state-value" id="st-callerid">-</span>
                </div>
            </div>
        </div>

        <!-- Softphone Controls -->
        <div class="panel-title">
            <span class="material-icons" style="font-size:13px;">phone</span>
            Softphone Controls
        </div>
        <div class="phone-section">
            <div class="status-indicator">
                <div class="status-dot" id="status-dot"></div>
                <span class="status-text" id="status-text">Not Initialized</span>
            </div>

            <button class="btn btn-primary btn-full" id="btn-login" onclick="doLogin()">
                <span class="material-icons" style="font-size:15px;">login</span>
                Initialize Softphone (Full Login)
            </button>
            <div style="margin-top: 8px;">
                <button class="btn btn-ghost btn-full" id="btn-logout" onclick="doLogout()" disabled>
                    <span class="material-icons" style="font-size:15px;">logout</span>
                    Logout &amp; Reset
                </button>
            </div>

            <div class="divider"></div>

            <!-- Manual Overrides -->
            <div class="panel-title" style="margin:0 -12px 8px; padding:6px 12px; font-size:10px; letter-spacing:.08em;">
                <span class="material-icons" style="font-size:12px;">tune</span>
                Manual Overrides
            </div>
            <div class="field-group">
                <label>Hunt Group SID <span style="font-size:10px;color:#64748b;">(overrides Step 2)</span></label>
                <input type="text" id="override-hg" placeholder="e.g. 35412" inputmode="numeric">
            </div>
            <div class="field-group">
                <label>Caller ID <span style="font-size:10px;color:#64748b;">(overrides Step 5)</span></label>
                <input type="text" id="override-callerid" placeholder="e.g. 8634134466" inputmode="tel">
            </div>
            <button class="btn btn-ghost btn-full" onclick="applyOverrides()" style="font-size:12px; padding:6px 8px; margin-bottom:8px;">
                <span class="material-icons" style="font-size:13px;">check_circle</span>
                Apply Overrides
            </button>

            <div class="divider"></div>

            <div class="field-group">
                <label>Phone Number to Dial <span style="font-size:10px;color:#64748b;">(passed to backend session - NOT SIP target)</span></label>
                <input type="tel" id="dial-phone" placeholder="+91XXXXXXXXXX or 10-digit">
            </div>
            <div style="display:flex; gap:8px;">
                <button class="btn btn-success" style="flex:1;" id="btn-call" onclick="doCall()" disabled>
                    <span class="material-icons" style="font-size:15px;">call</span> Call
                </button>
                <button class="btn btn-danger" style="flex:1;" id="btn-hangup" onclick="doHangup()" disabled>
                    <span class="material-icons" style="font-size:15px;">call_end</span> Hang Up
                </button>
            </div>

            <div class="divider"></div>
            <div class="field-group" style="margin-bottom:0;">
                <label>Test Lead ID (for call log)</label>
                <input type="number" id="test-lead-id" placeholder="1" value="1">
            </div>

        </div>

    </div>

    <!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â RIGHT COLUMN Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
    <div class="right-col">

        <!-- Run All -->
        <div class="run-all-bar">
            <button class="btn btn-primary" onclick="runAllSteps()">
                <span class="material-icons" style="font-size:15px;">play_arrow</span>
                Run All API Steps
            </button>
            <button class="btn btn-ghost btn-sm" onclick="resetAllSteps()">
                <span class="material-icons" style="font-size:14px;">refresh</span> Reset Steps
            </button>
            <span style="color:#475569;font-size:11px;margin-left:4px;">
                Run each step individually or all at once to validate TCN connectivity.
            </span>
        </div>

        <!-- API Steps -->
        <div class="steps-section">

            <div class="step-row" id="step-1">
                <div class="step-num" id="step-1-num">1</div>
                <div class="step-info">
                    <div class="step-name">Generate Access Token</div>
                    <div class="step-url">POST /tcn/test/token -> TCN /token (refresh_token grant)</div>
                    <div class="step-result" id="step-1-result">Waiting...</div>
                </div>
                <div class="step-actions">
                    <div class="step-spinner" id="step-1-spin"></div>
                    <button class="btn btn-ghost btn-sm" onclick="runStep(1)">Run</button>
                </div>
            </div>

            <div class="step-row" id="step-2">
                <div class="step-num" id="step-2-num">2</div>
                <div class="step-info">
                    <div class="step-name">Get Current Agent</div>
                    <div class="step-url">POST /tcn/agent -> TCN /p3api/getcurrentagent</div>
                    <div class="step-result" id="step-2-result">Waiting...</div>
                </div>
                <div class="step-actions">
                    <div class="step-spinner" id="step-2-spin"></div>
                    <button class="btn btn-ghost btn-sm" onclick="runStep(2)">Run</button>
                </div>
            </div>

            <div class="step-row" id="step-3">
                <div class="step-num" id="step-3-num">3</div>
                <div class="step-info">
                    <div class="step-name">Get Agent Skills</div>
                    <div class="step-url">POST /tcn/skills -> TCN /p3api/getagentskills</div>
                    <div class="step-result" id="step-3-result">Waiting...</div>
                </div>
                <div class="step-actions">
                    <div class="step-spinner" id="step-3-spin"></div>
                    <button class="btn btn-ghost btn-sm" onclick="runStep(3)">Run</button>
                </div>
            </div>

            <div class="step-row" id="step-4">
                <div class="step-num" id="step-4-num">4</div>
                <div class="step-info">
                    <div class="step-name">Create ASM Session</div>
                    <div class="step-url">POST /tcn/session -> TCN /asm/asm/createsession (VOICE)</div>
                    <div class="step-result" id="step-4-result">Waiting...</div>
                </div>
                <div class="step-actions">
                    <div class="step-spinner" id="step-4-spin"></div>
                    <button class="btn btn-ghost btn-sm" onclick="runStep(4)">Run</button>
                </div>
            </div>

            <div class="step-row" id="step-5">
                <div class="step-num" id="step-5-num">5</div>
                <div class="step-info">
                    <div class="step-name">SIP.js Login Call (INVITE to dial_url)</div>
                    <div class="step-url">Loads SIP.js, then sends WSS INVITE to sip:{dial_url}@sg-webphone.tcnp3.com</div>
                    <div class="step-result" id="step-5-result">Waiting...</div>
                </div>
                <div class="step-actions">
                    <div class="step-spinner" id="step-5-spin"></div>
                    <button class="btn btn-ghost btn-sm" onclick="runStep(5)">Run</button>
                </div>
            </div>

            <div class="step-row" id="step-6">
                <div class="step-num" id="step-6-num">6</div>
                <div class="step-info">
                    <div class="step-name">Keep-Alive Ping</div>
                    <div class="step-url">POST /tcn/keepalive -> TCN /acd/agentgetstatus (performKeepAlive: true)</div>
                    <div class="step-result" id="step-6-result">Waiting...</div>
                </div>
                <div class="step-actions">
                    <div class="step-spinner" id="step-6-spin"></div>
                    <button class="btn btn-ghost btn-sm" onclick="runStep(6)">Run</button>
                </div>
            </div>

        </div>

        <!-- Log Panel -->
        <div class="log-section">
            <div class="log-toolbar">
                <span class="panel-title" style="padding:0;background:none;border:none;">
                    <span class="material-icons" style="font-size:13px;">terminal</span>
                    API Log
                </span>
                <button class="btn btn-ghost btn-sm" onclick="clearLog()" style="margin-left:auto;">
                    <span class="material-icons" style="font-size:13px;">delete_sweep</span> Clear
                </button>
                <button class="btn btn-ghost btn-sm" onclick="copyLog()">
                    <span class="material-icons" style="font-size:13px;">content_copy</span> Copy
                </button>
            </div>
            <div class="log-body" id="log-body"></div>
        </div>

    </div>
</div>

<script>
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// State
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
var S = {
    accessToken:      null,
    agentSid:         null,
    clientSid:        null,
    huntGroupSid:     null,
    skills:           {},
    asmSessionSid:    null,
    voiceSessionSid:  null,
    currentSessionId: null,
    sipUser:          null,
    sipPass:          null,
    dialUrl:          null,
    callerIdPhone:    null,
    ua:               null,
    sipSession:       null,  // presence SIP session (dialUrl INVITE = agent login channel)
    outboundSession:  null,  // active outbound call SIP session
    sipCallActive:    false, // true when presence dialUrl call is Established
    loggedIn:         false,
    callActive:       false,
    activeCall:       null,
    activeLogId:      null,
    callStartTime:    0,     // Date.now() when outbound INVITE was sent
    keepAliveTimer:   null,  // setInterval handle Ã¢â‚¬â€ 30 s periodic keep-alive
    callKeepAliveTimer: null,
    sessionSwitchInProgress: false,
};

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Helpers
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function csrf() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
}

function now() {
    return new Date().toLocaleTimeString('en-IN', { hour12: false });
}

function addLog(tag, tagClass, msg, json) {
    var body = document.getElementById('log-body');
    var entry = document.createElement('div');
    entry.className = 'log-entry';
    entry.innerHTML =
        '<span class="log-time">' + now() + '</span>' +
        '<span class="log-tag ' + tagClass + '">[' + tag + ']</span>' +
        '<span class="log-msg">' + escHtml(msg) + '</span>';
    body.appendChild(entry);

    if (json !== undefined) {
        var pre = document.createElement('pre');
        pre.className = 'log-json';
        pre.textContent = JSON.stringify(json, null, 2);
        body.appendChild(pre);
    }
    body.scrollTop = body.scrollHeight;
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function clearLog() { document.getElementById('log-body').innerHTML = ''; }

function copyLog() {
    var text = document.getElementById('log-body').innerText;
    navigator.clipboard.writeText(text).then(function(){
        addLog('COPY', 'ok', 'Log copied to clipboard');
    });
}

function setState(id, value, isSet) {
    var el = document.getElementById('st-' + id);
    if (!el) return;
    el.textContent = value || '-';
    el.className = 'state-value' + (isSet ? ' set' : '');
}

function setStatus(state, text) {
    var dot  = document.getElementById('status-dot');
    var txt  = document.getElementById('status-text');
    dot.className = 'status-dot ' + state;
    txt.textContent = text;
}

function stepPending(n) {
    document.getElementById('step-' + n).className = 'step-row active';
    document.getElementById('step-' + n + '-num').className = 'step-num';
    document.getElementById('step-' + n + '-spin').style.display = 'block';
    document.getElementById('step-' + n + '-result').textContent = 'Running...';
    document.getElementById('step-' + n + '-result').className = 'step-result';
}

function stepOk(n, text) {
    document.getElementById('step-' + n).className = 'step-row success';
    document.getElementById('step-' + n + '-num').className = 'step-num done';
    document.getElementById('step-' + n + '-num').textContent = 'OK';
    document.getElementById('step-' + n + '-spin').style.display = 'none';
    document.getElementById('step-' + n + '-result').textContent = text;
    document.getElementById('step-' + n + '-result').className = 'step-result ok';
}

function stepFail(n, text) {
    document.getElementById('step-' + n).className = 'step-row error';
    document.getElementById('step-' + n + '-num').className = 'step-num fail';
    document.getElementById('step-' + n + '-num').textContent = 'X';
    document.getElementById('step-' + n + '-spin').style.display = 'none';
    document.getElementById('step-' + n + '-result').textContent = text;
    document.getElementById('step-' + n + '-result').className = 'step-result err';
}

function stepWarn(n, text) {
    document.getElementById('step-' + n).className = 'step-row';
    document.getElementById('step-' + n + '-num').className = 'step-num';
    document.getElementById('step-' + n + '-num').textContent = '!';
    document.getElementById('step-' + n + '-spin').style.display = 'none';
    document.getElementById('step-' + n + '-result').textContent = text;
    document.getElementById('step-' + n + '-result').className = 'step-result warn';
}

function toggleSecrets() {
    ['cred-client-secret','cred-refresh-token'].forEach(function(id) {
        var el = document.getElementById(id);
        el.type = el.type === 'password' ? 'text' : 'password';
    });
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// API Helpers
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
async function apiPost(url, body, useBearer) {
    var headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() };
    if (useBearer && S.accessToken) headers['Authorization'] = 'Bearer ' + S.accessToken;
    var res  = await fetch(url, { method: 'POST', headers: headers, body: JSON.stringify(body || {}) });
    var json = await res.json();
    return { ok: res.ok, status: res.status, data: json };
}

function isoNow() {
    return new Date().toISOString();
}

function getLoginSessionSid() {
    return S.voiceSessionSid || S.asmSessionSid ? String(S.voiceSessionSid || S.asmSessionSid) : null;
}

function getCallSessionSid(call) {
    if (!call) return null;
    return call.voiceSessionSid || call.asmSessionSid ? String(call.voiceSessionSid || call.asmSessionSid) : null;
}

function buildClientCallSid() {
    return 'tcn-web-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
}

function updateCallControls() {
    document.getElementById('btn-call').disabled = !S.loggedIn || !!S.activeCall;
    document.getElementById('btn-hangup').disabled = !S.activeCall;
}

async function createTcnCallLog(call) {
    if (!call) return null;
    if (call.logId) return call.logId;
    if (call.logCreatePromise) return call.logCreatePromise;

    call.logCreatePromise = (async function() {
        var lr = await fetch('/tcn/call-log', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({
                lead_id: call.leadId,
                phone: call.phone,
                call_sid: call.clientCallSid || getCallSessionSid(call),
            }),
        });

        if (!lr.ok) {
            addLog('CALL', 'warn', 'Call log create failed, HTTP ' + lr.status);
            return null;
        }

        var ld = await lr.json();
        call.logId = ld.call_log_id;
        S.activeLogId = call.logId;
        addLog('CALL', 'ok', 'Call log ' + (ld.existing ? 'reused' : 'created') + ': #' + call.logId);
        return call.logId;
    })().catch(function(e) {
        addLog('CALL', 'warn', 'Call log create error: ' + e.message);
        return null;
    }).finally(function() {
        call.logCreatePromise = null;
    });

    return call.logCreatePromise;
}

async function patchTcnCallLog(logId, payload) {
    if (!logId) return false;

    try {
        var res = await fetch('/tcn/call-log/' + logId, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            var data = await res.json().catch(function() { return {}; });
            addLog('CALL', 'warn', 'Call log update failed, HTTP ' + res.status, data);
            return false;
        }

        return true;
    } catch (e) {
        addLog('CALL', 'warn', 'Call log update error: ' + e.message);
        return false;
    }
}

function computeCallDuration(call) {
    if (!call || !call.establishedAt || !call.endedAt) return 0;

    var establishedAt = new Date(call.establishedAt).getTime();
    var endedAt = new Date(call.endedAt).getTime();

    if (!isFinite(establishedAt) || !isFinite(endedAt) || endedAt <= establishedAt) {
        return 0;
    }

    return Math.max(0, Math.round((endedAt - establishedAt) / 1000));
}

function computeCallDurationMs(call) {
    if (!call || !call.establishedAt || !call.endedAt) return 0;

    var establishedAt = new Date(call.establishedAt).getTime();
    var endedAt = new Date(call.endedAt).getTime();

    if (!isFinite(establishedAt) || !isFinite(endedAt) || endedAt <= establishedAt) {
        return 0;
    }

    return Math.max(0, endedAt - establishedAt);
}

function clearAnswerConfirmation(call) {
    if (!call || !call.answerConfirmTimer) return;
    clearTimeout(call.answerConfirmTimer);
    call.answerConfirmTimer = null;
}

function scheduleAnswerConfirmation(call) {
    clearAnswerConfirmation(call);
    if (!call || call.finalized || call.answerSynced || !call.establishedAt) return;

    call.answerConfirmTimer = setTimeout(function() {
        if (!S.activeCall || S.activeCall !== call || call.finalized || call.answerSynced || !call.establishedAt) {
            return;
        }

        call.answeredAt = call.answeredAt || call.establishedAt;
        call.answerSynced = true;
        syncCallLogFromSip(call, 'answered');
        addLog('CALL', 'ok', 'Call answer confirmed after stable connection');
    }, 1500);
}

async function syncCallLogFromSip(call, status) {
    if (!call) return;

    var logId = await createTcnCallLog(call);
    if (!logId) return;

    var payload = {
        status: status,
        call_sid: call.clientCallSid || getCallSessionSid(call),
    };

    if (status === 'ringing') {
        payload.duration = 0;
    } else if (status === 'answered') {
        payload.answered_at = call.answeredAt || isoNow();
        payload.duration = 0;
    } else {
        payload.ended_at = call.endedAt || isoNow();
        payload.duration = computeCallDuration(call);
        if (call.answeredAt) {
            payload.answered_at = call.answeredAt;
        }
        if (call.endReason) {
            payload.end_reason = call.endReason;
        }
    }

    var ok = await patchTcnCallLog(logId, payload);
    if (ok) {
        addLog('CALL', 'ok', 'Call log #' + logId + ' synced as ' + status);
    }
}

async function pingSessionKeepAlive(sessionSid, tag) {
    if (!sessionSid || !S.accessToken) return { ok: false, skipped: true };

    var r = await apiPost('/tcn/keepalive', { sessionSid: String(sessionSid) }, true);
    var alive = r.ok && r.data && r.data.keepAliveSucceeded !== false;
    var responseSessionId = (r.data && (r.data.currentSessionId || r.data.sessionId || 0)) || 0;

    addLog(tag, alive ? 'ok' : 'warn',
        'Keep-alive ' + (alive ? 'OK' : 'FAILED') +
        ' | requestSid=' + sessionSid +
        ' | status=' + ((r.data && r.data.statusDesc) || '?') +
        ' | sessionId=' + responseSessionId);

    return r;
}

function isInvalidKeepAliveResponse(r, expectedSessionSid) {
    if (!r || !r.ok || !r.data) return true;

    var status = String(r.data.statusDesc || '').toUpperCase();
    var currentSessionId = String(r.data.currentSessionId || r.data.sessionId || 0);

    if (currentSessionId === '0') return true;
    if (status === 'UNAVAILABLE' || status === 'UNAVALIABLE') return true;
    if (expectedSessionSid && currentSessionId !== String(expectedSessionSid)) return true;

    return false;
}

function stopCallKeepAlive() {
    if (S.callKeepAliveTimer) {
        clearInterval(S.callKeepAliveTimer);
        S.callKeepAliveTimer = null;
        addLog('CALL-KA', 'warn', 'Call keep-alive stopped');
    }
}

async function startCallKeepAlive(sessionSid) {
    stopCallKeepAlive();
    if (!sessionSid) return;
    if (String(sessionSid) === String(getLoginSessionSid()) && S.keepAliveTimer) {
        addLog('CALL-KA', 'info', 'Reusing login keep-alive for active sessionSid=' + sessionSid);
        return;
    }

    async function runCallKeepAlive() {
        if (!S.activeCall || S.activeCall.finalized) return;

        try {
            var r = await pingSessionKeepAlive(sessionSid, 'CALL-KA');
            if (isInvalidKeepAliveResponse(r, sessionSid)) {
                addLog('CALL-KA', 'err', 'Call keep-alive returned an invalid session. Active voiceSessionSid=' + sessionSid);
            }
        } catch (e) {
            addLog('CALL-KA', 'err', 'Call keep-alive request error: ' + e.message);
        }
    }

    setTimeout(runCallKeepAlive, 5000);
    S.callKeepAliveTimer = setInterval(runCallKeepAlive, 30000);

    addLog('CALL-KA', 'info', 'Call keep-alive armed for voiceSessionSid=' + sessionSid + ' (first ping in 5 s, then every 30 s)');
}

async function finalizeActiveCall(status, reason) {
    var call = S.activeCall;
    if (!call || call.finalized) return;

    call.finalized = true;
    call.endedAt = call.endedAt || isoNow();
    clearAnswerConfirmation(call);
    if (reason) {
        call.endReason = reason;
    }

    var duration = computeCallDuration(call);
    var durationMs = computeCallDurationMs(call);
    var resolvedStatus = status || 'failed';

    if (resolvedStatus === 'completed' && !call.establishedAt) {
        resolvedStatus = 'failed';
    }

    if (call.establishedAt && durationMs < 1000) {
        resolvedStatus = 'failed';
    }

    if (!call.establishedAt && (resolvedStatus === 'completed' || resolvedStatus === 'answered')) {
        resolvedStatus = 'failed';
    }

    if (!call.establishedAt && (resolvedStatus === 'canceled' || resolvedStatus === 'rejected')) {
        resolvedStatus = 'missed';
    }

    if (call.establishedAt && durationMs >= 1000) {
        call.answeredAt = call.answeredAt || call.establishedAt;
        if (!call.answerSynced) {
            call.answerSynced = true;
            await syncCallLogFromSip(call, 'answered');
        }

        if (resolvedStatus === 'failed' || resolvedStatus === 'missed') {
            resolvedStatus = 'completed';
        }
    } else {
        call.answeredAt = null;
    }

    await syncCallLogFromSip(call, resolvedStatus);

    stopCallKeepAlive();
    S.callActive = false;
    S.outboundSession = null;
    S.activeCall = null;
    S.activeLogId = null;
    S.callStartTime = 0;

    updateCallControls();

    if (S.loggedIn) {
        setStatus('ready', 'Ready');
    } else {
        setStatus('error', 'Session expired - re-initialize');
    }

    var summary = 'Call ' + resolvedStatus;
    if (reason) summary += ' - ' + reason;
    if (call.establishedAt) summary += ' - duration ' + duration + 's';
    addLog('CALL', resolvedStatus === 'completed' ? 'ok' : 'warn', summary);
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Individual Steps
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

async function step1_token() {
    stepPending(1);
    addLog('STEP 1', 'info', 'Generating access token via refresh_token grant...');
    var r = await apiPost('/tcn/test/token', {
        client_id:     document.getElementById('cred-client-id').value.trim(),
        client_secret: document.getElementById('cred-client-secret').value.trim(),
        refresh_token: document.getElementById('cred-refresh-token').value.trim(),
    });
    addLog('RESP', r.ok ? 'ok' : 'err', 'HTTP ' + r.status, r.data);
    if (!r.ok || !r.data.access_token) {
        stepFail(1, r.data.error || 'Token request failed (' + r.status + ')');
        throw new Error('Step 1 failed');
    }
    S.accessToken = r.data.access_token;
    setState('token', S.accessToken.substring(0, 20) + '...', true);
    stepOk(1, 'Token obtained (expires_in: ' + (r.data.expires_in || '?') + 's)');
    addLog('STEP 1', 'ok', 'Access token obtained');
}

async function step2_agent() {
    stepPending(2);
    addLog('STEP 2', 'info', 'Getting current agent...');
    var r = await apiPost('/tcn/agent', {}, true);
    addLog('RESP', r.ok ? 'ok' : 'err', 'HTTP ' + r.status, r.data);
    if (!r.ok) {
        stepFail(2, 'Failed (' + r.status + '): ' + JSON.stringify(r.data));
        throw new Error('Step 2 failed');
    }
    S.agentSid  = r.data.agentSid;
    S.clientSid = r.data.clientSid;
    // Respect manual override if set before this step ran
    var hgOverride = document.getElementById('override-hg').value.trim();
    S.huntGroupSid = hgOverride || r.data.huntGroupSid;
    if (hgOverride) addLog('STEP 2', 'warn', 'Hunt Group SID overridden to ' + hgOverride + ' (API returned ' + r.data.huntGroupSid + ')');
    setState('agent', S.agentSid, true);
    setState('hg', S.huntGroupSid, true);
    stepOk(2, 'agentSid=' + S.agentSid + ' huntGroupSid=' + S.huntGroupSid);
    addLog('STEP 2', 'ok', 'Agent: ' + S.agentSid + ' | HuntGroup: ' + S.huntGroupSid);
}

async function step3_skills() {
    stepPending(3);
    addLog('STEP 3', 'info', 'Getting agent skills for huntGroupSid=' + S.huntGroupSid);
    var r = await apiPost('/tcn/skills', { huntGroupSid: parseInt(S.huntGroupSid), agentSid: parseInt(S.agentSid) }, true);
    addLog('RESP', r.ok ? 'ok' : 'err', 'HTTP ' + r.status, r.data);
    if (!r.ok) {
        stepFail(3, 'Failed (' + r.status + ')');
        throw new Error('Step 3 failed');
    }
    S.skills = r.data.skills || r.data || {};
    setState('skills', Object.keys(S.skills).length + ' skill(s)', true);
    stepOk(3, Object.keys(S.skills).length + ' skill(s): ' + Object.keys(S.skills).join(', '));
    addLog('STEP 3', 'ok', 'Skills: ' + JSON.stringify(S.skills));
}

async function step4_session() {
    stepPending(4);
    addLog('STEP 4', 'info', 'Creating ASM session (VOICE)...');
    var r = await apiPost('/tcn/session', {
        huntGroupSid:    parseInt(S.huntGroupSid),
        skills:          S.skills,
        subsession_type: 'VOICE',
    }, true);
    addLog('RESP', r.ok ? 'ok' : 'err', 'HTTP ' + r.status, r.data);
    if (!r.ok) {
        stepFail(4, 'Failed (' + r.status + ')');
        throw new Error('Step 4 failed');
    }
    var d = r.data;
    S.asmSessionSid   = d.asmSessionSid   || d.asm_session_sid;
    S.voiceSessionSid = d.voiceSessionSid || d.voice_session_sid;
    var vr = d.voiceRegistration || d.voice_registration;
    if (!vr) {
        stepFail(4, 'No voiceRegistration in response');
        addLog('STEP 4', 'err', 'Raw response has no voiceRegistration. Keys: ' + Object.keys(d).join(', '));
        throw new Error('Step 4 failed: no voiceRegistration');
    }
    S.sipUser  = vr.username;
    S.sipPass  = vr.password;
    S.dialUrl  = vr.dialUrl || vr.dial_url;
    S.pstnPhone = vr.pstnPhone || vr.pstn_phone;
    setState('asm', S.asmSessionSid, true);
    setState('sip', S.sipUser, true);
    setState('dial', S.dialUrl ? S.dialUrl.substring(0, 20) + '...' : '-', !!S.dialUrl);
    S.callerIdPhone = document.getElementById('override-callerid').value.trim() || S.callerIdPhone || '';
    setState('callerid', S.callerIdPhone || 'none', !!S.callerIdPhone);
    var keepAliveSid = getLoginSessionSid();
    if (keepAliveSid) {
        addLog('STEP 4', 'info', 'ASM session created. voiceSessionSid=' + keepAliveSid + ' will be kept alive after SIP is established.');
    }
    stepOk(4, 'asmSid=' + S.asmSessionSid + ' voiceSid=' + S.voiceSessionSid + ' sipUser=' + S.sipUser);
    addLog('STEP 4', 'ok', 'ASM: ' + S.asmSessionSid + ' | Voice: ' + S.voiceSessionSid + ' | SIP user: ' + S.sipUser + ' | dialUrl: ' + S.dialUrl);
}

async function step5_sip() {
    stepPending(5);
    addLog('STEP 5', 'info', 'Loading SIP.js - will CALL dial_url (not REGISTER) per TCN spec...');
    if (!S.sipUser || !S.sipPass || !S.dialUrl) {
        stepFail(5, 'SIP credentials / dial_url not available - run Step 4 first');
        throw new Error('No SIP credentials or dial_url');
    }
    await loadSipJs();
    var SIP = (window.SIP && window.SIP.SIP) ? window.SIP.SIP : window.SIP;
    if (!SIP || !SIP.UserAgent) {
        stepFail(5, 'SIP.js not loaded from /js/sip.js');
        throw new Error('SIP.js missing');
    }
    addLog('STEP 5', 'info', 'SIP.js loaded. Target: sip:' + S.dialUrl + '@sg-webphone.tcnp3.com');
    await callDialUrl(SIP);
}

async function step6_keepalive() {
    stepPending(6);
    var sid = getLoginSessionSid();
    if (!sid) {
        stepFail(6, 'No voiceSessionSid - run Step 4 first');
        throw new Error('No session SID');
    }

    addLog('STEP 6', 'info', 'Sending keep-alive ping using sessionSid=' + sid);
    var r = await pingSessionKeepAlive(sid, 'STEP 6');

    if (r.ok && r.data && r.data.currentSessionId) {
        S.currentSessionId = r.data.currentSessionId;
    }

    if (!r.ok || (r.data && r.data.keepAliveSucceeded === false) || isInvalidKeepAliveResponse(r, sid)) {
        stepFail(6, 'Keep-alive failed (' + r.status + ')');
        throw new Error('Keep-alive failed');
    }

    stepOk(6, 'Keep-alive OK - agent is ' + ((r.data && r.data.statusDesc) || 'READY'));
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Step dispatcher
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
async function runStep(n) {
    try {
        if (n === 1) await step1_token();
        else if (n === 2) await step2_agent();
        else if (n === 3) await step3_skills();
        else if (n === 4) await step4_session();
        else if (n === 5) await step5_sip();
        else if (n === 6) await step6_keepalive();
    } catch(e) {
        addLog('ERROR', 'err', e.message);
    }
}

async function runAllSteps() {
    addLog('RUN', 'info', '=== Running all API steps ===');
    try {
        await step1_token();
        await step2_agent();
        await step3_skills();
        await step4_session();
        await step5_sip();
        await step6_keepalive();
        addLog('RUN', 'ok', '=== All steps complete - softphone READY ===');
        S.loggedIn = true;
        setStatus('ready', 'Ready');
        document.getElementById('btn-login').disabled = true;
        document.getElementById('btn-logout').disabled = false;
        updateCallControls();
    } catch (e) {
        addLog('RUN', 'err', '=== Stopped at error: ' + e.message + ' ===');
        updateCallControls();
    }
}

function resetAllSteps() {
    [1,2,3,4,5,6].forEach(function(n) {
        document.getElementById('step-' + n).className = 'step-row';
        document.getElementById('step-' + n + '-num').className = 'step-num';
        document.getElementById('step-' + n + '-num').textContent = n;
        document.getElementById('step-' + n + '-spin').style.display = 'none';
        document.getElementById('step-' + n + '-result').textContent = 'Waiting...';
        document.getElementById('step-' + n + '-result').className = 'step-result';
    });
    ['token','agent','hg','skills','asm','sip','dial','sip-status','callerid'].forEach(function(id) {
        setState(id, '-', false);
    });
    setStatus('', 'Not Initialized');
    S.accessToken = S.agentSid = S.huntGroupSid = S.asmSessionSid = S.voiceSessionSid = S.currentSessionId = null;
    S.skills = {}; S.loggedIn = false; S.sipCallActive = false; S.activeCall = null; S.callActive = false;
    stopKeepAlive();
    stopCallKeepAlive();
    updateCallControls();
    addLog('RESET', 'warn', 'State cleared - ready for fresh test run');
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// SIP.js
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function loadSipJs() {
    if (window.SIP) return Promise.resolve();
    return new Promise(function(resolve, reject) {
        var s = document.createElement('script');
        s.src = '/js/sip.js';
        s.onload = resolve;
        s.onerror = function() { reject(new Error('Failed to load /js/sip.js')); };
        document.head.appendChild(s);
    });
}

/**
 * callDialUrl Ã¢â‚¬â€ Per TCN Operator API doc (page 3):
 * "Use SIP.js to call in to the dial Url returned in the create session response."
 * e.g. sip:012148cdb158aa41759af635d7c856c072@sg-webphone.tcnp3.com
 *
 * This establishes the agent's audio channel to TCN (NOT a REGISTER).
 * Once this SIP call is Established, the agent is READY in TCN and manual dial works.
 */
function callDialUrl(SIP) {
    return new Promise(function(resolve, reject) {
        var wsUri   = 'wss://sg-webphone.tcnp3.com';
        var settled = false;

        var timer = setTimeout(function() {
            if (settled) return;
            settled = true;
            stepFail(5, 'SIP call timed out (20 s) - dial_url may be expired or auth failed');
            addLog('STEP 5', 'err', 'Timeout: SIP INVITE to dial_url did not establish within 20 s');
            reject(new Error('SIP call timed out'));
        }, 20000);

        S.ua = new SIP.UserAgent({
            uri:                   SIP.UserAgent.makeURI('sip:' + S.sipUser + '@sg-webphone.tcnp3.com'),
            transportConstructor:  SIP.Web.Transport,
            transportOptions:      { server: wsUri },
            authorizationUsername: S.sipUser,
            authorizationPassword: S.sipPass,
            reconnectionAttempts:  0,
            reconnectionDelay:     0,
            logLevel:              'warn',
            sessionDescriptionHandlerFactoryOptions: {
                constraints: { audio: true, video: false },
            },
        });

        S.ua.start().then(function() {
            var target  = SIP.UserAgent.makeURI('sip:' + S.dialUrl + '@sg-webphone.tcnp3.com');
            if (!target) {
                throw new Error('Invalid dial_url - cannot build SIP URI: ' + S.dialUrl);
            }
            addLog('STEP 5', 'info', 'WSS connected. Sending INVITE to sip:' + S.dialUrl.substring(0, 20) + '...@sg-webphone.tcnp3.com');

            var inviter  = new SIP.Inviter(S.ua, target);
            S.sipSession = inviter;

            inviter.stateChange.addListener(function(state) {
                addLog('SIP', 'event', 'SIP session state -> ' + state);
                setState('sip-status', state, state === 'Established');

                if (state === 'Established' && !settled) {
                    clearTimeout(timer);
                    S.sipCallActive = true;
                    attachRemoteAudio(inviter, 'tcn-presence-audio');

                    addLog('STEP 5', 'ok', 'SIP call Established. Waiting 2 s for TCN ACD to mark agent READY...');
                    setTimeout(function() {
                        if (settled) return;
                        settled = true;
                        S.loggedIn = true;
                        startKeepAlive();
                        stepOk(5, 'SIP call Established - audio channel open');
                        resolve();
                    }, 2000);
                } else if (state === 'Terminated' && !settled) {
                    settled = true;
                    clearTimeout(timer);
                    stepFail(5, 'SIP call Terminated before establishing - check credentials / dial_url');
                    reject(new Error('SIP call terminated before Established'));
                } else if (state === 'Terminated' && settled) {
                    if (S.sessionSwitchInProgress) {
                        S.sipSession = null;
                        addLog('SIP', 'warn', 'Previous ASM SIP session terminated during planned session switch.');
                        return;
                    }
                    S.sipCallActive = false;
                    S.loggedIn      = false;
                    stopKeepAlive();
                    stepFail(5, 'SIP dropped after login - ASM session expired. Click "Initialize Softphone" to reconnect.');
                    addLog('SIP', 'err', 'SIP session dropped. Re-initialize the softphone.');
                    document.getElementById('btn-login').disabled  = false;
                    updateCallControls();
                    setStatus('error', 'SIP Dropped - Re-initialize');
                    if (S.activeCall && !S.activeCall.finalized) {
                        finalizeActiveCall('failed', 'ASM session expired');
                    }
                }
            });

            return inviter.invite({
                sessionDescriptionHandlerOptions: {
                    constraints: { audio: true, video: false },
                },
            });
        }).catch(function(err) {
            if (!settled) {
                settled = true;
                clearTimeout(timer);
                setState('sip-status', 'Failed', false);
                stepFail(5, err.message || 'SIP failed');
                addLog('STEP 5', 'err', 'SIP error: ' + (err.message || err));
                reject(err);
            }
        });
    });
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Periodic keep-alive (TCN doc: every 30 s while logged in)
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function startKeepAlive() {
    stopKeepAlive();
    S.keepAliveTimer = setInterval(async function() {
        var sid = getLoginSessionSid();
        if (!S.accessToken || !sid || !S.loggedIn) return;
        try {
            var r = await pingSessionKeepAlive(sid, 'KA');
            if (r.ok && r.data.currentSessionId) {
                S.currentSessionId = r.data.currentSessionId;
            }
            var alive = r.ok && r.data && r.data.keepAliveSucceeded !== false;
            if (!alive || isInvalidKeepAliveResponse(r, sid)) {
                addLog('KA', 'err', 'Keep-alive failed - session may have expired. Re-initialize the softphone.');
                stopKeepAlive();
                S.loggedIn = false;
                setStatus('error', 'Session expired - re-initialize');
                document.getElementById('btn-login').disabled = false;
                updateCallControls();
            }
        } catch(e) {
            addLog('KA', 'err', 'Keep-alive request error: ' + e.message);
        }
    }, 30000);
    addLog('KA', 'info', 'Keep-alive interval started (every 30 s)');
}

function stopKeepAlive() {
    if (S.keepAliveTimer) {
        clearInterval(S.keepAliveTimer);
        S.keepAliveTimer = null;
        addLog('KA', 'warn', 'Keep-alive interval stopped');
    }
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function applyOverrides() {
    var hg  = document.getElementById('override-hg').value.trim();
    var cid = document.getElementById('override-callerid').value.trim();
    var applied = [];

    if (hg) {
        S.huntGroupSid = hg;
        setState('hg', hg, true);
        applied.push('huntGroupSid=' + hg);
    }
    if (cid) {
        S.callerIdPhone = cid;
        setState('callerid', cid, true);
        applied.push('callerIdPhone=' + cid);
    }

    if (applied.length) {
        addLog('OVERRIDE', 'ok', 'Manual override applied: ' + applied.join(', '));
    } else {
    addLog('OVERRIDE', 'warn', 'No override values entered - fill in at least one field');
    }
}

async function doLogin() {
    document.getElementById('btn-login').disabled = true;
    setStatus('calling', 'Initializing...');
    await runAllSteps();
}

function doLogout() {
    stopKeepAlive();
    stopCallKeepAlive();
    // Hang up the dial_url SIP session Ã¢â‚¬â€ this ends the agent's TCN login
    if (S.sipSession) { try { S.sipSession.bye(); } catch(_) {} S.sipSession = null; }
    if (S.outboundSession) { try { S.outboundSession.bye(); } catch(_) {} S.outboundSession = null; }
    if (S.ua) { try { S.ua.stop(); } catch(_) {} S.ua = null; }
    S.loggedIn = false; S.callActive = false; S.sipCallActive = false;
    S.outboundSession = null; S.callStartTime = 0; S.activeLogId = null; S.activeCall = null;
    document.getElementById('btn-login').disabled = false;
    document.getElementById('btn-logout').disabled = true;
    updateCallControls();
    setStatus('', 'Not Initialized');
    resetAllSteps();
    addLog('LOGOUT', 'warn', 'Softphone stopped and state cleared');
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Remote Audio helper
// Attaches the remote WebRTC track to a hidden <audio> element.
// CRITICAL: Without this the agent hears nothing even if SIP
// Established fires Ã¢â‚¬â€ the MediaStream exists but is not rendered.
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function attachRemoteAudio(session, elementId) {
    elementId = elementId || 'tcn-remote-audio';
    try {
        var sdh = session.sessionDescriptionHandler;
        if (!sdh || !sdh.peerConnection) { addLog('AUDIO', 'warn', 'No peerConnection yet'); return; }
        var remoteStream = new MediaStream();
        sdh.peerConnection.getReceivers().forEach(function(r) {
            if (r.track) remoteStream.addTrack(r.track);
        });
        var el = document.getElementById(elementId);
        if (!el) {
            el = document.createElement('audio');
            el.id = elementId; el.autoplay = true;
            el.setAttribute('playsinline', ''); el.style.display = 'none';
            document.body.appendChild(el);
        }
        el.srcObject = remoteStream;
        el.play().catch(function(e) { addLog('AUDIO', 'warn', 'audio.play() blocked: ' + e.message); });
        addLog('AUDIO', 'ok', 'Remote audio attached -> #' + elementId);
    } catch(e) {
        addLog('AUDIO', 'warn', 'attachRemoteAudio error (non-fatal): ' + e.message);
    }
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Call controls Ã¢â‚¬â€ pure SIP/WebRTC flow (manualdial REST removed)
//
// Architecture (confirmed 2026-04-03):
//   /dial/prepare, /dial/process, /dial/start all return 404
//   "default backend" Ã¢â‚¬â€ these paths are NOT on the K8s ingress for
//   this TCN account. Eliminated entirely.
//
//   Correct flow (mirrors TCN Operator panel):
//   1. Create FRESH ASM session per call Ã¢â‚¬â€ pass phoneNumber so TCN
//      configures the PSTN leg on their backend.
//   2. SIP INVITE Ã¢â€ â€™ sip:{fresh_dial_url}@sg-webphone.tcnp3.com
//      dial_url is a one-time routing token Ã¢â‚¬â€ NEVER the phone number.
//   3. TCN bridges PSTN (customer) to this SIP session (agent audio).
//   4. Hangup = SIP BYE on the outbound session.
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
async function doCall() {
    var phone  = document.getElementById('dial-phone').value.trim();
    var leadId = document.getElementById('test-lead-id').value.trim();
    if (!phone)  { addLog('CALL', 'err', 'Enter a phone number first'); return; }
    if (!S.loggedIn || !S.ua) { addLog('CALL', 'err', 'Softphone not initialized. Run all steps first.'); return; }
    if (S.callActive || S.activeCall || S.outboundSession) { addLog('CALL', 'err', 'Call already active.'); return; }

    var digits = phone.replace(/\D/g, '');
    if (digits.startsWith('91') && digits.length === 12) digits = digits.slice(2);
    if (digits.startsWith('00')) digits = digits.slice(2);
    if (digits.length < 7) { addLog('CALL', 'err', 'Invalid phone number: ' + phone); return; }

    addLog('CALL', 'info', '=== Outbound call to: ' + digits + ' ===');
    setStatus('calling', 'Dialing ' + phone + '...');

    try {
        var callDialUrl = S.dialUrl;
        var callAsmSessionSid = S.asmSessionSid;
        var callVoiceSessionSid = S.voiceSessionSid;

        if (!callDialUrl) throw new Error('No active dial_url on logged-in ASM session. Re-initialize the softphone.');
        if (!callVoiceSessionSid || String(callVoiceSessionSid) === '0') {
            throw new Error('No active voiceSessionSid on logged-in ASM session. Re-initialize the softphone.');
        }

        addLog('CALL', 'info', 'A) Reusing active ASM session | voiceSessionSid=' + callVoiceSessionSid);
        var sessionHealth = await pingSessionKeepAlive(callVoiceSessionSid, 'CALL-PREFLIGHT');
        if (sessionHealth.ok && sessionHealth.data && sessionHealth.data.currentSessionId) {
            S.currentSessionId = sessionHealth.data.currentSessionId;
        }
        if (isInvalidKeepAliveResponse(sessionHealth, callVoiceSessionSid)) {
            throw new Error('Active ASM session is not healthy. Re-initialize the softphone before calling.');
        }

        addLog('CALL', 'info', 'B) SIP INVITE -> sip:' + callDialUrl.substring(0, 24) + '...@sg-webphone.tcnp3.com');
        var SIP = (window.SIP && window.SIP.SIP) ? window.SIP.SIP : window.SIP;
        var target = SIP.UserAgent.makeURI('sip:' + callDialUrl + '@sg-webphone.tcnp3.com');
        if (!target) throw new Error('Invalid outbound dial_url: ' + callDialUrl);
        var inviter = new SIP.Inviter(S.ua, target);
        var call = {
            leadId: parseInt(leadId, 10) || 1,
            phone: phone,
            digits: digits,
            displayPhone: phone,
            asmSessionSid: callAsmSessionSid,
            voiceSessionSid: callVoiceSessionSid,
            inviteStartedAt: isoNow(),
            establishedAt: null,
            answeredAt: null,
            endedAt: null,
            hangupRequested: false,
            finalized: false,
            established: false,
            logId: null,
            logCreatePromise: null,
            ringingSynced: false,
            answerSynced: false,
            answerConfirmTimer: null,
            clientCallSid: buildClientCallSid(),
            endReason: null,
        };

        S.activeCall = call;
        S.callActive = true;
        S.outboundSession = inviter;
        updateCallControls();
        await createTcnCallLog(call);

        inviter.stateChange.addListener(function(state) {
            addLog('SIP-OUT', 'event', 'Outbound SIP state -> ' + state);
            if (state === 'Establishing') {
                if (!call.ringingSynced && !call.finalized) {
                    call.ringingSynced = true;
                    syncCallLogFromSip(call, 'ringing');
                }
            } else if (state === 'Established') {
                if (call.finalized || call.established) return;
                call.established = true;
                call.establishedAt = isoNow();
                attachRemoteAudio(inviter, 'tcn-remote-audio');
                addLog('CALL', 'ok', '=== Call Established - monitoring for stable connection ===');
                setStatus('calling', 'In Call: ' + phone);
                startCallKeepAlive(getCallSessionSid(call));
                scheduleAnswerConfirmation(call);
            } else if (state === 'Terminated') {
                call.endedAt = call.endedAt || isoNow();
                call.endReason = call.endReason || (call.hangupRequested ? 'Hangup requested' : 'SIP session terminated');
                finalizeActiveCall(call.establishedAt ? 'completed' : 'failed', call.endReason);
            }
        });

        await inviter.invite({
            sessionDescriptionHandlerOptions: { constraints: { audio: true, video: false } },
        });

        S.callStartTime = Date.now();
        addLog('CALL', 'ok', '=== SIP INVITE sent - waiting for answer... ===');

    } catch (e) {
        if (S.activeCall && !S.activeCall.finalized) {
            S.activeCall.endedAt = S.activeCall.endedAt || isoNow();
            await finalizeActiveCall(S.activeCall.establishedAt ? 'completed' : 'failed', e.message);
        } else {
            S.callActive = false;
            S.outboundSession = null;
            S.callStartTime = 0;
            S.activeLogId = null;
            updateCallControls();
            setStatus(S.loggedIn ? 'ready' : 'error', S.loggedIn ? 'Ready' : 'Call Failed');
        }
        addLog('CALL', 'err', '=== Call failed: ' + e.message + ' ===');
    }
}

async function doHangup() {
    addLog('CALL', 'info', 'Hanging up...');
    var call = S.activeCall;
    if (!call) {
        updateCallControls();
        if (S.loggedIn) setStatus('ready', 'Ready');
        return;
    }

    call.hangupRequested = true;
    call.endedAt = isoNow();

    // BYE the outbound SIP session Ã¢â‚¬â€ this terminates the per-call WebRTC leg.
    // The login presence session (S.sipSession) is NOT touched; it stays alive.
    if (S.outboundSession) {
        try { S.outboundSession.bye(); addLog('CALL', 'ok', 'SIP BYE sent'); }
        catch(e) { addLog('CALL', 'warn', 'SIP BYE error (non-fatal): ' + e.message); }
    }

    // Belt-and-suspenders: also signal TCN ACD via REST
    var callSessionSid = getCallSessionSid(call);
    if (callSessionSid) {
        try {
            var r = await apiPost('/tcn/disconnect', { sessionSid: callSessionSid }, true);
            addLog('RESP', r.ok ? 'ok' : 'warn', 'agentdisconnect HTTP ' + r.status, r.data);
        } catch(e) { addLog('CALL', 'warn', 'agentdisconnect error (non-fatal): ' + e.message); }
    }

    // stateChange 'Terminated' handler in doCall() should finalize the call.
    // If the SIP session is already gone, finalize here.
    if (!S.outboundSession || call.finalized) {
        await finalizeActiveCall(call.establishedAt ? 'completed' : 'canceled', 'Hangup requested');
    }

    addLog('CALL', 'ok', '=== Hangup complete ===');
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Init
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
document.addEventListener('DOMContentLoaded', function() {
    addLog('INIT', 'info', 'TCN Softphone Test Console ready');
    addLog('INIT', 'info', 'TCN Auth:   https://auth.tcn.com/token');
    addLog('INIT', 'info', 'TCN API:    https://api.bom.tcn.com');
    addLog('INIT', 'info', 'SIP Server: wss://sg-webphone.tcnp3.com');
    addLog('INIT', 'info', 'Proxy base: ' + window.location.origin + '/tcn/*');
    addLog('INIT', 'info', '--- Credentials pre-filled from DB/env. Edit if needed. ---');
});
</script>
</body>
</html>






