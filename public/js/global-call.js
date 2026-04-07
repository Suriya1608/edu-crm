/**
 * global-call.js - Provider-aware call manager (Twilio + Exotel VOIP)
 */

(function () {
"use strict";

var metaProvider = document.querySelector('meta[name="call-provider"]');
var PROVIDER = metaProvider ? metaProvider.getAttribute("content") : "twilio";


var GC = {

_device: null,
_call: null,
_deviceInitPromise: null,
_deviceInitialized: false,

_ua: null,
_registered: false,
_session: null,
_incomingVoipSession: null,
_voipConfig: null,

_state: null,
_csrf: null,
_timerInterval: null,
_pollInterval: null,
_manualHangup: false,
_endReported: false,

_pendingUrl: null,
_navInterceptBound: false,
_barEndBtnBound: false,

_pstnPollInterval: null,
_pstnShownCallLogId: null,
_pstnIncomingBtnBound: false,

isActive: function () {
    return !!this._state;
},

initDevice: async function () {
    if (this._deviceInitialized) {
        return;
    }
    if (this._deviceInitPromise) {
        return this._deviceInitPromise;
    }

    var self = this;
    this._deviceInitPromise = (async function () {
    if (PROVIDER === "exotel") {
        await self._initExotel();
        self._startPstnIncomingPoll();
    } else if (PROVIDER === "tcn") {
        await self._initTcn();
    } else {
        await self._initTwilio();
    }

    self._setupNavIntercept();
    self._setupBarEndBtn();
    self._setupPstnIncomingBtns();
    self._deviceInitialized = true;
    })().finally(function () {
        self._deviceInitPromise = null;
    });

    return this._deviceInitPromise;
},

// ── TCN ───────────────────────────────────────────────────────────────────
// TCN calls are driven by a named popup window (/softphone).
// Using a popup (not an iframe) ensures the SIP session and WebRTC audio
// survive parent-page navigations — the popup is never reloaded.
// Parent ↔ popup communication uses window.postMessage.
// ─────────────────────────────────────────────────────────────────────────

_tcnEventsWired: false,
_pendingLeadId:  null,

// Return the embedded softphone iframe (present in both layouts).
_tcnFrame: function () {
    return document.getElementById('tcnSoftphoneFrame');
},

// Show the iframe. Called on call start so the widget is visible.
_showTcnFrame: function () {
    var f = this._tcnFrame();
    if (f && f.style.display === 'none') {
        f.style.display = 'block';
        f.style.bottom  = '80px';
    }
},

// No-op shims — popup approach removed; iframe is used instead.
_tcnPopup:     function () { return null; },
_openTcnPopup: function () { return null; },

_initTcn: async function () {
    var self = this;

    if (self._tcnEventsWired) return;
    self._tcnEventsWired = true;

    // Receive status messages from the softphone iframe.
    window.addEventListener("message", function (ev) {
        var d = ev.data;
        if (!d || typeof d !== "object") return;

        switch (d.type) {

            case "TCN_CALL_STARTED":
                self._endReported = false;
                self._state = {
                    callLogId:  d.callLogId  || null,
                    phone:      d.phone      || "",
                    leadId:     self._pendingLeadId || null,
                    leadName:   null,
                    leadUrl:    null,
                    answeredAt: null,
                };
                self._pendingLeadId = null;
                self._showBar("Connecting\u2026");
                self._startTimer(Date.now());
                document.dispatchEvent(new CustomEvent("gc:callAccepted"));
                break;

            case "TCN_CALL_ANSWERED":
                if (self._state) {
                    self._state.answeredAt = Date.now();
                    self._showBar(self._state.phone || d.phone || "");
                    self._startTimer(self._state.answeredAt);
                }
                break;

            case "TCN_CALL_ENDED":
                self._finalize(d.status || "completed");
                break;

            case "TCN_ERROR":
                console.error("[GC-TCN]", d.message);
                if (self._state && !self._endReported) {
                    self._finalize("failed");
                }
                break;
        }
    });
},

// Manager: turn ON calling mode — show the softphone iframe.
enableCallingMode: async function () {
    if (PROVIDER !== "tcn") return;
    this._showTcnFrame();
    console.log("[GC-TCN] Calling mode enabled.");
},

// Manager: turn OFF calling mode — tell softphone iframe to logout.
disableCallingMode: function () {
    var f = this._tcnFrame();
    if (f && f.contentWindow) f.contentWindow.postMessage({ type: "LOGOUT" }, "*");
    console.log("[GC-TCN] Calling mode disabled.");
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
    f.contentWindow.postMessage({ type: "CALL", phone: phone }, "*");
    return Promise.resolve();
},

// ─────────────────────────────────────────────────────────────────────────

_initTwilio: async function () {
    if (this._device) return;

    try {
        var res = await fetch("/twilio/token");
        var data = await res.json();
        this._device = new window.TwilioDevice(data.token);
    } catch (e) {
        console.error("Twilio init error", e);
    }
},

_loadJsSIP: function () {
    if (typeof JsSIP !== "undefined") return Promise.resolve();

    return new Promise(function (resolve, reject) {
        var s = document.createElement("script");
        s.src = "/js/jssip.min.js";
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });
},

_initExotel: async function () {
    if (this._ua) return;

    try {
        await this._loadJsSIP();
    } catch (e) {
        console.error("JsSIP load failed", e);
        return;
    }

    try {

        const res = await fetch("/settings/voip");
        const cfg = await res.json();
        console.log("Using TEST VOIP CONFIG:", cfg);

        if (!cfg.enabled) {
            console.warn("Exotel VOIP disabled");
            return;
        }

        this._voipConfig = cfg;

        const socket = new JsSIP.WebSocketInterface("wss://" + cfg.proxy);

        this._ua = new JsSIP.UA({
            sockets: [socket],
           uri: "sip:" + cfg.username + "@" + cfg.domain,
            password: cfg.password,
            register: true,
            session_timers: false
        });

        const self = this;

        this._ua.on("registered", function () {
            self._registered = true;
            console.log("✅ Exotel SIP Registered");
        });

        this._ua.on("registrationFailed", function (e) {
            console.error("❌ SIP registration failed:", e.cause);
        });

        this._ua.on("newRTCSession", function (data) {

            if (data.originator === "remote") {
                self._incomingCall(data.session);
            }

        });

        this._ua.start();

    } catch (e) {
        console.error("VOIP init error", e);
    }
},

_waitForRegister: function (timeout) {
    var self = this;

    if (this._registered) return Promise.resolve();
    if (!this._ua) return Promise.reject("SIP not initialized");

    return new Promise(function (resolve, reject) {
        var t = setTimeout(function () {
            reject("SIP registration timeout");
        }, timeout || 10000);

        self._ua.once("registered", function () {
            clearTimeout(t);
            resolve();
        });
    });
},

startCall: async function (phone, leadId) {
    if (this._state) {
        alert("Call already active");
        return;
    }

    await this.initDevice();

    if (PROVIDER === "tcn") {
        // Return the promise so callers can await it and catch errors.
        // Without this return the button click handler's catch() never fires,
        // leaving the button stuck on "Connecting..." after a failed dial.
        return this._startTcnCall(phone, leadId);
    } else if (PROVIDER === "exotel") {
        this._startExotelCall(phone, leadId);
    } else {
        this._startTwilioCall(phone, leadId);
    }
},

_startTwilioCall: async function (phone, leadId) {
    if (!this._device) {
        await this._initTwilio();
    }

    if (!this._device) {
        alert("Twilio not ready. Please refresh and try again.");
        return;
    }

    var logRes = await fetch("/call/start", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": this._csrf
        },
        body: JSON.stringify({ lead_id: leadId })
    });

    var log = await logRes.json();

    this._state = {
        callLogId: log.call_log_id,
        phone: phone,
        leadId: leadId,
        leadName: null,
        leadUrl: null,
        answeredAt: null
    };

    this._showBar("Connecting...");

    var self = this;

    try {
        this._call = await this._device.connect({
            params: { To: phone, LeadId: leadId }
        });

        this._call.on("accept", function () {
            self._markAnswered(phone);
        });

        this._call.on("disconnect", function () {
            self._finalize("completed");
        });

        this._call.on("error", function () {
            self._finalize("failed");
        });
    } catch (e) {
        this._finalize("failed");
    }
},

_startExotelCall: async function (phone, leadId) {

    if (!this._ua) {
        await this._initExotel();
    }

    if (!this._ua) {
        alert("Exotel not initialized");
        return;
    }

    try {

        await this._waitForRegister(15000);

    } catch (e) {

        alert("SIP not registered yet");
        return;

    }

    try {

        await navigator.mediaDevices.getUserMedia({ audio: true });

    } catch (e) {

        alert("Microphone permission required");
        return;

    }

    const res = await fetch("/exotel/voip-call", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": this._csrf
        },
        body: JSON.stringify({
            phone: phone,
            lead_id: leadId
        })
    });

    const log = await res.json();

    if (!log.ok) {

        alert("Call failed");
        return;

    }

    this._state = {
        callLogId: log.call_log_id,
        phone: phone,
        leadId: leadId,
        answeredAt: null
    };

    this._showBar("Calling " + phone);

    const target = log.dial_to
        ? log.dial_to
        : this._buildDialTarget(phone);

    console.log("Dialing:", target);

    const session = this._ua.call(target, {
        mediaConstraints: { audio: true, video: false }
    });

    this._session = session;

    this._attachOutboundSession(session);

},

