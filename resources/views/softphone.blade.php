<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TCN Softphone</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        html,body{width:100%;height:100%;overflow:hidden;font-family:'Manrope',sans-serif;background:#fff;}
        body{display:flex;flex-direction:column;}

        /* Header */
        .sp-hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#137fec;color:#fff;flex-shrink:0;}
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
        .sp-key{height:42px;border:none;border-radius:9px;background:#f8fafc;font-family:'Manrope',sans-serif;font-size:17px;font-weight:700;color:#0f172a;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .1s;}
        .sp-key:hover{background:#e2e8f0;}
        .sp-key:active{background:#dbeafe;color:#137fec;}
        .sp-back{grid-column:1/-1;height:34px;border:none;border-radius:9px;background:#f8fafc;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;transition:background .1s;}
        .sp-back:hover{background:#e2e8f0;}

        /* Call button */
        .sp-pre-actions{padding:0 14px 8px;}
        .sp-call-btn{width:100%;height:42px;border:none;border-radius:9px;background:#10b981;color:#fff;font-family:'Manrope',sans-serif;font-weight:700;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .15s;}
        .sp-call-btn:disabled{opacity:.45;cursor:not-allowed;}

        /* In-call panel */
        .sp-incall{display:none;flex-direction:column;padding:8px 14px 10px;gap:8px;}
        .sp-timer{text-align:center;font-size:30px;font-weight:800;font-variant-numeric:tabular-nums;color:#0f172a;}
        .sp-call-lbl{text-align:center;font-size:12px;color:#64748b;}
        .sp-incall-btns{display:flex;gap:8px;}
        .sp-ibtn{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;border-radius:9px;padding:9px 0;border:1px solid #e2e8f0;background:#f8fafc;color:#0f172a;font-family:'Manrope',sans-serif;font-weight:600;font-size:11px;cursor:pointer;transition:background .1s;}
        .sp-ibtn:hover{background:#e2e8f0;}
        .sp-ibtn.danger{background:#ef4444;border-color:#ef4444;color:#fff;}
        .sp-ibtn.muted{background:#fee2e2;border-color:#ef4444;color:#ef4444;}

        /* Agent controls */
        .sp-agent{display:flex;gap:8px;padding:8px 14px 12px;border-top:1px solid #e2e8f0;flex-shrink:0;}
        .sp-abtn{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;border-radius:9px;padding:7px 0;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-family:'Manrope',sans-serif;font-weight:600;font-size:11px;cursor:pointer;transition:background .1s;}
        .sp-abtn:hover{background:#e2e8f0;}

        /* Not-configured message */
        .sp-uncfg{display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:20px;text-align:center;gap:10px;}
        .sp-uncfg .material-icons{font-size:36px;color:#94a3b8;}
        .sp-uncfg p{font-size:12px;color:#64748b;font-weight:600;}

        @keyframes sp-pulse{0%,100%{opacity:1}50%{opacity:.5}}
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
        <button class="sp-ibtn danger" id="spHangupBtn">
            <span class="material-icons" style="font-size:21px;">call_end</span>End
        </button>
    </div>
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

    // ── Singleton guard ──────────────────────────────────────────
    if (window.__spInit) return;
    window.__spInit = true;

    // ── State ────────────────────────────────────────────────────
    var _state    = 'connecting';
    var _phone    = '';
    var _muted    = false;
    var _paused   = false;
    var _secs     = 0;
    var _timer    = null;
    var _min      = false;   // minimized?

    // ── DOM ──────────────────────────────────────────────────────
    function g(id) { return document.getElementById(id); }
    var D = {
        dot:     g('spDot'),     status: g('spStatusTxt'),
        phone:   g('spPhone'),   dialSec: g('spDialSec'),
        dp:      g('spDp'),      callBtn: g('spCallBtn'),
        inCall:  g('spInCall'),  timer:   g('spTimer'),
        callLbl: g('spCallLbl'),
        muteBtn: g('spMuteBtn'), hangupBtn: g('spHangupBtn'),
        agent:   g('spAgent'),
        pauseBtn: g('spPauseBtn'), pauseIco: g('spPauseIco'), pauseLbl: g('spPauseLbl'),
        logoutBtn: g('spLogoutBtn'),
        minBtn:  g('spMinBtn'), minIco: g('spMinIcon'),
        uncfg:   g('spUncfg'),
    };

    var COLORS = { connecting:'#64748b', ready:'#10b981', paused:'#f59e0b', calling:'#137fec', 'on-call':'#ef4444', error:'#ef4444' };
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
        startTimer(); setState('calling');
        toParent({ type: 'TCN_CALL_STARTED', phone: d.phone || _phone, callLogId: d.callLogId });
    });
    window.addEventListener('tcn:callAnswered', function (e) {
        var d = e.detail || {};
        setState('on-call');
        toParent({ type: 'TCN_CALL_ANSWERED', phone: _phone, callLogId: d.callLogId });
    });
    window.addEventListener('tcn:callEnded', function (e) {
        var d = e.detail || {};
        stopTimer(); _muted = false; resetMute();
        setState(_paused ? 'paused' : 'ready');
        toParent({ type: 'TCN_CALL_ENDED', phone: _phone, callLogId: d.callLogId, duration: d.duration, status: d.status || 'completed' });
    });
    window.addEventListener('tcn:sipDropped', function () {
        if (_state !== 'calling' && _state !== 'on-call') setState('connecting');
        toParent({ type: 'TCN_SIP_DROPPED' });
    });
    window.addEventListener('tcn:loggedOut', function () {
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
        if (d.type === 'CALL')     { if (d.phone) setPhone(d.phone); handleCall(); }
        if (d.type === 'HANGUP')   { handleHangup(); }
        if (d.type === 'MUTE')     { toggleMute(); }
        if (d.type === 'SET_PHONE'){ setPhone(d.phone || ''); }
        if (d.type === 'LOGOUT')   { handleLogout(); }
    });

    // ── Call actions ─────────────────────────────────────────────
    function handleCall() {
        if (_state !== 'ready' || _phone.length < 5) return;
        if (window.TcnService) {
            window.TcnService.call(_phone).catch(function (e) {
                console.error('[SP] call failed:', e.message);
                setState('error');
            });
        } else if (window.TCN && window.TCN._loggedIn) {
            window.TCN.startCall(_phone, null).catch(function (e) {
                console.error('[SP] startCall failed:', e.message);
            });
        }
    }

    function handleHangup() {
        if (window.TCN && window.TCN._callActive) window.TCN.endCall();
    }

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

    // ── Logout ───────────────────────────────────────────────────
    function handleLogout() {
        if (!confirm('Log out of TCN softphone?')) return;
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
    D.muteBtn.addEventListener('click', toggleMute);
    D.pauseBtn.addEventListener('click', togglePause);
    D.logoutBtn.addEventListener('click', handleLogout);

    // ── Initial render ───────────────────────────────────────────
    render();

    // ── Boot TCN ─────────────────────────────────────────────────
    if (window.TcnService) {
        window.TcnService.init()
            .then(function (ok) {
                if (!ok) {
                    D.dialSec.style.display = 'none';
                    D.agent.style.display   = 'none';
                    D.uncfg.style.display   = 'flex';
                    setState('error');
                }
            })
            .catch(function (e) {
                console.error('[SP] TcnService.init failed:', e);
                setState('error');
            });
    } else {
        D.dialSec.style.display = 'none';
        D.agent.style.display   = 'none';
        D.uncfg.style.display   = 'flex';
    }

})();
</script>
</body>
</html>
