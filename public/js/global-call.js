/**
 * global-call.js — Persistent Twilio Call Manager
 * Loaded in every layout. Intercepts navigation during active calls.
 * Pages with call buttons call GC.initDevice() + GC.startCall().
 */
(function () {
    'use strict';

    var GC = {
        _device: null,
        _call: null,
        _state: null,      // { callLogId, phone, leadId, answeredAt }
        _csrf: null,
        _timerInterval: null,
        _manualHangup: false,
        _endReported: false,

        /** Fetch Twilio token and initialize Device. Safe to call on each page that needs it. */
        initDevice: async function () {
            if (this._device) return;
            try {
                var res = await fetch('/twilio/token');
                var data = await res.json();
                var self = this;
                this._device = new window.TwilioDevice(data.token, {
                    codecPreferences: ['opus', 'pcmu'],
                    enableRingingState: true
                });
                this._device.on('registered', function () { console.log('[GC] Twilio ready'); });
                this._device.on('error', function (err) { console.error('[GC] Error', err); });
            } catch (e) {
                console.error('[GC] initDevice error', e);
            }
        },

        /**
         * Start a Twilio call.
         * Dispatches gc:callRinging, gc:callAccepted, gc:callEnded on document.
         */
        startCall: async function (phone, leadId) {
            if (!this._device) {
                alert('Call system not ready. Please wait a moment and try again.');
                return;
            }
            if (this._state) {
                alert('A call is already in progress.');
                return;
            }

            this._manualHangup = false;
            this._endReported = false;

            var logRes = await fetch('/call/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf },
                body: JSON.stringify({ lead_id: leadId })
            });
            var logData = await logRes.json();

            this._state = { callLogId: logData.call_log_id, phone: phone, leadId: leadId, answeredAt: null };
            document.dispatchEvent(new CustomEvent('gc:callRinging', { detail: { phone: phone } }));

            await navigator.mediaDevices.getUserMedia({ audio: true });

            var self = this;
            this._call = await this._device.connect({
                params: { To: phone, call_log_id: this._state.callLogId }
            });

            this._call.on('accept', function () {
                self._state.answeredAt = Date.now();
                fetch('/call/update-sid', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': self._csrf },
                    body: JSON.stringify({
                        call_log_id: self._state.callLogId,
                        call_sid: self._call.parameters.CallSid
                    })
                });
                self._showBar(phone);
                self._startTimer(self._state.answeredAt);
                document.dispatchEvent(new CustomEvent('gc:callAccepted', { detail: { phone: phone } }));
            });

            this._call.on('disconnect', function () { self._finalize(); });
            this._call.on('cancel', function () { self._finalize(); });
            this._call.on('reject', function () { self._finalize(); });
        },

        /** Manually end the active call. */
        endCall: function () {
            if (this._call) {
                this._manualHangup = true;
                this._call.disconnect();
            }
        },

        isActive: function () { return !!this._state; },

        _finalize: async function () {
            if (this._endReported) return;
            this._endReported = true;

            var wasAnswered = !!(this._state && this._state.answeredAt);
            var duration = wasAnswered ? Math.max(0, Math.floor((Date.now() - this._state.answeredAt) / 1000)) : 0;
            var endedBy = this._manualHangup ? 'telecaller' : 'customer';
            var finalStatus = wasAnswered ? 'completed' : (this._manualHangup ? 'canceled' : 'no-answer');
            var logId = this._state ? this._state.callLogId : null;
            var phone = this._state ? this._state.phone : '';

            this._stopTimer();
            this._hideBar();

            if (logId) {
                await fetch('/call/end', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf },
                    body: JSON.stringify({
                        call_log_id: logId,
                        ended_by: endedBy,
                        final_status: finalStatus,
                        end_reason: finalStatus,
                        duration: duration
                    })
                });
            }

            this._call = null;
            this._state = null;
            this._manualHangup = false;
            document.dispatchEvent(new CustomEvent('gc:callEnded', { detail: { phone: phone, callLogId: logId } }));
        },

        _showBar: function (phone) {
            var bar = document.getElementById('gcCallBar');
            var phoneEl = document.getElementById('gcCallPhone');
            if (phoneEl) phoneEl.textContent = phone;
            if (bar) bar.style.display = 'flex';
        },

        _hideBar: function () {
            var bar = document.getElementById('gcCallBar');
            if (bar) bar.style.display = 'none';
        },

        _startTimer: function (startAtMs) {
            var timerEl = document.getElementById('gcCallTimer');
            this._timerInterval = setInterval(function () {
                var secs = Math.floor((Date.now() - startAtMs) / 1000);
                var m = Math.floor(secs / 60);
                var s = secs % 60;
                if (timerEl) timerEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            }, 1000);
        },

        _stopTimer: function () {
            clearInterval(this._timerInterval);
            var timerEl = document.getElementById('gcCallTimer');
            if (timerEl) timerEl.textContent = '0:00';
        },

        _setupNavIntercept: function () {
            var self = this;

            // Intercept all link clicks during an active call (capture phase)
            document.addEventListener('click', function (e) {
                if (!self.isActive()) return;
                var link = e.target.closest('a[href]');
                if (!link) return;
                var href = link.getAttribute('href');
                if (!href || href === '#' || href.startsWith('javascript:') || href.charAt(0) === '#') return;
                e.preventDefault();
                e.stopPropagation();
                self._showNavWarning(href);
            }, true);

            // Warn on browser-level navigation (refresh, back button, address bar, tab close)
            window.addEventListener('beforeunload', function (e) {
                if (!self.isActive()) return;
                e.preventDefault();
                e.returnValue = 'A call is in progress. Leaving this page will end the call.';
            });

            // Wire up the End & Navigate button in the warning modal
            var proceedBtn = document.getElementById('gcNavProceedBtn');
            if (proceedBtn) {
                proceedBtn.addEventListener('click', function () {
                    var url = proceedBtn.dataset.targetUrl;
                    var modal = document.getElementById('gcNavWarningModal');
                    if (modal && window.bootstrap) {
                        var inst = bootstrap.Modal.getInstance(modal);
                        if (inst) inst.hide();
                    }
                    self.endCall();
                    setTimeout(function () { if (url) window.location.href = url; }, 600);
                });
            }

            // Wire up End Call button on the global bar
            var barEndBtn = document.getElementById('gcBarEndBtn');
            if (barEndBtn) {
                barEndBtn.addEventListener('click', function () { self.endCall(); });
            }
        },

        _showNavWarning: function (targetUrl) {
            var modal = document.getElementById('gcNavWarningModal');
            var proceedBtn = document.getElementById('gcNavProceedBtn');

            if (!modal) {
                if (confirm('A call is in progress. End call and navigate away?')) {
                    this.endCall();
                    var self = this;
                    setTimeout(function () { window.location.href = targetUrl; }, 600);
                }
                return;
            }

            if (proceedBtn) proceedBtn.dataset.targetUrl = targetUrl;
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    };

    // Auto-read CSRF token from meta tag
    var metaCsrf = document.querySelector('meta[name="csrf-token"]');
    if (metaCsrf) GC._csrf = metaCsrf.getAttribute('content');

    // Setup navigation intercept (script is at bottom of body — DOM is ready)
    GC._setupNavIntercept();

    window.GC = GC;
})();