endCall: function () {
    this._manualHangup = true;

    if (PROVIDER === "tcn") {
        var f = this._tcnFrame();
        if (f && f.contentWindow) {
            f.contentWindow.postMessage({ type: "HANGUP" }, "*");
        } else if (window.TCN && window.TCN._callActive) {
            window.TCN.endCall();
        }
        return;
    }

    if (this._call) {
        this._call.disconnect();
    } else if (this._session) {
        this._session.terminate();
    } else if (this._incomingVoipSession) {
        this._incomingVoipSession.terminate();
    } else if (this._state) {
        this._finalize("canceled");
    }
},

_finalize: async function (status) {
    if (this._endReported) return;

    this._endReported = true;

    var duration = this._state && this._state.answeredAt
        ? Math.floor((Date.now() - this._state.answeredAt) / 1000)
        : 0;

    var logId = this._state ? this._state.callLogId : null;
    var phone = this._state ? this._state.phone : null;
    var endedBy = this._manualHangup ? "telecaller" : "unknown";

    this._stopExotelStatusPoll();
    this._stopTimer();
    this._hideBar();

    if (logId) {
        await fetch("/call/end", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": this._csrf
            },
            body: JSON.stringify({
                call_log_id: logId,
                duration: duration,
                final_status: status,
                ended_by: endedBy
            })
        });
    }

    this._call = null;
    this._session = null;
    this._incomingVoipSession = null;
    this._state = null;
    this._endReported = false;
    this._manualHangup = false;

    document.dispatchEvent(new CustomEvent("gc:callEnded", {
        detail: { callLogId: logId, phone: phone }
    }));
},

