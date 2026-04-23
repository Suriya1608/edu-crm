<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TCN Softphone</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        html,body{width:100%;height:100%;overflow:hidden;font-family:'Plus Jakarta Sans',sans-serif;background:#fff;}
        body{display:flex;flex-direction:column;}

        /* Header */
        .sp-hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#6366f1;color:#fff;flex-shrink:0;}
        .sp-hdr-title{display:flex;align-items:center;gap:7px;font-weight:700;font-size:13px;}
        .sp-min-btn{background:none;border:none;cursor:pointer;color:rgba(255,255,255,.9);display:flex;align-items:center;padding:2px;line-height:1;}

        /* Status bar */
        .sp-status{display:flex;align-items:center;gap:8px;padding:7px 14px;border-bottom:1px solid #e2e8f0;flex-shrink:0;}
        .sp-dot{width:9px;height:9px;border-radius:50%;background:#64748b;flex-shrink:0;transition:background .25s;}
        .sp-status-txt{font-size:12px;font-weight:600;color:#64748b;transition:color .25s;}

        /* Phone display */
        .sp-phone{padding:8px 14px 4px;font-size:21px;font-weight:800;color:#0f172a;letter-spacing:1.2px;min-height:46px;font-variant-numeric:tabular-nums;word-break:break-all;}
        .sp-phone.empty{color:#94a3b8;}

        /* Dial pad */
        .sp-dp{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;padding:4px 14px 8px;}
        .sp-key{height:42px;border:none;border-radius:9px;background:#f8fafc;font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:700;color:#0f172a;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .1s;}
        .sp-key:hover{background:#e2e8f0;}
        .sp-key:active{background:#ede9fe;color:#6366f1;}
        .sp-back{grid-column:1/-1;height:34px;border:none;border-radius:9px;background:#f8fafc;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;transition:background .1s;}
        .sp-back:hover{background:#e2e8f0;}

        /* Call button */
        .sp-pre-actions{padding:0 14px 8px;}
        .sp-call-btn{width:100%;height:42px;border:none;border-radius:9px;background:#10b981;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .15s;}
        .sp-call-btn:disabled{opacity:.45;cursor:not-allowed;}

        /* In-call panel */
        .sp-incall{display:none;flex-direction:column;padding:8px 14px 10px;gap:8px;}
        .sp-timer{text-align:center;font-size:30px;font-weight:800;font-variant-numeric:tabular-nums;color:#0f172a;}
        .sp-call-lbl{text-align:center;font-size:12px;color:#64748b;}
        .sp-incall-btns{display:flex;gap:8px;}
        .sp-ibtn{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;border-radius:9px;padding:9px 0;border:1px solid #e2e8f0;background:#f8fafc;color:#0f172a;font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;font-size:11px;cursor:pointer;transition:background .1s;}
        .sp-ibtn:hover{background:#e2e8f0;}
        .sp-ibtn.danger{background:#ef4444;border-color:#ef4444;color:#fff;}
        .sp-ibtn.muted{background:#fee2e2;border-color:#ef4444;color:#ef4444;}
        .sp-ibtn.held{background:#fef3c7;border-color:#f59e0b;color:#b45309;}
        /* DTMF in-call keypad */
        .sp-dtmf-toggle{width:100%;background:none;border:none;color:#64748b;font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:600;cursor:pointer;padding:2px 0;display:flex;align-items:center;justify-content:center;gap:4px;}
        .sp-dtmf-pad{display:none;grid-template-columns:repeat(3,1fr);gap:4px;padding:4px 0;}
        .sp-dtmf-pad.open{display:grid;}
        .sp-dkey{height:34px;border:none;border-radius:7px;background:#f1f5f9;font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:700;color:#0f172a;cursor:pointer;transition:background .1s;}
        .sp-dkey:hover{background:#e2e8f0;}
        .sp-dkey:active{background:#ede9fe;color:#6366f1;}

        /* Agent controls */
        .sp-agent{display:flex;gap:8px;padding:8px 14px 12px;border-top:1px solid #e2e8f0;flex-shrink:0;}
        .sp-abtn{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;border-radius:9px;padding:7px 0;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;font-size:11px;cursor:pointer;transition:background .1s;}
        .sp-abtn:hover{background:#e2e8f0;}

        /* Not-configured message */
        .sp-uncfg{display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:20px;text-align:center;gap:10px;}
        .sp-uncfg .material-icons{font-size:36px;color:#94a3b8;}
        .sp-uncfg p{font-size:12px;color:#64748b;font-weight:600;}

        @keyframes sp-pulse{0%,100%{opacity:1}50%{opacity:.5}}

        /* Incoming call banner */
        #spIncoming{flex-shrink:0;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;padding:12px 14px;}
        #spIncoming .sp-inc-label{font-size:11px;font-weight:600;opacity:.75;margin-bottom:3px;letter-spacing:.5px;text-transform:uppercase;}
        #spIncoming .sp-inc-phone{font-size:18px;font-weight:800;letter-spacing:.8px;margin-bottom:10px;}
        #spIncoming .sp-inc-btns{display:flex;gap:8px;}
        .sp-inc-btn{flex:1;height:38px;border:none;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:opacity .15s;}
        .sp-inc-btn:hover{opacity:.88;}
        .sp-inc-btn.accept{background:#10b981;color:#fff;}
        .sp-inc-btn.reject{background:#ef4444;color:#fff;}
        @keyframes sp-ring-pulse{0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.55)}70%{box-shadow:0 0 0 8px rgba(99,102,241,0)}}
        #spIncoming.ringing{animation:sp-ring-pulse 1.2s ease-in-out infinite;}
    </style>
</head>
<body>

{{-- Header --}}
<div class="sp-hdr">
    <div class="sp-hdr-title">
        <span class="material-icons" style="font-size:17px;">phone</span>
        TCN Softphone
    </div>
    <button class="sp-min-btn" id="spMinBtn" title="Minimize">
        <span class="material-icons" style="font-size:17px;" id="spMinIcon">remove</span>
    </button>
</div>

{{-- Incoming Call Banner (hidden until an invite arrives) --}}
<div id="spIncoming" style="display:none;">
    <div class="sp-inc-label">Incoming Call</div>
    <div class="sp-inc-phone" id="spIncomingPhone">Unknown</div>
    <div class="sp-inc-btns">
        <button class="sp-inc-btn accept" id="spAcceptBtn">
            <span class="material-icons" style="font-size:16px;">call</span> Accept
        </button>
        <button class="sp-inc-btn reject" id="spRejectBtn">
            <span class="material-icons" style="font-size:16px;">call_end</span> Reject
        </button>
    </div>
</div>

{{-- Status --}}
<div class="sp-status">
    <span class="sp-dot" id="spDot"></span>
    <span class="sp-status-txt" id="spStatusTxt">Connecting&hellip;</span>
</div>

{{-- Phone display --}}
<div class="sp-phone empty" id="spPhone">&mdash;</div>

{{-- Dial pad + call button --}}
<div id="spDialSec">
    <div class="sp-dp" id="spDp"></div>
    <div class="sp-pre-actions">
        <button class="sp-call-btn" id="spCallBtn" disabled>
            <span class="material-icons" style="font-size:17px;">call</span> Call
        </button>
    </div>
</div>

{{-- In-call panel --}}
<div class="sp-incall" id="spInCall">
    <div class="sp-timer" id="spTimer">0:00</div>
    <div class="sp-call-lbl" id="spCallLbl"></div>
    <div class="sp-incall-btns">
        <button class="sp-ibtn" id="spMuteBtn">
            <span class="material-icons" style="font-size:21px;">mic</span>Mute
        </button>
        <button class="sp-ibtn" id="spHoldBtn">
            <span class="material-icons" style="font-size:21px;" id="spHoldIco">pause_circle</span>
            <span id="spHoldLbl">Hold</span>
        </button>
        <button class="sp-ibtn danger" id="spHangupBtn">
            <span class="material-icons" style="font-size:21px;">call_end</span>End
        </button>
    </div>
    {{-- DTMF keypad (shown during call) --}}
    <button class="sp-dtmf-toggle" id="spDtmfToggle">
        <span class="material-icons" style="font-size:14px;">dialpad</span> Keypad
    </button>
    <div class="sp-dtmf-pad" id="spDtmfPad"></div>
</div>

{{-- Agent controls --}}
<div class="sp-agent" id="spAgent">
    <button class="sp-abtn" id="spPauseBtn">
        <span class="material-icons" style="font-size:17px;" id="spPauseIco">pause</span>
        <span id="spPauseLbl">Pause</span>
    </button>
    <button class="sp-abtn" id="spLogoutBtn">
        <span class="material-icons" style="font-size:17px;">logout</span>Logout
    </button>
</div>

{{-- Not-configured state --}}
<div class="sp-uncfg" id="spUncfg">
    <span class="material-icons">phone_disabled</span>
    <p>TCN not configured.<br>Contact your admin.</p>
</div>

{{-- 419 handler (same-origin fetch interceptor) --}}
<script>
(function () {
    var _orig = window.fetch;
    window.fetch = function (input, init) {
        init = Object.assign({}, init);
        init.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, init.headers);
        return _orig.call(window, input, init);
    };
})();
</script>

{{-- TCN Service singleton --}}
<script src="{{ asset('js/tcn-service.js') }}"></script>

{{-- Softphone UI + logic --}}
<script>
(function () {
    'use strict';

    // ── Singleton guard ───────────────────────────────────────────
    // window.sipInitialized persists for the iframe's lifetime.
    // The iframe is kept alive by data-turbo-permanent on the parent,
    // so this flag survives all Turbo navigations — SIP only inits once.
    if (window.sipInitialized) {
        console.log('[SP] SIP already initialized — skipping.');
        return;
    }
    window.sipInitialized = true;

    // ── State ────────────────────────────────────────────────────
    var _state        = 'connecting';
    var _phone        = '';
    var _leadId       = null;    // set by parent via CALL message
    var _muted        = false;
    var _onHold       = false;
    var _paused       = false;
    var _secs         = 0;
    var _timer        = null;
    var _min          = false;   // minimized?
    var _dtmfOpen     = false;
    var _autoAnswered = false;   // true when the current call was an inbound auto-answer

    // ── DOM ──────────────────────────────────────────────────────
    function g(id) { return document.getElementById(id); }
    var D = {
        dot:     g('spDot'),     status: g('spStatusTxt'),
        phone:   g('spPhone'),   dialSec: g('spDialSec'),
        dp:      g('spDp'),      callBtn: g('spCallBtn'),
        inCall:  g('spInCall'),  timer:   g('spTimer'),
        callLbl: g('spCallLbl'),
        muteBtn: g('spMuteBtn'),
        holdBtn: g('spHoldBtn'), holdIco: g('spHoldIco'), holdLbl: g('spHoldLbl'),
        hangupBtn: g('spHangupBtn'),
        dtmfToggle: g('spDtmfToggle'), dtmfPad: g('spDtmfPad'),
        agent:   g('spAgent'),
        pauseBtn: g('spPauseBtn'), pauseIco: g('spPauseIco'), pauseLbl: g('spPauseLbl'),
        logoutBtn: g('spLogoutBtn'),
        minBtn:  g('spMinBtn'), minIco: g('spMinIcon'),
        uncfg:   g('spUncfg'),
        // Incoming call
        incoming:      g('spIncoming'),
        incomingPhone: g('spIncomingPhone'),
        acceptBtn:     g('spAcceptBtn'),
        rejectBtn:     g('spRejectBtn'),
    };

    var COLORS = { connecting:'#64748b', ready:'#10b981', paused:'#f59e0b', calling:'#6366f1', 'on-call':'#ef4444', error:'#ef4444' };
    var LABELS = { connecting:'Connecting\u2026', ready:'Ready', paused:'Paused', calling:'Calling\u2026', 'on-call':'On Call', error:'Error' };

    // ── Render ───────────────────────────────────────────────────
    function render() {
        var c = COLORS[_state] || '#64748b';
        D.dot.style.background   = c;
        D.status.style.color     = c;
        D.status.textContent     = LABELS[_state] || _state;

        var inCall = (_state === 'calling' || _state === 'on-call');
        D.dialSec.style.display  = inCall ? 'none'  : 'block';
        D.inCall.style.display   = inCall ? 'flex'  : 'none';
        D.agent.style.display    = inCall ? 'none'  : 'flex';

        var canCall = (_state === 'ready' && _phone.length >= 5);
        D.callBtn.disabled      = !canCall;
        D.callBtn.style.opacity = canCall ? '1' : '0.45';
        if (inCall) D.callLbl.textContent = _phone || '';

        D.phone.textContent = _phone || '\u2014';
        D.phone.className   = 'sp-phone' + (_phone ? '' : ' empty');

        // Hold button appearance
        if (_onHold) {
            D.holdIco.textContent  = 'play_circle';
            D.holdLbl.textContent  = 'Resume';
            D.holdBtn.className    = 'sp-ibtn held';
        } else {
            D.holdIco.textContent  = 'pause_circle';
            D.holdLbl.textContent  = 'Hold';
            D.holdBtn.className    = 'sp-ibtn';
        }

        // Pause button appearance
        if (_paused) {
            D.pauseIco.textContent = 'play_arrow';
            D.pauseLbl.textContent = 'Resume';
            D.pauseBtn.style.cssText = 'background:#f59e0b;border-color:#f59e0b;color:#fff;';
        } else {
            D.pauseIco.textContent = 'pause';
            D.pauseLbl.textContent = 'Pause';
            D.pauseBtn.style.cssText = '';
        }

        // Pulsing tab icon while calling
        D.dot.style.animation = (_state === 'calling') ? 'sp-pulse 1s ease-in-out infinite' : '';
    }

    function setState(s) { _state = s; render(); }
    function setPhone(p) { _phone = String(p || '').replace(/\s+/g, ''); render(); }

    // ── Build dial pad ───────────────────────────────────────────
    ['1','2','3','4','5','6','7','8','9','*','0','#'].forEach(function (k) {
        var b = document.createElement('button');
        b.className = 'sp-key';
        b.textContent = k;
        b.addEventListener('click', function () {
            if (_state === 'calling' || _state === 'on-call') return;
            _phone += k; render();
        });
        D.dp.appendChild(b);
    });
    var bk = document.createElement('button');
    bk.className = 'sp-back';
    bk.innerHTML = '<span class="material-icons" style="font-size:17px;">backspace</span>';
    bk.addEventListener('click', function () {
        if (_state === 'calling' || _state === 'on-call') return;
        _phone = _phone.slice(0, -1); render();
    });
    D.dp.appendChild(bk);

    // ── Build DTMF in-call keypad ─────────────────────────────────
    ['1','2','3','4','5','6','7','8','9','*','0','#'].forEach(function (k) {
        var b = document.createElement('button');
        b.className = 'sp-dkey';
        b.textContent = k;
        b.addEventListener('click', function () {
            if (window.TCN && window.TCN._callActive) window.TCN.dtmf(k);
        });
        D.dtmfPad.appendChild(b);
    });

    // ── DTMF keypad toggle ────────────────────────────────────────
    D.dtmfToggle.addEventListener('click', function () {
        _dtmfOpen = !_dtmfOpen;
        D.dtmfPad.className = 'sp-dtmf-pad' + (_dtmfOpen ? ' open' : '');
        D.dtmfToggle.innerHTML = '<span class="material-icons" style="font-size:14px;">dialpad</span> ' + (_dtmfOpen ? 'Hide Keypad' : 'Keypad');
    });

    // ── Keyboard input ───────────────────────────────────────────
    window.addEventListener('keydown', function (e) {
        if (_state === 'calling' || _state === 'on-call') return;
        if (e.metaKey || e.ctrlKey || e.altKey) return;

        if (/^[0-9*#]$/.test(e.key)) {
            _phone += e.key; render();
        } else if (e.key === '+' && _phone.length === 0) {
            _phone = '+'; render();
        } else if (e.key === 'Backspace') {
            _phone = _phone.slice(0, -1); render();
        } else if (e.key === 'Enter') {
            handleCall();
        }
    });

    // ── Timer ────────────────────────────────────────────────────
    function startTimer() {
        _secs = 0; stopTimer(); tick();
        _timer = setInterval(tick, 1000);
    }
    function stopTimer() { if (_timer) { clearInterval(_timer); _timer = null; } }
    function tick() {
        _secs++;
        var m = Math.floor(_secs / 60), s = _secs % 60;
        D.timer.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    }

    // ── Ringtone (Web Audio API) ─────────────────────────────────
    var _ringCtx = null;
    var _ringInterval = null;

    function _startRingtone() {
        _stopRingtone();
        try {
            _ringCtx = new (window.AudioContext || window.webkitAudioContext)();
            function _beep() {
                if (!_ringCtx) return;
                var osc = _ringCtx.createOscillator();
                var gain = _ringCtx.createGain();
                osc.connect(gain);
                gain.connect(_ringCtx.destination);
                osc.type = 'sine';
                osc.frequency.value = 480;
                gain.gain.setValueAtTime(0.25, _ringCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, _ringCtx.currentTime + 0.4);
                osc.start(_ringCtx.currentTime);
                osc.stop(_ringCtx.currentTime + 0.45);
            }
            _beep();
            _ringInterval = setInterval(_beep, 1800);
        } catch (_) {}
    }

    function _stopRingtone() {
        if (_ringInterval) { clearInterval(_ringInterval); _ringInterval = null; }
        if (_ringCtx) { try { _ringCtx.close(); } catch (_) {} _ringCtx = null; }
    }

    // ── Incoming call helpers ────────────────────────────────────
    function _showIncoming(phone) {
        D.incomingPhone.textContent = phone || 'Unknown';
        D.incoming.style.display = 'block';
        D.incoming.classList.add('ringing');
        _startRingtone();
    }

    function _hideIncoming() {
        _stopRingtone();
        D.incoming.style.display = 'none';
        D.incoming.classList.remove('ringing');
    }

    // ── postMessage to parent ────────────────────────────────────
    // Works in both modes:
    //   popup → use window.opener (parent page that called window.open)
    //   iframe → use window.parent (legacy / fallback)
    function toParent(msg) {
        try {
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage(msg, '*');
            } else {
                window.parent.postMessage(msg, '*');
            }
        } catch (_) {}
    }

    // ── TCN events → forward to parent ──────────────────────────
    window.addEventListener('tcn:ready', function () {
        _paused = false; setState('ready');
        toParent({ type: 'TCN_READY' });
    });
    window.addEventListener('tcn:callStarted', function (e) {
        var d = e.detail || {};
        if (d.phone) setPhone(d.phone);
        // Reset timer display to 0:00 — do NOT start the interval here.
        // Timer must only run once the customer answers (tcn:callAnswered).
        stopTimer(); _secs = 0; D.timer.textContent = '0:00';
        setState('calling');
        // Include incoming flag so the parent call bar can show "Auto-Answered"
        var wasAutoAnswered = _autoAnswered;
        _autoAnswered = false;   // reset immediately after reading
        toParent({ type: 'TCN_CALL_STARTED', phone: d.phone || _phone, callLogId: d.callLogId, incoming: wasAutoAnswered });
    });
    window.addEventListener('tcn:callAnswered', function (e) {
        var d = e.detail || {};
        // Start timer only when customer answers — not on dial-out.
        startTimer();
        setState('on-call');
        toParent({ type: 'TCN_CALL_ANSWERED', phone: _phone, callLogId: d.callLogId });
    });
    window.addEventListener('tcn:callEnded', function (e) {
        var d = e.detail || {};
        stopTimer(); _muted = false; _onHold = false; _dtmfOpen = false;
        _hideIncoming();   // dismiss any lingering incoming banner
        resetMute();
        D.dtmfPad.className = 'sp-dtmf-pad';
        D.dtmfToggle.innerHTML = '<span class="material-icons" style="font-size:14px;">dialpad</span> Keypad';
        setState(_paused ? 'paused' : 'ready');
        toParent({ type: 'TCN_CALL_ENDED', phone: _phone, callLogId: d.callLogId, duration: d.duration, status: d.status || 'completed' });
    });
    window.addEventListener('tcn:sipDropped', function () {
        if (_state !== 'calling' && _state !== 'on-call') setState('connecting');
        toParent({ type: 'TCN_SIP_DROPPED' });
    });
    window.addEventListener('tcn:loggedOut', function () {
        // Reset flags so the next explicit START_SIP is allowed
        window.sipInitialized = false;
        window._sipBooted = false;
        stopTimer(); setState('connecting');
        toParent({ type: 'TCN_LOGGED_OUT' });
    });
    window.addEventListener('tcn:error', function (e) {
        var msg = (e.detail && e.detail.message) || 'Unknown error';
        if (_state !== 'calling' && _state !== 'on-call' && _state !== 'ready') setState('error');
        toParent({ type: 'TCN_ERROR', message: msg });
    });

    // ── Receive commands from parent ─────────────────────────────
    window.addEventListener('message', function (ev) {
        var d = ev.data;
        if (!d || typeof d !== 'object') return;
        if (d.type === 'CALL')            { if (d.phone) setPhone(d.phone); _leadId = d.leadId || null; handleCall(); }
        if (d.type === 'HANGUP')          { handleHangup(); }
        if (d.type === 'MUTE')            { toggleMute(); }
        if (d.type === 'HOLD')            { toggleHold(); }
        if (d.type === 'DTMF')            { if (window.TCN && d.digit) window.TCN.dtmf(d.digit); }
        if (d.type === 'SET_PHONE')       { setPhone(d.phone || ''); }
        if (d.type === 'LOGOUT')          { handleLogout(); }
        if (d.type === 'LOGOUT_SILENT')   { handleLogoutSilent(); }
        if (d.type === 'START_SIP')       { bootSip(); }
        if (d.type === 'ACCEPT_INCOMING') {
            _autoAnswered = true;
            _hideIncoming();
            if (window.TCN && window.TCN.acceptIncomingCall) window.TCN.acceptIncomingCall();
        }
        if (d.type === 'REJECT_INCOMING') {
            _hideIncoming();
            if (window.TCN && window.TCN.rejectIncomingCall) window.TCN.rejectIncomingCall();
        }
    });

    // ── Call actions ─────────────────────────────────────────────
    function handleCall() {
        // Block only when already in a call — not on 'connecting'.
        // TcnService.call() handles re-initialization internally, so
        // we must not gate on _state === 'ready' here.
        if (_state === 'calling' || _state === 'on-call') return;
        if (_phone.length < 5) return;
        var leadId = _leadId;   // capture before async operations
        if (window.TcnService) {
            window.TcnService.call(_phone, leadId).catch(function (e) {
                console.error('[SP] call failed:', e.message);
                setState('error');
            });
        } else if (window.TCN && window.TCN._loggedIn) {
            window.TCN.startCall(_phone, leadId).catch(function (e) {
                console.error('[SP] startCall failed:', e.message);
            });
        }
    }

    function handleHangup() {
        if (!window.TCN || !window.TCN._callActive) return;
        if (window.TCN._isIncoming) {
            window.TCN.endIncomingCall();
        } else {
            window.TCN.endCall();
        }
    }

    function toggleHold() {
        if (!window.TCN || !window.TCN._callActive) return;
        if (_onHold) {
            window.TCN.resume();
        } else {
            window.TCN.hold();
        }
    }

    // Reflect TCN hold/resume events in the UI and notify the parent call bar
    window.addEventListener('tcn:onHold', function () {
        _onHold = true;
        render();
        toParent({ type: 'TCN_ON_HOLD' });
    });
    window.addEventListener('tcn:offHold', function () {
        _onHold = false;
        render();
        toParent({ type: 'TCN_OFF_HOLD' });
    });

    // ── Incoming call events ─────────────────────────────────────
    //
    // MANUAL-ANSWER MODE: Show the incoming call banner and notify the parent
    // page. The agent must click Accept or Reject — no auto-answer.
    window.addEventListener('tcn:incomingCall', function (e) {
        var phone = (e.detail && e.detail.phone) || null;
        _phone = phone;
        _autoAnswered = false;

        // Show the incoming banner inside the iframe (with ringtone)
        _showIncoming(phone || 'Incoming');

        // Notify the parent page so it can show its own incoming call popup
        toParent({ type: 'TCN_INCOMING_CALL', phone: phone || 'Incoming' });
    });

    // ANI resolved after initial detection (e.g., from approve-call or status poll)
    window.addEventListener('tcn:phoneResolved', function (e) {
        var phone = (e.detail && e.detail.phone) || null;
        if (!phone) return;
        _phone = phone;
        if (D.incomingPhone) D.incomingPhone.textContent = phone;
        render();
        toParent({ type: 'TCN_PHONE_RESOLVED', phone: phone });
    });

    window.addEventListener('tcn:incomingCallRejected', function () {
        _autoAnswered = false;
        _hideIncoming();
        toParent({
            type:      'TCN_INCOMING_REJECTED',
            phone:     _phone || null,
            callLogId: (window.TCN && window.TCN._activeLogId) ? window.TCN._activeLogId : null,
        });
        _phone = '';   // clear so next incoming starts fresh
    });

    // Accept / Reject buttons kept for edge-case manual override
    // (e.g. when auto-accept fails and the banner is shown via _showIncoming)
    D.acceptBtn.addEventListener('click', function () {
        _hideIncoming();
        _autoAnswered = true;
        if (window.TCN && window.TCN.acceptIncomingCall) window.TCN.acceptIncomingCall();
    });

    D.rejectBtn.addEventListener('click', function () {
        _autoAnswered = false;
        _hideIncoming();
        if (window.TCN && window.TCN.rejectIncomingCall) window.TCN.rejectIncomingCall();
    });

    function toggleMute() {
        if (!window.TCN) return;
        _muted = !_muted;
        if (_muted) { window.TCN.mute(); renderMuted(); }
        else         { window.TCN.unmute(); resetMute(); }
    }
    function renderMuted() {
        D.muteBtn.innerHTML = '<span class="material-icons" style="font-size:21px;">mic_off</span>Unmute';
        D.muteBtn.className = 'sp-ibtn muted';
    }
    function resetMute() {
        D.muteBtn.innerHTML = '<span class="material-icons" style="font-size:21px;">mic</span>Mute';
        D.muteBtn.className = 'sp-ibtn';
    }

    // ── Pause / Resume ───────────────────────────────────────────
    function togglePause() {
        if (_state === 'calling' || _state === 'on-call') return;
        var newPaused = !_paused;
        var status    = newPaused ? 'UNAVAILABLE' : 'READY';
        D.pauseBtn.disabled = true;
        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch('/tcn/set-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '' },
            body: JSON.stringify({ status: status }),
        }).then(function (r) { return r.json(); })
          .then(function () { _paused = newPaused; setState(_paused ? 'paused' : 'ready'); })
          .catch(function () { _paused = newPaused; setState(_paused ? 'paused' : 'ready'); })
          .finally(function () { D.pauseBtn.disabled = false; });
    }

    // ── Logout (with confirm — used by in-panel Logout button) ──────
    function handleLogout() {
        if (!confirm('Log out of TCN softphone?')) return;
        handleLogoutSilent();
    }

    // ── Logout (no confirm — used by header Stop button via LOGOUT_SILENT) ──
    function handleLogoutSilent() {
        window.sipInitialized = false;
        window._sipBooted = false;
        if (window.TcnService) window.TcnService.logout();
        else if (window.TCN)   window.TCN.logout();
        setState('connecting');
        toParent({ type: 'TCN_LOGGED_OUT' });
    }

    // ── Minimize / Expand ────────────────────────────────────────
    D.minBtn.addEventListener('click', function () {
        _min = !_min;
        D.minIco.textContent = _min ? 'add' : 'remove';
        toParent({ type: _min ? 'SP_MINIMIZE' : 'SP_EXPAND' });
    });

    // ── Button events ────────────────────────────────────────────
    D.callBtn.addEventListener('click', handleCall);
    D.hangupBtn.addEventListener('click', handleHangup);
    D.holdBtn.addEventListener('click', toggleHold);
    D.muteBtn.addEventListener('click', toggleMute);
    D.pauseBtn.addEventListener('click', togglePause);
    D.logoutBtn.addEventListener('click', handleLogout);

    // ── Initial render ───────────────────────────────────────────
    render();

    // ── Deferred SIP boot ─────────────────────────────────────────
    // SIP does NOT auto-start on iframe load by default. The parent sends
    // { type: 'START_SIP' } when the user clicks "Ready".  We also
    // self-boot here from localStorage so the iframe doesn't depend on the
    // parent postMessage arriving at exactly the right time (DOMContentLoaded
    // fires before the iframe HTTP response completes, so the message can be
    // lost on first click and after hard page reloads).
    // _sipBooted is a per-iframe-lifetime guard (complements sipInitialized).
    window._sipBooted = false;

    function bootSip() {
        if (window._sipBooted) return;
        window._sipBooted = true;
        console.log('[SP] Booting SIP on START_SIP command.');

        if (window.TcnService) {
            window.TcnService.init()
                .then(function (ok) {
                    if (!ok) {
                        // TCN not configured for this agent — show unconfigured state.
                        window.sipInitialized = false;
                        window._sipBooted = false;
                        D.dialSec.style.display = 'none';
                        D.agent.style.display   = 'none';
                        D.uncfg.style.display   = 'flex';
                        setState('error');
                    }
                })
                .catch(function (e) {
                    console.error('[SP] TcnService.init failed:', e);
                    window.sipInitialized = false;
                    window._sipBooted = false;
                    setState('error');
                });
        } else {
            window.sipInitialized = false;
            window._sipBooted = false;
            D.dialSec.style.display = 'none';
            D.agent.style.display   = 'none';
            D.uncfg.style.display   = 'flex';
        }
    }

    // ── Self-boot from localStorage ───────────────────────────────
    // If the parent persisted tcn_sip_active=1 (user previously clicked Ready),
    // boot SIP immediately on iframe load — no postMessage from parent required.
    // This fixes the timing race where START_SIP is sent before the iframe is
    // ready and the message is silently dropped.
    try {
        if (localStorage.getItem('tcn_sip_active') === '1') {
            bootSip();
        }
    } catch (_) {}

})();
</script>
</body>
</html>
