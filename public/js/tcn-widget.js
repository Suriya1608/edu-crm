/**
 * tcn-widget.js — Floating softphone widget for TCN WebRTC integration
 *
 * Depends on: tcn-softphone.js (window.TCN), global-call.js (window.GC)
 * Material Icons and Manrope font must be loaded by the host page.
 *
 * Auto-initializes on DOMContentLoaded.
 * Exposes: window.TcnWidget = { setState, setPhone, expand, collapse }
 */
(function () {
    'use strict';

    // ─── Design tokens ───────────────────────────────────────────────────
    var C = {
        primary:  '#137fec',
        success:  '#10b981',
        warning:  '#f59e0b',
        danger:   '#ef4444',
        muted:    '#64748b',
        white:    '#ffffff',
        surface:  '#f8fafc',
        border:   '#e2e8f0',
        dark:     '#0f172a',
    };

    // ─── State ───────────────────────────────────────────────────────────
    var _state        = 'connecting';
    var _phone        = '';
    var _muted        = false;
    var _agentPaused  = false;
    var _expanded     = false;
    var _callSecs     = 0;
    var _callTimer    = null;

    // DOM refs populated by buildWidget()
    var W = {};

    // ─── Helper: create element with attributes ───────────────────────────
    function el(tag, attrs, html) {
        var e = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'class')    { e.className = attrs[k]; }
                else if (k === 'style') { e.style.cssText = attrs[k]; }
                else                  { e.setAttribute(k, attrs[k]); }
            });
        }
        if (html !== undefined) e.innerHTML = html;
        return e;
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    // ─── Build widget DOM ────────────────────────────────────────────────
    function buildWidget() {
        // ── Inject CSS ──────────────────────────────────────────────────
        var styleEl = document.createElement('style');
        styleEl.textContent = [
            '#tcnWidget{position:fixed;bottom:20px;right:20px;z-index:1065;font-family:"Manrope",sans-serif;}',
            '#tcnWidgetTab{width:52px;height:52px;border-radius:50%;background:' + C.muted + ';cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(0,0,0,.22);transition:background .25s;position:relative;user-select:none;}',
            '#tcnWidgetTab:hover{box-shadow:0 6px 24px rgba(0,0,0,.28);}',
            '#tcnWidgetDot{width:11px;height:11px;border-radius:50%;position:absolute;top:1px;right:1px;border:2px solid #fff;background:' + C.muted + ';transition:background .25s;}',
            '#tcnWidgetPanel{width:268px;background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18);border:1px solid ' + C.border + ';overflow:hidden;margin-bottom:10px;animation:tcnSlideUp .2s ease;}',
            '.tcn-dp{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;padding:4px 14px 12px;}',
            '.tcn-dp-key{height:44px;border:none;border-radius:9px;background:' + C.surface + ';font-family:"Manrope",sans-serif;font-size:18px;font-weight:700;color:' + C.dark + ';cursor:pointer;transition:background .12s;display:flex;align-items:center;justify-content:center;}',
            '.tcn-dp-key:hover{background:' + C.border + ';}',
            '.tcn-dp-key:active{background:#dbeafe;color:' + C.primary + ';}',
            '.tcn-dp-back{height:38px;border:none;border-radius:9px;background:' + C.surface + ';font-family:"Manrope",sans-serif;cursor:pointer;transition:background .12s;display:flex;align-items:center;justify-content:center;color:' + C.muted + ';grid-column:1/-1;}',
            '.tcn-dp-back:hover{background:' + C.border + ';}',
            '.tcn-ctrl-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;border-radius:9px;padding:10px 0;font-family:"Manrope",sans-serif;font-weight:700;font-size:13px;cursor:pointer;border:1px solid ' + C.border + ';transition:opacity .15s;}',
            '.tcn-icon-btn{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;border-radius:9px;padding:10px 0;font-family:"Manrope",sans-serif;font-weight:600;font-size:11px;cursor:pointer;border:1px solid ' + C.border + ';background:' + C.surface + ';color:' + C.dark + ';transition:background .15s;}',
            '.tcn-icon-btn:hover{background:' + C.border + ';}',
            '@keyframes tcnSlideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}',
            '@keyframes tcnPulse{0%,100%{opacity:1}50%{opacity:.6}}',
        ].join('');
        document.head.appendChild(styleEl);

        // ── Root container ──────────────────────────────────────────────
        var root = el('div', { id: 'tcnWidget', style: 'display:none;' });

        // ── Panel ───────────────────────────────────────────────────────
        var panel = el('div', { id: 'tcnWidgetPanel', style: 'display:none;' });

        //   Header
        var hdr = el('div', { style: 'display:flex;align-items:center;justify-content:space-between;padding:12px 14px 10px;border-bottom:1px solid ' + C.border + ';' });
        var hdrLeft = el('div', { style: 'display:flex;align-items:center;gap:8px;' });
        var hdrIcon = el('span', { class: 'material-icons', style: 'color:' + C.primary + ';font-size:20px;' }, 'phone');
        var hdrTitle = el('span', { style: 'font-weight:700;font-size:13px;color:' + C.dark + ';' }, 'TCN Softphone');
        hdrLeft.appendChild(hdrIcon);
        hdrLeft.appendChild(hdrTitle);
        var minBtn = el('button', { style: 'background:none;border:none;cursor:pointer;color:' + C.muted + ';padding:2px;line-height:1;display:flex;align-items:center;' });
        minBtn.innerHTML = '<span class="material-icons" style="font-size:18px;">remove</span>';
        minBtn.addEventListener('click', function (e) { e.stopPropagation(); collapse(); });
        hdr.appendChild(hdrLeft);
        hdr.appendChild(minBtn);

        //   Status bar
        var statusBar = el('div', { style: 'display:flex;align-items:center;gap:7px;padding:6px 14px 8px;' });
        var statusDot = el('span', { style: 'width:9px;height:9px;border-radius:50%;display:inline-block;background:' + C.muted + ';flex-shrink:0;transition:background .25s;' });
        var statusTxt = el('span', { style: 'font-size:12px;font-weight:600;color:' + C.muted + ';transition:color .25s;' }, 'Connecting\u2026');
        statusBar.appendChild(statusDot);
        statusBar.appendChild(statusTxt);

        //   Phone display
        var phoneDisp = el('div', { style: 'margin:0 14px 8px;padding:10px 12px;background:' + C.surface + ';border:1px solid ' + C.border + ';border-radius:9px;font-size:19px;font-weight:800;color:' + C.dark + ';letter-spacing:1.5px;min-height:46px;line-height:1.3;word-break:break-all;font-variant-numeric:tabular-nums;' });
        phoneDisp.textContent = '\u2014';

        //   Dial pad
        var dpSection = el('div', { id: 'tcnDpSection' });
        var dpGrid = el('div', { class: 'tcn-dp' });
        ['1','2','3','4','5','6','7','8','9','*','0','#'].forEach(function (k) {
            var b = el('button', { class: 'tcn-dp-key', 'data-tcn-key': k }, k);
            b.addEventListener('click', function () { appendDigit(k); });
            dpGrid.appendChild(b);
        });
        var bkBtn = el('button', { class: 'tcn-dp-back' });
        bkBtn.innerHTML = '<span class="material-icons" style="font-size:18px;">backspace</span>';
        bkBtn.addEventListener('click', function () { deleteDigit(); });
        dpGrid.appendChild(bkBtn);
        dpSection.appendChild(dpGrid);

        //   Pre-call controls
        var preCallCtrl = el('div', { style: 'padding:0 14px 12px;' });
        var callBtn = el('button', { class: 'tcn-ctrl-btn', style: 'width:100%;background:' + C.success + ';color:#fff;border-color:' + C.success + ';' });
        callBtn.innerHTML = '<span class="material-icons" style="font-size:17px;">call</span> Call';
        callBtn.addEventListener('click', function () { handleCallBtn(); });
        preCallCtrl.appendChild(callBtn);

        //   In-call controls
        var inCallCtrl = el('div', { style: 'display:none;padding:0 14px 12px;' });
        var timerDisp = el('div', { style: 'text-align:center;font-size:28px;font-weight:800;font-variant-numeric:tabular-nums;color:' + C.dark + ';margin-bottom:4px;' }, '0:00');
        var callingLbl = el('div', { style: 'text-align:center;font-size:12px;color:' + C.muted + ';margin-bottom:12px;' });
        var inCallBtns = el('div', { style: 'display:flex;gap:8px;' });
        var muteBtn = el('button', { class: 'tcn-icon-btn' });
        muteBtn.innerHTML = '<span class="material-icons" style="font-size:22px;">mic</span>Mute';
        muteBtn.addEventListener('click', function () { toggleMute(); });
        var endBtn = el('button', { class: 'tcn-icon-btn', style: 'background:' + C.danger + ';border-color:' + C.danger + ';color:#fff;' });
        endBtn.innerHTML = '<span class="material-icons" style="font-size:22px;">call_end</span>End';
        endBtn.addEventListener('click', function () { handleEndBtn(); });
        inCallBtns.appendChild(muteBtn);
        inCallBtns.appendChild(endBtn);
        inCallCtrl.appendChild(timerDisp);
        inCallCtrl.appendChild(callingLbl);
        inCallCtrl.appendChild(inCallBtns);

        //   Agent controls
        var agentCtrl = el('div', { style: 'display:flex;gap:8px;padding:10px 14px 14px;border-top:1px solid ' + C.border + ';' });
        var pauseBtn = el('button', { class: 'tcn-icon-btn' });
        pauseBtn.innerHTML = '<span class="material-icons" style="font-size:18px;" id="tcnPauseIcon">pause</span><span id="tcnPauseLabel">Pause</span>';
        pauseBtn.addEventListener('click', function () { toggleAgentStatus(); });
        var logoutBtn = el('button', { class: 'tcn-icon-btn', style: 'color:' + C.muted + ';' });
        logoutBtn.innerHTML = '<span class="material-icons" style="font-size:18px;">logout</span>Logout';
        logoutBtn.addEventListener('click', function () { handleLogout(); });
        agentCtrl.appendChild(pauseBtn);
        agentCtrl.appendChild(logoutBtn);

        //   Assemble panel
        panel.appendChild(hdr);
        panel.appendChild(statusBar);
        panel.appendChild(phoneDisp);
        panel.appendChild(dpSection);
        panel.appendChild(preCallCtrl);
        panel.appendChild(inCallCtrl);
        panel.appendChild(agentCtrl);

        // ── Minimized tab ───────────────────────────────────────────────
        var tab = el('div', { id: 'tcnWidgetTab' });
        var tabDot = el('span', { id: 'tcnWidgetDot' });
        var tabIcon = el('span', { class: 'material-icons', style: 'color:#fff;font-size:24px;pointer-events:none;' }, 'phone');
        tab.appendChild(tabDot);
        tab.appendChild(tabIcon);
        tab.addEventListener('click', function () { expand(); });

        // ── Assemble widget ─────────────────────────────────────────────
        root.appendChild(panel);
        root.appendChild(tab);
        document.body.appendChild(root);

        // ── Store refs ──────────────────────────────────────────────────
        W = {
            root:         root,
            panel:        panel,
            tab:          tab,
            tabDot:       tabDot,
            statusDot:    statusDot,
            statusTxt:    statusTxt,
            phoneDisp:    phoneDisp,
            dpSection:    dpSection,
            preCallCtrl:  preCallCtrl,
            callBtn:      callBtn,
            inCallCtrl:   inCallCtrl,
            timerDisp:    timerDisp,
            callingLbl:   callingLbl,
            muteBtn:      muteBtn,
            endBtn:       endBtn,
            agentCtrl:    agentCtrl,
            pauseBtn:     pauseBtn,
            pauseIcon:    null, // set after append
            pauseLabel:   null,
            logoutBtn:    logoutBtn,
        };

        // Late-bind elements that were created with id
        W.pauseIcon  = document.getElementById('tcnPauseIcon');
        W.pauseLabel = document.getElementById('tcnPauseLabel');
    }

    // ─── Render (sync DOM to state) ──────────────────────────────────────
    function render() {
        if (!W.root) return;
        W.root.style.display = 'block';

        // Color map per state
        var COLOR_MAP = {
            connecting: C.muted,
            ready:      C.success,
            paused:     C.warning,
            calling:    C.primary,
            'on-call':  C.danger,
            error:      C.danger,
        };
        var LABEL_MAP = {
            connecting: 'Connecting\u2026',
            ready:      'Ready',
            paused:     'Paused',
            calling:    'Calling\u2026',
            'on-call':  'On Call',
            error:      'Error',
        };

        var stateColor = COLOR_MAP[_state] || C.muted;
        var stateLabel = LABEL_MAP[_state]  || _state;

        // Tab
        W.tab.style.background = stateColor;
        W.tabDot.style.background = stateColor;

        // Pulse animation during calling
        W.tab.style.animation = (_state === 'calling') ? 'tcnPulse 1s ease-in-out infinite' : '';

        // Status bar
        W.statusDot.style.background = stateColor;
        W.statusTxt.style.color      = stateColor;
        W.statusTxt.textContent      = stateLabel;

        // Section visibility
        var inCall = (_state === 'calling' || _state === 'on-call');
        W.dpSection.style.display    = inCall ? 'none'  : 'block';
        W.preCallCtrl.style.display  = inCall ? 'none'  : 'block';
        W.inCallCtrl.style.display   = inCall ? 'block' : 'none';
        W.agentCtrl.style.display    = inCall ? 'none'  : 'flex';

        // Call button enable/disable
        var canCall = (_state === 'ready' && _phone.length >= 5);
        W.callBtn.disabled      = !canCall;
        W.callBtn.style.opacity = canCall ? '1' : '0.5';
        W.callBtn.style.cursor  = canCall ? 'pointer' : 'not-allowed';

        // Calling label
        if (inCall) W.callingLbl.textContent = _phone || '';

        // Pause/Resume button
        if (_agentPaused) {
            if (W.pauseIcon)  W.pauseIcon.textContent  = 'play_arrow';
            if (W.pauseLabel) W.pauseLabel.textContent  = 'Resume';
            W.pauseBtn.style.background  = C.warning;
            W.pauseBtn.style.borderColor = C.warning;
            W.pauseBtn.style.color       = '#fff';
        } else {
            if (W.pauseIcon)  W.pauseIcon.textContent  = 'pause';
            if (W.pauseLabel) W.pauseLabel.textContent  = 'Pause';
            W.pauseBtn.style.background  = C.surface;
            W.pauseBtn.style.borderColor = C.border;
            W.pauseBtn.style.color       = C.dark;
        }
    }

    // ─── State setter ─────────────────────────────────────────────────────
    function setState(newState) {
        _state = newState;
        render();
    }

    // ─── Phone management ─────────────────────────────────────────────────
    function setPhone(phone) {
        _phone = String(phone || '').replace(/\s+/g, '');
        if (W.phoneDisp) W.phoneDisp.textContent = _phone || '\u2014';
        render();
    }

    function appendDigit(d) {
        if (_state === 'calling' || _state === 'on-call') return;
        _phone += d;
        if (W.phoneDisp) W.phoneDisp.textContent = _phone;
        render();
    }

    function deleteDigit() {
        if (_state === 'calling' || _state === 'on-call') return;
        _phone = _phone.slice(0, -1);
        if (W.phoneDisp) W.phoneDisp.textContent = _phone || '\u2014';
        render();
    }

    // ─── Expand / collapse ────────────────────────────────────────────────
    function expand() {
        _expanded = true;
        if (W.panel) {
            W.panel.style.display   = 'block';
            W.panel.style.animation = 'tcnSlideUp .2s ease';
        }
    }

    function collapse() {
        _expanded = false;
        if (W.panel) W.panel.style.display = 'none';
    }

    // ─── Call actions ─────────────────────────────────────────────────────
    function handleCallBtn() {
        if (_state !== 'ready' || _phone.length < 5) return;
        if (window.GC && typeof window.GC.startCall === 'function') {
            window.GC.startCall(_phone, null).catch(function (e) {
                console.error('[TCN-Widget] startCall failed:', e && e.message);
            });
        } else if (window.TCN && window.TCN._loggedIn) {
            window.TCN.startCall(_phone, null).catch(function (e) {
                console.error('[TCN-Widget] startCall failed:', e && e.message);
            });
        }
    }

    function handleEndBtn() {
        if (window.GC && typeof window.GC.endCall === 'function') {
            window.GC.endCall();
        } else if (window.TCN && window.TCN._callActive) {
            window.TCN.endCall();
        }
    }

    function toggleMute() {
        if (!window.TCN) return;
        _muted = !_muted;
        if (_muted) {
            window.TCN.mute();
            W.muteBtn.innerHTML = '<span class="material-icons" style="font-size:22px;">mic_off</span>Unmute';
            W.muteBtn.style.background  = '#fee2e2';
            W.muteBtn.style.borderColor = C.danger;
            W.muteBtn.style.color       = C.danger;
        } else {
            window.TCN.unmute();
            W.muteBtn.innerHTML = '<span class="material-icons" style="font-size:22px;">mic</span>Mute';
            W.muteBtn.style.background  = C.surface;
            W.muteBtn.style.borderColor = C.border;
            W.muteBtn.style.color       = C.dark;
        }
    }

    // ─── Agent pause / resume ─────────────────────────────────────────────
    function toggleAgentStatus() {
        if (_state === 'calling' || _state === 'on-call') return;
        var newPaused   = !_agentPaused;
        var statusParam = newPaused ? 'UNAVAILABLE' : 'READY';

        W.pauseBtn.disabled = true;

        fetch('/tcn/set-status', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ status: statusParam }),
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            // Accept both ok:true and warning (API may not support set-status for this account)
            _agentPaused = newPaused;
            setState(_agentPaused ? 'paused' : 'ready');
            if (data && data.warning) {
                console.warn('[TCN-Widget] set-status warning:', data.warning);
            }
        }).catch(function (e) {
            // Toggle locally even on network error
            console.error('[TCN-Widget] set-status error:', e);
            _agentPaused = newPaused;
            setState(_agentPaused ? 'paused' : 'ready');
        }).finally(function () {
            W.pauseBtn.disabled = false;
        });
    }

    // ─── Logout ────────────────────────────────────────────────────────────
    function handleLogout() {
        if (!confirm('Log out of TCN softphone?')) return;
        if (window.GC && typeof window.GC.disableCallingMode === 'function') {
            window.GC.disableCallingMode();
        } else if (window.TCN) {
            window.TCN.logout();
        }
        setState('connecting');
        collapse();
    }

    // ─── Call timer ────────────────────────────────────────────────────────
    function startCallTimer() {
        _callSecs = 0;
        stopCallTimer();
        updateTimer();
        _callTimer = setInterval(function () {
            _callSecs++;
            updateTimer();
        }, 1000);
    }

    function stopCallTimer() {
        if (_callTimer) { clearInterval(_callTimer); _callTimer = null; }
    }

    function updateTimer() {
        if (!W.timerDisp) return;
        var m = Math.floor(_callSecs / 60);
        var s = _callSecs % 60;
        W.timerDisp.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    }

    // ─── Wire TCN + GC events ─────────────────────────────────────────────
    function wireEvents() {
        window.addEventListener('tcn:ready', function () {
            _agentPaused = false;
            setState('ready');
        });

        window.addEventListener('tcn:callStarted', function (e) {
            var detail = e.detail || {};
            if (detail.phone) setPhone(detail.phone);
            startCallTimer();
            setState('calling');
            // Auto-expand so agent can see call controls
            if (!_expanded) expand();
        });

        window.addEventListener('tcn:callAnswered', function () {
            setState('on-call');
        });

        window.addEventListener('tcn:callEnded', function () {
            stopCallTimer();
            // Reset mute
            _muted = false;
            if (W.muteBtn) {
                W.muteBtn.innerHTML = '<span class="material-icons" style="font-size:22px;">mic</span>Mute';
                W.muteBtn.style.background  = C.surface;
                W.muteBtn.style.borderColor = C.border;
                W.muteBtn.style.color       = C.dark;
            }
            setState(_agentPaused ? 'paused' : 'ready');
        });

        window.addEventListener('tcn:sipDropped', function () {
            if (_state !== 'calling' && _state !== 'on-call') {
                setState('connecting');
            }
        });

        window.addEventListener('tcn:loggedOut', function () {
            stopCallTimer();
            setState('connecting');
        });

        window.addEventListener('tcn:error', function (e) {
            var msg = (e.detail && e.detail.message) ? e.detail.message : '';
            console.error('[TCN-Widget] tcn:error:', msg);
            // Don't switch to error state if already in a call or connecting
            if (_state !== 'calling' && _state !== 'on-call' && _state !== 'ready') {
                setState('error');
            }
        });

        // Adjust position when call bar (gcCallBar) appears / disappears
        document.addEventListener('gc:callAccepted', function () {
            if (W.root) W.root.style.bottom = '80px';
        });
        document.addEventListener('gc:callEnded', function () {
            if (W.root) W.root.style.bottom = '20px';
        });

        // Intercept clicks on [data-phone] buttons to pre-populate dial pad
        document.addEventListener('click', function (e) {
            // Skip if the click is inside the widget itself
            if (W.root && W.root.contains(e.target)) return;
            var btn = e.target.closest('[data-phone]');
            if (btn) {
                var phone = btn.getAttribute('data-phone');
                if (phone) {
                    setPhone(phone);
                    if (!_expanded) expand();
                }
            }
        }, true);
    }

    // ─── Initialize ───────────────────────────────────────────────────────
    function init() {
        buildWidget();
        wireEvents();
        render();
    }

    document.addEventListener('DOMContentLoaded', init);

    // ─── Public API ───────────────────────────────────────────────────────
    window.TcnWidget = {
        setState: setState,
        setPhone: setPhone,
        expand:   expand,
        collapse: collapse,
    };

})();