_attachOutboundSession: function (session) {
    var self = this;

    session.on("progress", function () {
        self._updateBar("Ringing " + self._state.phone + "...");
        self._syncCallSidFromSession(session, self._state.callLogId);
    });

    session.on("accepted", function () {
        self._syncCallSidFromSession(session, self._state.callLogId);
        self._markAnswered(self._state.phone);
    });

    session.on("confirmed", function () {
        self._syncCallSidFromSession(session, self._state.callLogId);
        if (!self._state.answeredAt) {
            self._markAnswered(self._state.phone);
        }
    });

    session.on("ended", function () {
        self._finalize("completed");
    });

    session.on("failed", function (e) {
        var cause = e && e.cause ? String(e.cause).toLowerCase() : "";
        var finalStatus = "failed";

        if (cause.indexOf("busy") !== -1) {
            finalStatus = "busy";
        } else if (cause.indexOf("cancel") !== -1 || cause.indexOf("reject") !== -1) {
            finalStatus = self._manualHangup ? "canceled" : "failed";
        } else if (cause.indexOf("no answer") !== -1 || cause.indexOf("unavailable") !== -1) {
            finalStatus = "no-answer";
        }

        self._finalize(finalStatus);
    });
},

_incomingCall: function (session) {
    var self = this;
    var caller = this._extractSessionNumber(session);

    this._incomingVoipSession = session;
    this._showIncoming(caller);
    this._registerIncomingSession(caller, this._extractSessionCallSid(session));

    session.on("accepted", function () {
        self._syncCallSidFromSession(session, self._currentIncomingCallLogId());
    });

    session.on("confirmed", function () {
        var activeLabel = self._currentIncomingLabel();
        var activeCallLogId = self._currentIncomingCallLogId();
        var incomingMeta = self._currentIncomingMeta();

        self._session = session;
        self._incomingVoipSession = null;
        self._hideIncoming();
        self._state = {
            callLogId: activeCallLogId,
            phone: incomingMeta.phone || activeLabel,
            leadId: null,
            leadName: incomingMeta.leadName,
            leadUrl: incomingMeta.leadUrl,
            answeredAt: Date.now()
        };

        self._showBar(activeLabel);
        self._startTimer(self._state.answeredAt);
        document.dispatchEvent(new CustomEvent("gc:callAccepted"));
    });

    session.on("ended", function () {
        var pendingCallLogId = self._currentIncomingCallLogId();
        self._incomingVoipSession = null;
        self._hideIncoming();

        if (self._session === session) {
            self._finalize("completed");
        } else if (pendingCallLogId) {
            fetch("/call/end", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": self._csrf
                },
                body: JSON.stringify({
                    call_log_id: pendingCallLogId,
                    final_status: self._manualHangup ? "canceled" : "no-answer",
                    ended_by: self._manualHangup ? "telecaller" : "unknown",
                    duration: 0
                })
            });
        }
    });

    session.on("failed", function (e) {
        var pendingCallLogId = self._currentIncomingCallLogId();
        self._incomingVoipSession = null;
        self._hideIncoming();

        if (self._session === session) {
            self._finalize("failed");
            return;
        }

        if (pendingCallLogId) {
            fetch("/call/end", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": self._csrf
                },
                body: JSON.stringify({
                    call_log_id: pendingCallLogId,
                    final_status: self._mapFailedCauseToStatus(e),
                    ended_by: self._manualHangup ? "telecaller" : "unknown",
                    duration: 0
                })
            });
        }
    });
},

