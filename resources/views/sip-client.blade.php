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
        .sp-ibtn.held{background:#fef3c7;border-color:#f59e0b;color:#b45309;}
        .sp-dtmf-toggle{width:100%;background:none;border:none;color:#64748b;font-family:'Manrope',sans-serif;font-size:11px;font-weight:600;cursor:pointer;padding:2px 0;display:flex;align-items:center;justify-content:center;gap:4px;}
        .sp-dtmf-pad{display:none;grid-template-columns:repeat(3,1fr);gap:4px;padding:4px 0;}
        .sp-dtmf-pad.open{display:grid;}
        .sp-dkey{height:34px;border:none;border-radius:7px;background:#f1f5f9;font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;color:#0f172a;cursor:pointer;transition:background .1s;}
        .sp-dkey:hover{background:#e2e8f0;}
        .sp-dkey:active{background:#dbeafe;color:#137fec;}

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
        <button class="sp-ibtn" id="spHoldBtn">
            <span class="material-icons" style="font-size:21px;" id="spHoldIco">pause_circle</span>
            <span id="spHoldLbl">Hold</span>
        </button>
        <button class="sp-ibtn danger" id="spHangupBtn">
            <span class="material-icons" style="font-size:21px;">call_end</span>End
        </button>
    </div>
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

{{-- Hidden audio output â€” SIP.js attaches remote WebRTC stream here --}}
<audio id="tcn-remote-audio" autoplay style="display:none"></audio>

{{-- Global 419 handler: redirect to login on session expiry --}}
<script>
(function () {
    var _orig = window.fetch;
    window.fetch = function (input, init) {
        init = Object.assign({}, init);
        init.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, init.headers);
        return _orig.call(window, input, init).then(function (res) {
            if (res.status === 419) { window.location.href = '/login'; }
            return res;
        });
    };
})();
</script>

{{-- tcn-service.js bootstraps the token + dynamically loads tcn-softphone.js --}}
<script src="{{ asset('js/tcn-service.js') }}"></script>

{{--
    Softphone UI controller
    â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Architecture:
      â€¢ This page runs inside <iframe id="sipClientFrame"> in the CRM layout.
      â€¢ tcn-softphone.js (loaded by TcnService.init()) holds the SIP session.
      â€¢ The SIP session lives only in runtime memory for this page/iframe.
      â€¢ On reload, the softphone starts a fresh secure session.
      â€¢ postMessage bridge:
          Parent â†’ iframe : CALL | HANGUP | MUTE | HOLD | DTMF | SET_PHONE | LOGOUT | PING
          Iframe â†’ parent : TCN_READY | TCN_CALL_STARTED | TCN_CALL_ANSWERED |
                            TCN_CALL_ENDED | TCN_STATE_SYNC | TCN_SIP_DROPPED |
                            TCN_LOGGED_OUT | TCN_ERROR | SP_MINIMIZE | SP_EXPAND
    â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
--}}
<script>
(function () {
    'use strict';

    // â”€â”€ Singleton guard â€” prevent double-execution if script runs twice â”€â”€â”€â”€
    if (window.__sipClientInit) return;
    window.__sipClientInit = true;

    // â”€â”€ State â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    var _state     = 'connecting';
    var _tcnStatus = '';
    var _phone     = '';
    var _muted     = false;
    var _onHold    = false;
    var _paused    = false;
    var _secs      = 0;
    var _timer     = null;
    var _min       = false;
    var _dtmfOpen  = false;

    // â”€â”€ DOM helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function g(id) { return document.getElementById(id); }
    var D = {
        dot:      g('spDot'),      status:  g('spStatusTxt'),
        phone:    g('spPhone'),    dialSec: g('spDialSec'),
        dp:       g('spDp'),       callBtn: g('spCallBtn'),
        inCall:   g('spInCall'),   timer:   g('spTimer'),
        callLbl:  g('spCallLbl'),
        muteBtn:  g('spMuteBtn'),
        holdBtn:  g('spHoldBtn'),  holdIco: g('spHoldIco'), holdLbl: g('spHoldLbl'),
        hangupBtn:g('spHangupBtn'),
        dtmfToggle:g('spDtmfToggle'), dtmfPad:g('spDtmfPad'),
        agent:    g('spAgent'),
        pauseBtn: g('spPauseBtn'), pauseIco:g('spPauseIco'), pauseLbl:g('spPauseLbl'),
        logoutBtn:g('spLogoutBtn'),
        minBtn:   g('spMinBtn'),   minIco:  g('spMinIcon'),
        uncfg:    g('spUncfg'),
    };

    var COLORS = {
        connecting:'#64748b', ready:'#10b981', paused:'#f59e0b',
        calling:'#137fec', 'on-call':'#ef4444', ending:'#f59e0b', error:'#ef4444',
    };
    var LABELS = {
        connecting:'Connecting\u2026', ready:'Ready', paused:'Paused',
        calling:'Ringing\u2026', 'on-call':'On Call', ending:'Ending\u2026', error:'Error',
    };

    // â”€â”€ Render â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function render() {
        var c    = COLORS[_state] || '#64748b';
        D.dot.style.background  = c;
        D.status.style.color    = c;
        D.status.textContent    = LABELS[_state] || _state;

        var inCall = (_state === 'calling' || _state === 'on-call' || _state === 'ending');
        D.dialSec.style.display = inCall ? 'none'  : 'block';
        D.inCall.style.display  = inCall ? 'flex'  : 'none';
        D.agent.style.display   = inCall ? 'none'  : 'flex';

        var canCall = (_state === 'ready' && _phone.length >= 5);
        D.callBtn.disabled      = !canCall;
        D.callBtn.style.opacity = canCall ? '1' : '0.45';
        if (inCall) D.callLbl.textContent = _phone || '';

        if (_state === 'calling') {
            D.timer.textContent  = (_tcnStatus === 'PEERED') ? 'Connecting\u2026' : 'Ringing\u2026';
            D.status.textContent = (_tcnStatus === 'PEERED') ? 'Connecting\u2026' : (LABELS['calling'] || 'Ringing\u2026');
        } else if (_state === 'ending') {
            D.timer.textContent = 'Ending\u2026';
        }

        var ending = (_state === 'ending');
        D.hangupBtn.disabled = ending;
        D.muteBtn.disabled   = ending;
        D.holdBtn.disabled   = ending;
        D.hangupBtn.style.opacity = ending ? '0.45' : '1';

        D.phone.textContent = _phone || '\u2014';
        D.phone.className   = 'sp-phone' + (_phone ? '' : ' empty');

        if (_onHold) {
            D.holdIco.textContent = 'play_circle';
            D.holdLbl.textContent = 'Resume';
            D.holdBtn.className   = 'sp-ibtn held';
        } else {
            D.holdIco.textContent = 'pause_circle';
            D.holdLbl.textContent = 'Hold';
            D.holdBtn.className   = 'sp-ibtn';
        }

        if (_paused) {
            D.pauseIco.textContent = 'play_arrow';
            D.pauseLbl.textContent = 'Resume';
            D.pauseBtn.style.cssText = 'background:#f59e0b;border-color:#f59e0b;color:#fff;';
        } else {
            D.pauseIco.textContent = 'pause';
            D.pauseLbl.textContent = 'Pause';
            D.pauseBtn.style.cssText = '';
        }

        D.dot.style.animation = (_state === 'calling') ? 'sp-pulse 1s ease-in-out infinite' : '';
    }

    function setState(s) { _state = s; render(); }
    function setPhone(p) { _phone = String(p || '').replace(/\s+/g, ''); render(); }

    // â”€â”€ Snapshot & parent sync â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function buildSnapshot() {
        return {
            type:              'TCN_STATE_SYNC',
            state:             _state,
            phone:             _phone,
            paused:            _paused,
            muted:             _muted,
            onHold:            _onHold,
            minimized:         _min,
            tcnStatus:         _tcnStatus,
            callActive:        !!(window.TCN && window.TCN._callActive),
            callLogId:         window.TCN ? (window.TCN._activeLogId || null) : null,
            callEstablishedAt: window.TCN ? (window.TCN._callEstablishedAt || null) : null,
        };
    }

    // postMessage to parent (works from both iframe and popup contexts).
    function toParent(msg) {
        try {
            var target = (window.parent && window.parent !== window) ? window.parent : (window.opener || null);
            if (target) target.postMessage(msg, '*');
        } catch (_) {}
    }

    function syncParent() { toParent(buildSnapshot()); }

    // â”€â”€ Build dial pad â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Build DTMF in-call keypad â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ['1','2','3','4','5','6','7','8','9','*','0','#'].forEach(function (k) {
        var b = document.createElement('button');
        b.className = 'sp-dkey';
        b.textContent = k;
        b.addEventListener('click', function () {
            if (window.TCN && window.TCN._callActive) window.TCN.dtmf(k);
        });
        D.dtmfPad.appendChild(b);
    });

    D.dtmfToggle.addEventListener('click', function () {
        _dtmfOpen = !_dtmfOpen;
        D.dtmfPad.className = 'sp-dtmf-pad' + (_dtmfOpen ? ' open' : '');
        D.dtmfToggle.innerHTML = '<span class="material-icons" style="font-size:14px;">dialpad</span> ' + (_dtmfOpen ? 'Hide Keypad' : 'Keypad');
    });

    // â”€â”€ Keyboard input â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    window.addEventListener('keydown', function (e) {
        if (_state === 'calling' || _state === 'on-call') return;
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        if (/^[0-9*#]$/.test(e.key))      { _phone += e.key; render(); }
        else if (e.key === '+' && _phone.length === 0) { _phone = '+'; render(); }
        else if (e.key === 'Backspace')    { _phone = _phone.slice(0, -1); render(); }
        else if (e.key === 'Enter')        { handleCall(); }
    });

    // â”€â”€ Timer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function startTimerFrom(offsetSecs) {
        _secs = offsetSecs || 0;
        stopTimer();
        tick();
        _timer = setInterval(tick, 1000);
    }
    function startTimer() { startTimerFrom(0); }
    function stopTimer() {
        if (_timer) { clearInterval(_timer); _timer = null; }
        D.timer.textContent = '0:00';
    }
    function tick() {
        _secs++;
        var m = Math.floor(_secs / 60), s = _secs % 60;
        D.timer.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    }

    // â”€â”€ TCN events â†’ forward to parent â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    window.addEventListener('tcn:ready', function () {
        _paused = false; setState('ready');
        toParent({ type: 'TCN_READY' });
        syncParent();
    });
    window.addEventListener('tcn:callStarted', function (e) {
        var d = e.detail || {};
        if (d.phone) setPhone(d.phone);
        _tcnStatus = 'OUTBOUND_LOCKED';
        stopTimer();
        setState('calling');
        toParent({ type: 'TCN_CALL_STARTED', phone: d.phone || _phone, callLogId: d.callLogId });
        syncParent();
    });
    window.addEventListener('tcn:callPeered', function () {
        _tcnStatus = 'PEERED';
        if (_state === 'calling') render();
        syncParent();
    });
    window.addEventListener('tcn:callAnswered', function (e) {
        var d = e.detail || {};
        _tcnStatus = 'INCALL';
        setState('on-call');
        var offset = (d.restored && window.TCN && window.TCN._callEstablishedAt)
            ? Math.round((Date.now() - window.TCN._callEstablishedAt) / 1000)
            : 0;
        startTimerFrom(offset);
        toParent({ type: 'TCN_CALL_ANSWERED', phone: _phone, callLogId: d.callLogId });
        syncParent();
    });
    window.addEventListener('tcn:callEnding', function () {
        stopTimer();
        setState('ending');
        syncParent();
    });
    window.addEventListener('tcn:callEnded', function (e) {
        var d = e.detail || {};
        _tcnStatus = ''; stopTimer(); _muted = false; _onHold = false; _dtmfOpen = false;
        resetMute();
        D.dtmfPad.className = 'sp-dtmf-pad';
        D.dtmfToggle.innerHTML = '<span class="material-icons" style="font-size:14px;">dialpad</span> Keypad';
        setState(_paused ? 'paused' : 'ready');
        toParent({ type: 'TCN_CALL_ENDED', phone: _phone, callLogId: d.callLogId, duration: d.duration, status: d.status || 'completed' });
        syncParent();
    });
    window.addEventListener('tcn:sipDropped', function () {
        if (_state !== 'calling' && _state !== 'on-call') setState('connecting');
        toParent({ type: 'TCN_SIP_DROPPED' });
        syncParent();
    });
    window.addEventListener('tcn:loggedOut', function () {
        stopTimer(); setState('connecting');
        toParent({ type: 'TCN_LOGGED_OUT' });
        syncParent();
    });
    window.addEventListener('tcn:error', function (e) {
        var msg = (e.detail && e.detail.message) || 'Unknown error';
        if (_state !== 'calling' && _state !== 'on-call' && _state !== 'ready') setState('error');
        toParent({ type: 'TCN_ERROR', message: msg });
        syncParent();
    });
    window.addEventListener('tcn:onHold',  function () { _onHold = true;  render(); syncParent(); });
    window.addEventListener('tcn:offHold', function () { _onHold = false; render(); syncParent(); });

    // â”€â”€ Receive commands from parent (postMessage) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    window.addEventListener('message', function (ev) {
        // Accept messages from the parent origin only (same-origin iframe).
        if (ev.origin !== window.location.origin) return;
        var d = ev.data;
        if (!d || typeof d !== 'object') return;

        switch (d.type) {
            case 'CALL':     if (d.phone) setPhone(d.phone); handleCall(); break;
            case 'HANGUP':   handleHangup(); break;
            case 'MUTE':     toggleMute(); break;
            case 'HOLD':     toggleHold(); break;
            case 'DTMF':     if (window.TCN && d.digit) window.TCN.dtmf(d.digit); break;
            case 'SET_PHONE':setPhone(d.phone || ''); syncParent(); break;
            case 'LOGOUT':   handleLogout(true); break;
            case 'PING':     toParent(buildSnapshot()); break;
        }
    });

    // â”€â”€ Call actions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function handleCall() {
        if (_state !== 'ready' || _phone.length < 5) return;
        if (window.TcnService) {
            window.TcnService.call(_phone).catch(function (e) {
                console.error('[SIP-CLIENT] call failed:', e.message);
                setState('error');
            });
        } else if (window.TCN && window.TCN._loggedIn) {
            window.TCN.startCall(_phone, null).catch(function (e) {
                console.error('[SIP-CLIENT] startCall failed:', e.message);
            });
        }
    }

    function handleHangup() {
        if (window.TCN && window.TCN._callActive) window.TCN.endCall();
    }

    function toggleHold() {
        if (!window.TCN || !window.TCN._callActive) return;
        if (_onHold) { window.TCN.resume(); } else { window.TCN.hold(); }
    }

    function toggleMute() {
        if (!window.TCN) return;
        _muted = !_muted;
        if (_muted) { window.TCN.mute(); renderMuted(); }
        else        { window.TCN.unmute(); resetMute(); }
    }
    function renderMuted() {
        D.muteBtn.innerHTML = '<span class="material-icons" style="font-size:21px;">mic_off</span>Unmute';
        D.muteBtn.className = 'sp-ibtn muted';
    }
    function resetMute() {
        D.muteBtn.innerHTML = '<span class="material-icons" style="font-size:21px;">mic</span>Mute';
        D.muteBtn.className = 'sp-ibtn';
    }

    // â”€â”€ Pause / Resume â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
        }).finally(function () {
            _paused = newPaused;
            setState(_paused ? 'paused' : 'ready');
            D.pauseBtn.disabled = false;
            syncParent();
        });
    }

    // â”€â”€ Logout â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // silent=true when triggered by parent LOGOUT command (no confirm dialog).
    function handleLogout(silent) {
        if (!silent && !confirm('Log out of TCN softphone?')) return;
        if (window.TcnService) window.TcnService.logout();
        else if (window.TCN)   window.TCN.logout();
        setState('connecting');
        toParent({ type: 'TCN_LOGGED_OUT' });
        syncParent();
    }

    // â”€â”€ Minimize / Expand â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    D.minBtn.addEventListener('click', function () {
        _min = !_min;
        D.minIco.textContent = _min ? 'add' : 'remove';
        toParent({ type: _min ? 'SP_MINIMIZE' : 'SP_EXPAND' });
        syncParent();
    });

    // â”€â”€ Button bindings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    D.callBtn.addEventListener('click', handleCall);
    D.hangupBtn.addEventListener('click', handleHangup);
    D.holdBtn.addEventListener('click', toggleHold);
    D.muteBtn.addEventListener('click', toggleMute);
    D.pauseBtn.addEventListener('click', togglePause);
    D.logoutBtn.addEventListener('click', function () { handleLogout(false); });

    // â”€â”€ Initial render â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    render();
    syncParent();

    // â”€â”€ Boot TcnService (loads tcn-softphone.js â†’ SIP login) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if (window.TcnService) {
        window.TcnService.init()
            .then(function (ok) {
                if (!ok) {
                    D.dialSec.style.display = 'none';
                    D.agent.style.display   = 'none';
                    D.uncfg.style.display   = 'flex';
                    setState('error');
                    return;
                }
                // Restore in-progress call state (if CRM page navigated mid-call).
                if (window.TCN && typeof window.TCN.resumeActiveCall === 'function') {
                    window.TCN.resumeActiveCall().catch(function (e) {
                        console.warn('[SIP-CLIENT] resumeActiveCall (non-fatal):', e);
                    });
                }
            })
            .catch(function (e) {
                console.error('[SIP-CLIENT] TcnService.init failed:', e);
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

