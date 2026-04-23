/**
 * global-call.js — TCN call manager (v4 — auto-answer edition)
 *
 * All calls are handled via the TCN softphone iframe (id="tcnSoftphoneFrame").
 * Parent ↔ iframe communication uses window.postMessage.
 *
 * Changes in v4:
 *  - gcCallBar sticky-top bar now has End / Mute / Hold controls
 *  - Audio alert plays on tcn:callAnswered (Web Audio API — no file needed)
 *  - toggleMute() / toggleHold() send MUTE / HOLD postMessages to the iframe
 *  - body.gc-call-active class pushes content below the call bar
 *  - Auto-answer: TCN_CALL_STARTED with incoming:true shows "Auto-Answered" status
 */

(function () {
    "use strict";

    var GC = {

        // ── Core state ─────────────────────────────────────────────
        _state: null,
        _csrf: null,
        _timerInterval: null,
        _manualHangup: false,
        _endReported: false,

        _tcnEventsWired: false,
        _pendingLeadId: null,
        _wrapupReached: false,
        _deviceInitialized: false,

        // ── Call-bar control state ─────────────────────────────────
        _muted: false,
        _onHold: false,

        _agentRole: function () {
            var meta = document.querySelector('meta[name="user-role"]');
            return meta && meta.getAttribute('content') === 'manager' ? 'manager' : 'telecaller';
        },

        // ── Web Audio context for alert sound ──────────────────────
        _audioCtx: null,

        // ── localStorage key — persists "SIP active" across page navigations ──
        SIP_ACTIVE_KEY: 'tcn_sip_active',

        _isSipPersisted: function () {
            try { return localStorage.getItem(this.SIP_ACTIVE_KEY) === '1'; } catch (_) { return false; }
        },

        _persistSip: function (active) {
            try {
                if (active) localStorage.setItem(this.SIP_ACTIVE_KEY, '1');
                else localStorage.removeItem(this.SIP_ACTIVE_KEY);
            } catch (_) {}
        },

        isActive: function () {
            return !!this._state;
        },

        // ── Return the embedded softphone iframe ───────────────────
        _tcnFrame: function () {
            return document.getElementById('tcnSoftphoneFrame');
        },

        _showTcnFrame: function () {
            var f = this._tcnFrame();
            if (!f) return;
            f.style.display = 'block';
            f.style.bottom = '80px';
            f.style.height = '480px';
            f.style.width = '300px';
            f.style.borderRadius = '14px';
        },

        // ── Audio alert — Web Audio API, no external file needed ───
        // Plays a two-tone "call connected" sound that works even without
        // a prior user gesture on the parent page (gesture happened in iframe).
        _playCallAlert: function () {
            try {
                if (!this._audioCtx) {
                    this._audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                var ctx = this._audioCtx;
                // Resume in case browser suspended the context
                if (ctx.state === 'suspended') { ctx.resume(); }

                // Two short tones: 880 Hz + 1100 Hz (pleasant "ding-ding")
                var tones = [
                    { freq: 880,  start: 0,    dur: 0.18 },
                    { freq: 1100, start: 0.22, dur: 0.18 },
                ];
                tones.forEach(function (t) {
                    var osc  = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.type = 'sine';
                    osc.frequency.value = t.freq;
                    var now = ctx.currentTime;
                    gain.gain.setValueAtTime(0, now + t.start);
                    gain.gain.linearRampToValueAtTime(0.32, now + t.start + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + t.start + t.dur);
                    osc.start(now + t.start);
                    osc.stop(now + t.start + t.dur + 0.02);
                });
            } catch (_) {}
        },

        // ── Device init ────────────────────────────────────────────
        initDevice: async function () {
            if (this._deviceInitialized) return;
            this._deviceInitialized = true;

            await this._initTcn();

            if (this._isSipPersisted()) {
                await this.enableCallingMode();
            }
        },

        readyForCalls: async function () {
            await this.initDevice();
            await this.enableCallingMode();
        },

        // ── Wire postMessage events from the softphone iframe ──────
        _initTcn: async function () {
            var self = this;

            if (self._tcnEventsWired) return;
            self._tcnEventsWired = true;

            // Wire call-bar button clicks (event delegation survives Turbo nav)
            document.addEventListener('click', function (e) {
                if (e.target.closest('#gcMuteBtn'))       { self.toggleMute(); }
                if (e.target.closest('#gcHoldBtn'))       { self.toggleHold(); }
                if (e.target.closest('#gcEndCallBarBtn')) { self.endCall(); }
            }, true);

            window.addEventListener("message", function (ev) {
                var d = ev.data;
                if (!d || typeof d !== "object") return;

                switch (d.type) {

                    // ── Incoming call popup (manual-answer mode, not auto-answer) ──
                    case "TCN_INCOMING_CALL":
                        self._showIncomingCallPopup(d.phone || "Unknown");
                        break;

                    case "TCN_INCOMING_REJECTED":
                        self._hideIncomingCallPopup();
                        self._showMissedCallToast(d.phone || null, d.callLogId || null);
                        window.dispatchEvent(new CustomEvent('gc:missedCall', {
                            detail: { phone: d.phone || null, callLogId: d.callLogId || null }
                        }));
                        break;

                    case "TCN_PHONE_RESOLVED":
                        if (d.phone) {
                            self._updatePhone(d.phone);
                            self._showIncomingCallPopup(d.phone);
                        }
                        break;

                    // ── Call started (outbound dial-out OR inbound auto-answered) ──
                    case "TCN_CALL_STARTED":
                        self._endReported = false;
                        self._wrapupReached = false;
                        self._state = {
                            callLogId: d.callLogId || null,
                            phone:     d.phone || "",
                            leadId:    self._pendingLeadId || null,
                            leadName:  null,
                            leadUrl:   null,
                            answeredAt: null,
                        };
                        self._pendingLeadId = null;

                        // Reset mute / hold state for this new call
                        self._muted  = false;
                        self._onHold = false;
                        self._resetMuteUI();
                        self._resetHoldUI();

                        // Show bar immediately.
                        // Auto-answered inbound calls fire TCN_CALL_STARTED and TCN_CALL_ANSWERED
                        // nearly simultaneously — show "Auto-Answered" for inbound, "Connecting…" for outbound.
                        var initialStatus = d.incoming ? 'Auto-Answered' : 'Connecting\u2026';
                        self._showBar(d.phone || (d.incoming ? 'Incoming' : 'Connecting\u2026'), initialStatus);
                        self._stopTimer();
                        document.dispatchEvent(new CustomEvent("gc:callAccepted"));
                        break;

                    // ── Call answered (PSTN leg bridged — start timer) ─────────
                    case "TCN_CALL_ANSWERED":
                        if (self._wrapupReached) return;

                        self._hideIncomingCallPopup();

                        if (!self._state) {
                            // Incoming auto-answered without prior TCN_CALL_STARTED message.
                            self._endReported = false;
                            self._state = {
                                callLogId: d.callLogId || null,
                                phone:     d.phone || "Incoming",
                                leadId:    null,
                                leadName:  null,
                                leadUrl:   null,
                                answeredAt: Date.now(),
                            };
                            self._showBar(self._state.phone, 'Call Connected');
                            self._startTimer(self._state.answeredAt);
                            document.dispatchEvent(new CustomEvent("gc:callAccepted"));
                        } else {
                            self._state.answeredAt = Date.now();
                            self._showBar(self._state.phone || d.phone || "", 'Call Connected');
                            self._startTimer(self._state.answeredAt);
                        }

                        // Play alert so telecaller knows the call is live
                        self._playCallAlert();
                        break;

                    // ── WRAPUP — post-call state ───────────────────────────────
                    case "TCN_WRAPUP":
                        self._wrapupReached = true;
                        self._stopTimer();
                        break;

                    // ── Call ended ──────────────────────────────────────────────
                    case "TCN_CALL_ENDED":
                        self._finalize(d.status || "completed", d.ended_by || null);
                        self._wrapupReached = false;
                        break;

                    case "TCN_ERROR":
                        console.error("[GC-TCN]", d.message);
                        if (self._state && !self._endReported) {
                            self._finalize("failed");
                        }
                        break;

                    // ── Hold / resume state reflected from iframe ──────────────
                    case "TCN_ON_HOLD":
                        self._onHold = true;
                        self._setHoldUI(true);
                        break;

                    case "TCN_OFF_HOLD":
                        self._onHold = false;
                        self._setHoldUI(false);
                        break;
                }
            });
        },

        // ── Enabling / disabling calling mode ──────────────────────
        enableCallingMode: async function () {
            this._persistSip(true);
            this._showTcnFrame();
            var f = this._tcnFrame();
            if (!f) return;

            try {
                if (f.contentWindow && f.contentWindow._sipBooted) {
                    console.log("[GC-TCN] SIP already running — skipping START_SIP.");
                    return;
                }
            } catch (_) {}

            function _sendStartSip() {
                try { if (f.contentWindow && f.contentWindow._sipBooted) return; } catch (_) {}
                if (f && f.contentWindow) {
                    f.contentWindow.postMessage({ type: "START_SIP" }, "*");
                }
            }

            if (f.contentDocument && f.contentDocument.readyState === 'complete') {
                _sendStartSip();
            } else {
                f.addEventListener('load', _sendStartSip, { once: true });
            }
            console.log("[GC-TCN] Calling mode enabled.");
        },

        disableCallingMode: function () {
            this._persistSip(false);
            try { localStorage.removeItem('tcn_softphone_bootstrap_v1'); } catch (_) {}
            try { localStorage.removeItem('tcn_service_bootstrap_v2'); } catch (_) {}
            var f = this._tcnFrame();
            if (f && f.contentWindow) f.contentWindow.postMessage({ type: "LOGOUT_SILENT" }, "*");
            console.log("[GC-TCN] Calling mode disabled.");
        },

        // ── Call initiation ─────────────────────────────────────────
        startCall: async function (phone, leadId) {
            if (this._state) {
                alert("Call already active");
                return;
            }
            await this.initDevice();
            return this._startTcnCall(phone, leadId);
        },

        _startTcnCall: function (phone, leadId) {
            var self = this;
            var f = self._tcnFrame();

            if (!f) {
                console.error("[GC-TCN] Softphone iframe not found in DOM.");
                return Promise.resolve();
            }

            self._pendingLeadId = leadId || null;
            self._showTcnFrame();
            f.contentWindow.postMessage({ type: "CALL", phone: phone, leadId: leadId || null }, "*");
            return Promise.resolve();
        },

        endCall: function () {
            this._manualHangup = true;

            var f = this._tcnFrame();
            if (f && f.contentWindow) {
                f.contentWindow.postMessage({ type: "HANGUP" }, "*");
            } else if (window.TCN && window.TCN._callActive) {
                window.TCN.endCall();
            } else if (this._state) {
                this._finalize("canceled");
            }
        },

        // ── Mute toggle ────────────────────────────────────────────
        toggleMute: function () {
            if (!this._state) return;
            this._muted = !this._muted;

            var f = this._tcnFrame();
            if (f && f.contentWindow) {
                f.contentWindow.postMessage({ type: "MUTE" }, "*");
            } else if (window.TCN) {
                if (this._muted) window.TCN.mute();
                else             window.TCN.unmute();
            }

            this._setMuteUI(this._muted);
        },

        // ── Hold toggle ────────────────────────────────────────────
        toggleHold: function () {
            if (!this._state) return;

            var f = this._tcnFrame();
            if (f && f.contentWindow) {
                f.contentWindow.postMessage({ type: "HOLD" }, "*");
            } else if (window.TCN) {
                if (this._onHold) window.TCN.resume();
                else              window.TCN.hold();
            }
            // Visual update happens on TCN_ON_HOLD / TCN_OFF_HOLD postMessage from iframe
        },

        // ── Mute/Hold button UI helpers ────────────────────────────
        _setMuteUI: function (muted) {
            var btn  = document.getElementById('gcMuteBtn');
            var icon = document.getElementById('gcMuteIcon');
            if (!btn || !icon) return;
            if (muted) {
                btn.style.background  = 'rgba(239,68,68,.45)';
                btn.title             = 'Unmute';
                icon.textContent      = 'mic_off';
            } else {
                btn.style.background  = 'rgba(255,255,255,.18)';
                btn.title             = 'Mute';
                icon.textContent      = 'mic';
            }
        },

        _resetMuteUI: function () {
            this._muted = false;
            this._setMuteUI(false);
        },

        _setHoldUI: function (onHold) {
            var btn  = document.getElementById('gcHoldBtn');
            var icon = document.getElementById('gcHoldIcon');
            if (!btn || !icon) return;
            if (onHold) {
                btn.style.background  = 'rgba(245,158,11,.5)';
                btn.title             = 'Resume';
                icon.textContent      = 'play_circle';
            } else {
                btn.style.background  = 'rgba(255,255,255,.18)';
                btn.title             = 'Hold';
                icon.textContent      = 'pause_circle';
            }
        },

        _resetHoldUI: function () {
            this._onHold = false;
            this._setHoldUI(false);
        },

        // ── Finalize (call ended) ──────────────────────────────────
        _finalize: async function (status, iframeEndedBy) {
            if (this._endReported) return;
            this._endReported = true;

            var duration = this._state && this._state.answeredAt
                ? Math.floor((Date.now() - this._state.answeredAt) / 1000)
                : 0;

            var logId    = this._state ? this._state.callLogId : null;
            var phone    = this._state ? this._state.phone     : null;
            // Prefer explicit ended_by from iframe message; fall back to manualHangup flag
            var endedBy  = iframeEndedBy || (this._manualHangup ? this._agentRole() : null);

            this._stopTimer();
            this._hideBar();
            this._hideIncomingCallPopup();

            // Reset call-bar control state
            this._resetMuteUI();
            this._resetHoldUI();

            if (logId) {
                var body = { call_log_id: logId, duration: duration, final_status: status };
                if (endedBy) body.ended_by = endedBy;
                await fetch("/call/end", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": this._csrf },
                    body: JSON.stringify(body)
                });
            }

            this._state        = null;
            this._endReported  = false;
            this._manualHangup = false;

            document.dispatchEvent(new CustomEvent("gc:callEnded", {
                detail: { callLogId: logId, phone: phone }
            }));
        },

        _markAnswered: function (label) {
            if (!this._state) return;
            if (!this._state.answeredAt) {
                this._state.answeredAt = Date.now();
            }
            this._updateBar(label);
            this._startTimer(this._state.answeredAt);
            document.dispatchEvent(new CustomEvent("gc:callAccepted"));
        },

        // ── Incoming call popup (manual-answer mode only) ─────────
        _showIncomingCallPopup: function (phone) {
            var self = this;
            var popup = document.getElementById('gcIncomingCallPopup');

            if (!popup) {
                popup = document.createElement('div');
                popup.id = 'gcIncomingCallPopup';

                var styleEl = document.createElement('style');
                styleEl.textContent = [
                    '@keyframes gcRingPulse{',
                    '0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.6)}',
                    '70%{box-shadow:0 0 0 12px rgba(99,102,241,0)}}',
                    '#gcIncomingCallPopup{animation:gcRingPulse 1.2s ease-in-out infinite;}',
                ].join('');
                document.head.appendChild(styleEl);

                popup.style.cssText = [
                    'position:fixed;bottom:100px;right:20px;width:270px;',
                    'background:#fff;border-radius:14px;',
                    'box-shadow:0 8px 32px rgba(0,0,0,.22);',
                    'border:1px solid #e2e8f0;z-index:10001;overflow:hidden;',
                    'font-family:"Plus Jakarta Sans",sans-serif;',
                ].join('');

                popup.innerHTML = [
                    '<div style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;padding:12px 14px;">',
                    '  <div style="font-size:11px;font-weight:600;opacity:.75;margin-bottom:3px;letter-spacing:.5px;text-transform:uppercase;">Incoming Call</div>',
                    '  <div id="gcIncomingPhone" style="font-size:17px;font-weight:800;letter-spacing:.5px;"></div>',
                    '</div>',
                    '<div style="padding:12px 14px;display:flex;gap:8px;">',
                    '  <button id="gcAcceptCallBtn" style="flex:1;height:38px;border:none;border-radius:9px;',
                    '    background:#10b981;color:#fff;font-family:inherit;font-weight:700;font-size:13px;',
                    '    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;',
                    '    transition:opacity .15s;">',
                    '    <span class="material-icons" style="font-size:16px;">call</span> Accept',
                    '  </button>',
                    '  <button id="gcRejectCallBtn" style="flex:1;height:38px;border:none;border-radius:9px;',
                    '    background:#ef4444;color:#fff;font-family:inherit;font-weight:700;font-size:13px;',
                    '    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;',
                    '    transition:opacity .15s;">',
                    '    <span class="material-icons" style="font-size:16px;">call_end</span> Reject',
                    '  </button>',
                    '</div>',
                ].join('');

                document.body.appendChild(popup);

                document.getElementById('gcAcceptCallBtn').addEventListener('click', function () {
                    self._hideIncomingCallPopup();
                    var f = self._tcnFrame();
                    if (f && f.contentWindow) f.contentWindow.postMessage({ type: 'ACCEPT_INCOMING' }, '*');
                });

                document.getElementById('gcRejectCallBtn').addEventListener('click', function () {
                    self._hideIncomingCallPopup();
                    var f = self._tcnFrame();
                    if (f && f.contentWindow) f.contentWindow.postMessage({ type: 'REJECT_INCOMING' }, '*');
                });
            }

            var phoneEl = document.getElementById('gcIncomingPhone');
            if (phoneEl) phoneEl.textContent = phone;
            popup.style.display = 'block';
        },

        _hideIncomingCallPopup: function () {
            var popup = document.getElementById('gcIncomingCallPopup');
            if (popup) popup.style.display = 'none';
        },

        // ── Missed call toast ──────────────────────────────────────
        _showMissedCallToast: function (phone, callLogId) {
            var self = this;

            // Play a distinct double-beep missed-call sound
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                [[440, 0], [440, 0.35]].forEach(function (t) {
                    var osc  = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.type = 'sine';
                    osc.frequency.value = t[0];
                    var now = ctx.currentTime;
                    gain.gain.setValueAtTime(0, now + t[1]);
                    gain.gain.linearRampToValueAtTime(0.28, now + t[1] + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + t[1] + 0.22);
                    osc.start(now + t[1]);
                    osc.stop(now + t[1] + 0.25);
                });
            } catch (_) {}

            // Build toast
            var toastId = 'gcMissedCallToast_' + Date.now();
            var displayPhone = phone || 'Unknown caller';
            var logLink = callLogId
                ? ' <a href="/telecaller/call-logs" style="color:#fca5a5;font-weight:700;text-decoration:underline;">View log</a>'
                : '';

            var toast = document.createElement('div');
            toast.id = toastId;
            toast.style.cssText = [
                'position:fixed;bottom:80px;right:20px;z-index:10100;',
                'width:280px;border-radius:13px;overflow:hidden;',
                'box-shadow:0 8px 28px rgba(0,0,0,.28);',
                'font-family:"Plus Jakarta Sans",sans-serif;',
                'animation:gcToastIn .25s ease;',
            ].join('');
            toast.innerHTML = [
                '<style>',
                '@keyframes gcToastIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}',
                '</style>',
                '<div style="background:#dc2626;padding:10px 14px 12px;">',
                '  <div style="display:flex;align-items:center;gap:7px;margin-bottom:4px;">',
                '    <span class="material-icons" style="font-size:17px;color:#fff;flex-shrink:0;">phone_missed</span>',
                '    <span style="font-size:11px;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase;letter-spacing:.6px;">Missed Call</span>',
                '  </div>',
                '  <div style="font-size:16px;font-weight:800;color:#fff;letter-spacing:.5px;">' + displayPhone + '</div>',
                '  <div style="font-size:11px;color:rgba(255,255,255,.75);margin-top:3px;">Caller hung up before you could answer.' + logLink + '</div>',
                '</div>',
            ].join('');

            document.body.appendChild(toast);

            // Auto-remove after 8s
            var removeToast = function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            };
            setTimeout(removeToast, 8000);
            toast.addEventListener('click', removeToast);
        },

        // ── Call bar show / hide / update ─────────────────────────
        // status: optional string for the small label above the phone number.
        _showBar: function (phoneText, status) {
            var bar = document.getElementById("gcCallBar");
            var ph  = document.getElementById("gcCallPhone");
            var st  = document.getElementById("gcCallStatus");

            if (ph) ph.textContent = phoneText || '—';
            if (st) st.textContent = status || 'Connecting\u2026';
            this._setCallLeadLink(this._state && this._state.leadUrl ? this._state.leadUrl : null);
            if (bar) bar.style.display = "flex";

            // Push content below the bar
            document.body.classList.add('gc-call-active');
        },

        _updateBar: function (phoneText, status) {
            var ph = document.getElementById("gcCallPhone");
            var st = document.getElementById("gcCallStatus");
            if (ph && phoneText !== undefined) ph.textContent = phoneText;
            if (st && status    !== undefined) st.textContent = status;
        },

        _updatePhone: function (phone) {
            if (!phone) return;
            if (this._state) this._state.phone = phone;
            var ph = document.getElementById("gcCallPhone");
            if (ph && ph.textContent) ph.textContent = phone;
        },

        _hideBar: function () {
            var bar = document.getElementById("gcCallBar");
            if (bar) bar.style.display = "none";
            this._setCallLeadLink(null);
            document.body.classList.remove('gc-call-active');
        },

        _setCallLeadLink: function (url) {
            var link = document.getElementById("gcCallLeadLink");
            if (!link) return;
            if (url) {
                link.href           = url;
                link.style.display  = "inline-block";
            } else {
                link.removeAttribute("href");
                link.style.display  = "none";
            }
        },

        // ── Timer ──────────────────────────────────────────────────
        _startTimer: function (start) {
            this._stopTimer();
            var el = document.getElementById("gcCallTimer");

            this._timerInterval = setInterval(function () {
                var sec = Math.floor((Date.now() - start) / 1000);
                var m   = Math.floor(sec / 60);
                var s   = sec % 60;
                if (el) el.textContent = m + ":" + (s < 10 ? "0" : "") + s;
            }, 1000);
        },

        _stopTimer: function () {
            clearInterval(this._timerInterval);
            this._timerInterval = null;
            var el = document.getElementById("gcCallTimer");
            if (el) el.textContent = "0:00";
        }

    };

    // ── Bootstrap ─────────────────────────────────────────────────
    var metaCsrf = document.querySelector('meta[name="csrf-token"]');
    if (metaCsrf) {
        GC._csrf = metaCsrf.getAttribute("content");
    }

    window.GC = GC;

})();