_registerIncomingSession: async function (phone, callSid) {
    try {
        var res = await fetch("/exotel/browser-incoming", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": this._csrf,
                "Accept": "application/json"
            },
            body: JSON.stringify({
                phone: phone,
                call_sid: callSid
            })
        });

        if (!res.ok) return;

        var data = await res.json();
        if (!data || !data.ok) return;

        this._pstnShownCallLogId = data.call_log_id;
        this._showPstnIncoming({
            callLogId: data.call_log_id,
            phone: data.phone,
            leadName: data.lead_name || null,
            leadUrl: data.lead_url || null,
            label: data.lead_name ? data.lead_name + " • " + data.phone : data.phone
        });
    } catch (e) {
        console.error("Unable to register incoming SIP session", e);
    }
},

_startPstnIncomingPoll: function () {
    if (this._pstnPollInterval) return;

    var self = this;

    this._pstnPollInterval = setInterval(async function () {
        try {
            var res = await fetch("/exotel/incoming-poll");
            var data = await res.json();

            if (data.has_incoming) {
                if (data.call_log_id === self._pstnShownCallLogId) return;

                self._pstnShownCallLogId = data.call_log_id;
                self._showPstnIncoming({
                    callLogId: data.call_log_id,
                    phone: data.phone,
                    leadName: data.lead_name || null,
                    leadUrl: data.lead_url || null,
                    label: data.lead_name ? data.lead_name + " • " + data.phone : data.phone
                });
            } else if (self._pstnShownCallLogId && !self._incomingVoipSession) {
                self._pstnShownCallLogId = null;
                self._hidePstnIncoming();
            }
        } catch (e) {
            // ignore transient errors
        }
    }, 5000);
},

_showPstnIncoming: function (payload) {
    var el = document.getElementById("gcIncomingBar");
    var ph = document.getElementById("gcIncomingPhone");
    var callLogId = payload && payload.callLogId ? payload.callLogId : "";
    var label = payload && payload.label ? payload.label : "";

    if (ph) ph.textContent = label;

    if (el) {
        el.setAttribute("data-call-log-id", callLogId || "");
        el.setAttribute("data-lead-name", payload && payload.leadName ? payload.leadName : "");
        el.setAttribute("data-lead-url", payload && payload.leadUrl ? payload.leadUrl : "");
        el.setAttribute("data-phone", payload && payload.phone ? payload.phone : "");
        el.style.display = "flex";
    }

    this._setIncomingLeadLink(payload && payload.leadUrl ? payload.leadUrl : null);

    document.dispatchEvent(new CustomEvent("gc:incomingCall", {
        detail: {
            callLogId: callLogId || null,
            phone: payload && payload.phone ? payload.phone : null,
            leadName: payload && payload.leadName ? payload.leadName : null,
            leadUrl: payload && payload.leadUrl ? payload.leadUrl : null
        }
    }));
},

_hidePstnIncoming: function () {
    var el = document.getElementById("gcIncomingBar");
    if (el) el.style.display = "none";
    this._setIncomingLeadLink(null);
},

