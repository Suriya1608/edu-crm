/**
 * global-call.js - TCN-only call manager.
 */
(function () {
"use strict";

var metaProvider = document.querySelector('meta[name="call-provider"]');
var provider = metaProvider ? String(metaProvider.getAttribute("content") || "").toLowerCase() : "tcn";

if (provider && provider !== "tcn") {
    console.warn("[GC] Non-TCN provider configured, forcing TCN runtime:", provider);
}

var GC = {
    _state: null,
    _deviceInitialized: false,
    _deviceInitPromise: null,
    _tcnEventsWired: false,
    _pendingLeadId: null,
    _pendingUrl: null,
    _navInterceptBound: false,
    _barEndBtnBound: false,
    _timerInterval: null,

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
            await self._initTcn();
            self._setupNavIntercept();
            self._setupBarEndBtn();
            self._deviceInitialized = true;
        })().finally(function () {
            self._deviceInitPromise = null;
        });

        return this._deviceInitPromise;
    },

    _initTcn: async function () {
        if (this._tcnEventsWired) {
            return;
        }

        var self = this;
        this._tcnEventsWired = true;

        document.addEventListener("tcn:state-sync", function (ev) {
            self._syncTcnState(ev.detail || {});
        });

        document.addEventListener("tcn:tcn-call-started", function (ev) {
            var d = ev.detail || {};
            self._state = {
                callLogId: d.callLogId || null,
                phone: d.phone || "",
                leadId: self._pendingLeadId || null,
                answeredAt: null
            };
            self._pendingLeadId = null;
            self._showBar("Ringing...");
            self._stopTimer();
        });

        document.addEventListener("tcn:tcn-call-answered", function (ev) {
            var d = ev.detail || {};
            if (!self._state) {
                self._state = {
                    callLogId: d.callLogId || null,
                    phone: d.phone || "",
                    leadId: self._pendingLeadId || null,
                    answeredAt: Date.now()
                };
                self._pendingLeadId = null;
            }
            if (!self._state.answeredAt) {
                self._state.answeredAt = Date.now();
            }
            self._updateBar(self._state.phone || d.phone || "");
            self._startTimer(self._state.answeredAt);
        });

        document.addEventListener("tcn:tcn-call-ended", function (ev) {
            var d = ev.detail || {};
            self._finalize(d.callLogId || null, d.phone || null);
        });

        document.addEventListener("tcn:tcn-error", function () {
            self._finalize();
        });

        document.addEventListener("gc:softphoneNotReady", function (ev) {
            var msg = (ev.detail && ev.detail.message) || "Initializing softphone...";
            self._flashCallButtons(msg, 2500);
        });
    },

    _syncTcnState: function (snapshot) {
        if (!snapshot || typeof snapshot !== "object") {
            return;
        }

        if (snapshot.state === "calling" || snapshot.state === "on-call" || snapshot.state === "ending") {
            this._state = {
                callLogId: snapshot.callLogId || (this._state && this._state.callLogId) || null,
                phone: snapshot.phone || (this._state && this._state.phone) || "",
                leadId: (this._state && this._state.leadId) || this._pendingLeadId || null,
                answeredAt: snapshot.callEstablishedAt || (this._state && this._state.answeredAt) || null
            };
            this._showBar(snapshot.state === "calling" ? "Ringing..." : (this._state.phone || ""));
            if (this._state.answeredAt) {
                this._startTimer(this._state.answeredAt);
            } else {
                this._stopTimer();
            }
            return;
        }

        if (snapshot.state === "ready" || snapshot.state === "paused" || snapshot.state === "connecting" || snapshot.state === "error") {
            this._finalize(snapshot.callLogId || null, snapshot.phone || null, true);
        }
    },

    startCall: async function (phone, leadId) {
        if (!phone) {
            throw new Error("Phone number is required");
        }

        await this.initDevice();

        this._pendingLeadId = leadId || null;

        if (window.TCNWidget && typeof window.TCNWidget.call === "function") {
            window.TCNWidget.call(phone, leadId || null);
            return;
        }

        if (window.TcnService && typeof window.TcnService.call === "function") {
            await window.TcnService.call(phone, leadId || null);
            return;
        }

        if (window.TCN && typeof window.TCN.startCall === "function") {
            await window.TCN.startCall(phone, leadId || null);
            return;
        }

        throw new Error("TCN runtime is not available on this page.");
    },

    endCall: function () {
        if (window.TCNWidget && typeof window.TCNWidget.end === "function") {
            window.TCNWidget.end();
            return;
        }

        if (window.TCN && typeof window.TCN.hangup === "function") {
            window.TCN.hangup();
            return;
        }

        this._finalize();
    },

    enableCallingMode: async function () {
        await this.initDevice();
        if (window.TCNWidget && typeof window.TCNWidget.open === "function") {
            window.TCNWidget.open();
        } else if (window.TCN && !window.TCN._loggedIn && typeof window.TCN.login === "function") {
            window.TCN.login().catch(function (e) { console.error(e); });
        }
    },

    disableCallingMode: function () {
        if (window.TCNWidget && typeof window.TCNWidget.send === "function") {
            window.TCNWidget.send({ type: "LOGOUT" });
        } else if (window.TCN && typeof window.TCN.logout === "function") {
            window.TCN.logout();
        }
    },

    _finalize: function (callLogId, phone, silent) {
        var prev = this._state;
        this._stopTimer();
        this._hideBar();
        this._state = null;

        if (!silent) {
            document.dispatchEvent(new CustomEvent("gc:callEnded", {
                detail: {
                    callLogId: callLogId || (prev && prev.callLogId) || null,
                    phone: phone || (prev && prev.phone) || null
                }
            }));
        }
    },

    _flashCallButtons: function (msg, durationMs) {
        durationMs = durationMs || 2500;
        var textEls = Array.from(document.querySelectorAll(".call-btn .call-text"));
        var origTexts = textEls.map(function (el) { return el.textContent; });
        textEls.forEach(function (el) { el.textContent = msg; });

        var iconBtns = Array.from(document.querySelectorAll(".integrated-call-btn"));
        var origTitles = iconBtns.map(function (el) { return el.title; });
        iconBtns.forEach(function (el) { el.title = msg; });

        setTimeout(function () {
            textEls.forEach(function (el, i) { el.textContent = origTexts[i]; });
            iconBtns.forEach(function (el, i) { el.title = origTitles[i]; });
        }, durationMs);
    },

    _setupNavIntercept: function () {
        if (this._navInterceptBound) {
            return;
        }
        this._navInterceptBound = true;

        var self = this;
        var modalEl = document.getElementById("gcNavWarningModal");
        var proceedBtn = document.getElementById("gcNavProceedBtn");
        var modal = (window.bootstrap && modalEl) ? new window.bootstrap.Modal(modalEl) : null;

        document.addEventListener("click", function (ev) {
            if (!self.isActive()) {
                return;
            }

            var link = ev.target && ev.target.closest ? ev.target.closest("a[href]") : null;
            if (!link) {
                return;
            }

            var href = link.getAttribute("href");
            if (!href || href === "#" || href.startsWith("javascript:")) {
                return;
            }

            if (link.target === "_blank" || link.hasAttribute("download")) {
                return;
            }

            ev.preventDefault();
            self._pendingUrl = link.href;

            if (modal) {
                modal.show();
            }
        }, true);

        if (proceedBtn) {
            proceedBtn.addEventListener("click", function () {
                var url = self._pendingUrl;
                self._pendingUrl = null;
                self.endCall();
                if (modal) {
                    modal.hide();
                }
                if (url) {
                    window.location.href = url;
                }
            });
        }
    },

    _setupBarEndBtn: function () {
        if (this._barEndBtnBound) {
            return;
        }
        this._barEndBtnBound = true;

        var self = this;
        var endBtn = document.getElementById("gcCallEndBtn");
        if (!endBtn) {
            return;
        }

        endBtn.addEventListener("click", function () {
            self.endCall();
        });
    },

    _showBar: function (text) {
        var el = document.getElementById("gcCallBar");
        var ph = document.getElementById("gcCallPhone");
        if (ph) {
            ph.textContent = text;
        }
        if (el) {
            el.style.display = "flex";
        }
    },

    _updateBar: function (text) {
        var ph = document.getElementById("gcCallPhone");
        if (ph) {
            ph.textContent = text;
        }
    },

    _hideBar: function () {
        var el = document.getElementById("gcCallBar");
        if (el) {
            el.style.display = "none";
        }
    },

    _startTimer: function (start) {
        this._stopTimer();
        var el = document.getElementById("gcCallTimer");

        this._timerInterval = setInterval(function () {
            var sec = Math.floor((Date.now() - start) / 1000);
            var m = Math.floor(sec / 60);
            var s = sec % 60;
            if (el) {
                el.textContent = m + ":" + (s < 10 ? "0" : "") + s;
            }
        }, 1000);
    },

    _stopTimer: function () {
        clearInterval(this._timerInterval);
        this._timerInterval = null;
        var el = document.getElementById("gcCallTimer");
        if (el) {
            el.textContent = "0:00";
        }
    }
};

window.GC = GC;
})();