_setupPstnIncomingBtns: function () {
    if (this._pstnIncomingBtnBound) return;

    var answerBtn = document.getElementById("gcIncomingAnswerBtn");
    var rejectBtn = document.getElementById("gcIncomingRejectBtn");

    if (!answerBtn && !rejectBtn) return;

    this._pstnIncomingBtnBound = true;

    var self = this;

    if (answerBtn) {
        answerBtn.addEventListener("click", function () {
            if (self._incomingVoipSession) {
                self._incomingVoipSession.answer({
                    mediaConstraints: { audio: true, video: false }
                });
                return;
            }

            self._pstnShownCallLogId = null;
            self._hidePstnIncoming();
        });
    }

    if (rejectBtn) {
        rejectBtn.addEventListener("click", function () {
            if (self._incomingVoipSession) {
                self._incomingVoipSession.terminate();
                self._incomingVoipSession = null;
            }

            self._pstnShownCallLogId = null;
            self._hidePstnIncoming();
        });
    }
},

_setupNavIntercept: function () {
    if (this._navInterceptBound) return;
    this._navInterceptBound = true;

    var self = this;

    document.addEventListener("click", function (e) {
        if (!self.isActive()) return;

        var link = e.target.closest("a[href]");
        if (!link) return;

        var href = link.getAttribute("href");
        if (!href || href === "#" || href.startsWith("javascript:")) return;

        e.preventDefault();
        self._pendingUrl = href;

        var modal = document.getElementById("gcNavWarningModal");
        if (modal && typeof bootstrap !== "undefined") {
            new bootstrap.Modal(modal).show();
        }
    });

    var proceedBtn = document.getElementById("gcNavProceedBtn");
    if (proceedBtn) {
        proceedBtn.addEventListener("click", function () {
            var url = GC._pendingUrl;
            GC._pendingUrl = null;
            GC.endCall();
            setTimeout(function () {
                window.location.href = url;
            }, 300);
        });
    }
},

_setupBarEndBtn: function () {
    if (this._barEndBtnBound) return;

    var endBtn = document.getElementById("gcBarEndBtn");
    if (!endBtn) return;

    this._barEndBtnBound = true;
    endBtn.addEventListener("click", function () {
        GC.endCall();
    });
},

_normalize: function (phone) {
    if (!phone) return phone;

    var d = String(phone).replace(/\D/g, "");

    if (d.length === 10) return "91" + d;
    if (d.length === 11 && d.startsWith("0")) return "91" + d.substring(1);
    if (d.startsWith("91")) return d;

    return d;
},

_sanitizeProxyHost: function (proxy) {
    var host = String(proxy || "voip.in1.exotel.com").trim();
    host = host.replace(/^wss?:\/\//i, "");
    host = host.replace(/\/+$/, "");
    host = host.replace(/:443$/, "");
    return host || "voip.in1.exotel.com";
},

// _buildDialTarget: function (phone) {
//     var normalized = this._normalize(phone);
//     var domain = this._voipConfig && this._voipConfig.domain ? this._voipConfig.domain : "";
//     return "sip:" + normalized + "@" + domain;
// },
_buildDialTarget: function (phone) {

    var normalized = this._normalize(phone);
    var domain = this._voipConfig.domain;

    return "sip:" + normalized + "@" + domain;

},

_extractSessionNumber: function (session) {
    try {
        var user = session && session.remote_identity && session.remote_identity.uri
            ? session.remote_identity.uri.user
            : "";

        if (!user) return "Incoming call";

        return this._normalize(user) || user;
    } catch (e) {
        return "Incoming call";
    }
},

_extractSessionCallSid: function (session) {
    try {
        return session && session.request && session.request.call_id
            ? session.request.call_id
            : null;
    } catch (e) {
        return null;
    }
},

_syncCallSidFromSession: function (session, callLogId) {
    var callSid = this._extractSessionCallSid(session);
    if (!callSid || !callLogId) return;

    fetch("/call/update-sid", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": this._csrf
        },
        body: JSON.stringify({
            call_log_id: callLogId,
            call_sid: callSid
        })
    });
},

_mapFailedCauseToStatus: function (event) {
    var cause = event && event.cause ? String(event.cause).toLowerCase() : "";

    if (cause.indexOf("busy") !== -1) return "busy";
    if (cause.indexOf("no answer") !== -1 || cause.indexOf("unavailable") !== -1) return "no-answer";
    if (cause.indexOf("cancel") !== -1 || cause.indexOf("reject") !== -1) return this._manualHangup ? "canceled" : "failed";

    return "failed";
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

_currentIncomingLabel: function () {
    var ph = document.getElementById("gcIncomingPhone");
    return ph && ph.textContent ? ph.textContent : "Incoming call";
},

_currentIncomingCallLogId: function () {
    var el = document.getElementById("gcIncomingBar");
    var raw = el ? el.getAttribute("data-call-log-id") : null;
    return raw ? parseInt(raw, 10) : null;
},

_currentIncomingMeta: function () {
    var el = document.getElementById("gcIncomingBar");
    return {
        leadName: el ? (el.getAttribute("data-lead-name") || null) : null,
        leadUrl: el ? (el.getAttribute("data-lead-url") || null) : null,
        phone: el ? (el.getAttribute("data-phone") || null) : null
    };
},

_showBar: function (text) {
    var el = document.getElementById("gcCallBar");
    var ph = document.getElementById("gcCallPhone");

    if (ph) ph.textContent = text;
    this._setCallLeadLink(this._state && this._state.leadUrl ? this._state.leadUrl : null);
    if (el) el.style.display = "flex";
},

_updateBar: function (text) {
    var ph = document.getElementById("gcCallPhone");
    if (ph) ph.textContent = text;
},

_hideBar: function () {
    var el = document.getElementById("gcCallBar");
    if (el) el.style.display = "none";
    this._setCallLeadLink(null);
},

_showIncoming: function (phone) {
    var el = document.getElementById("gcIncomingBar");
    var ph = document.getElementById("gcIncomingPhone");

    if (ph) ph.textContent = phone;
    this._setIncomingLeadLink(this._currentIncomingMeta().leadUrl);
    if (el) el.style.display = "flex";
},

_hideIncoming: function () {
    var el = document.getElementById("gcIncomingBar");

    if (el) {
        el.style.display = "none";
        el.removeAttribute("data-call-log-id");
        el.removeAttribute("data-lead-name");
        el.removeAttribute("data-lead-url");
        el.removeAttribute("data-phone");
    }

    this._setIncomingLeadLink(null);
},

_setCallLeadLink: function (url) {
    var link = document.getElementById("gcCallLeadLink");
    if (!link) return;

    if (url) {
        link.href = url;
        link.style.display = "inline-block";
    } else {
        link.removeAttribute("href");
        link.style.display = "none";
    }
},

_setIncomingLeadLink: function (url) {
    var link = document.getElementById("gcIncomingLeadLink");
    if (!link) return;

    if (url) {
        link.href = url;
        link.style.display = "inline-block";
    } else {
        link.removeAttribute("href");
        link.style.display = "none";
    }
},

_startTimer: function (start) {
    this._stopTimer();

    var el = document.getElementById("gcCallTimer");

    this._timerInterval = setInterval(function () {
        var sec = Math.floor((Date.now() - start) / 1000);
        var m = Math.floor(sec / 60);
        var s = sec % 60;

        if (el) el.textContent = m + ":" + (s < 10 ? "0" : "") + s;
    }, 1000);
},

_stopTimer: function () {
    clearInterval(this._timerInterval);
    this._timerInterval = null;

    var el = document.getElementById("gcCallTimer");
    if (el) el.textContent = "0:00";
},

_startExotelStatusPoll: function () {
    var self = this;

    this._pollInterval = setInterval(async function () {
        if (!self._state) {
            self._stopExotelStatusPoll();
            return;
        }

        try {
            var res = await fetch("/exotel/status/" + self._state.callLogId);
            var data = await res.json();

            if (data.status === "in-progress" || data.status === "answered") {
                self._stopExotelStatusPoll();
                self._state.answeredAt = data.answered_at
                    ? new Date(data.answered_at).getTime()
                    : Date.now();
                self._updateBar(self._state.phone);
                self._startTimer(self._state.answeredAt);
                document.dispatchEvent(new CustomEvent("gc:callAccepted"));
                return;
            }

            if (["completed", "no-answer", "failed", "busy", "canceled", "missed"].indexOf(data.status) !== -1) {
                self._stopExotelStatusPoll();
                if (!self._endReported) {
                    self._finalize(data.status === "missed" ? "no-answer" : data.status);
                }
            }
        } catch (e) {
            // ignore transient errors
        }
    }, 3000);
},

_stopExotelStatusPoll: function () {
    clearInterval(this._pollInterval);
    this._pollInterval = null;
}

};

var metaCsrf = document.querySelector('meta[name="csrf-token"]');
if (metaCsrf) {
    GC._csrf = metaCsrf.getAttribute("content");
}

window.GC = GC;

})();
