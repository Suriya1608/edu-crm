/**
 * tcn-widget.js — v5.0  iframe postMessage bridge
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * ARCHITECTURE (v5)
 * ──────────────────
 * tcn-softphone.js runs ONLY inside <iframe id="sipClientFrame"> (/sip-client).
 * This widget is loaded in the parent CRM layout and bridges:
 *
 *   iframe → postMessage → window.message listener here
 *          → document CustomEvents → global-call.js
 *
 *   global-call.js / .call-btn → TCNWidget.call()
 *          → postMessage → iframe → window.TCN.startCall()
 *
 * A minimal window.TCN proxy is kept in sync so that global-call.js
 * readiness checks (window.TCN.isReady, window.TCN._loggedIn) continue
 * to work without modification.
 *
 * Singleton guard: the IIFE exits immediately if already loaded.
 */
(function (window, document) {
    'use strict';

    if (window.__tcnWidgetV5) return;
    window.__tcnWidgetV5 = true;

    // ── Internal state ────────────────────────────────────────────────────────
    var _frame   = null;   // <iframe id="sipClientFrame"> element
    var _wrapper = null;   // <div id="sipClientWrapper"> element
    var _snap    = null;   // last known state snapshot from iframe
    var _origin  = window.location.origin;

    // Heights for expand/minimise transitions.
    var _H_EXPANDED  = '460px';
    var _H_MINIMIZED = '44px';   // show only the softphone header bar

    // ── Call button state ─────────────────────────────────────────────────────

    function _setCallButtonsEnabled(enabled) {
        ['.call-btn', '.integrated-call-btn'].forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (el) {
                el.disabled = !enabled;
                el.title    = enabled ? '' : 'Softphone connecting\u2026';
            });
        });
    }

    // Disable all call buttons immediately on page load; re-enabled on TCN_READY.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { _setCallButtonsEnabled(false); });
    } else {
        _setCallButtonsEnabled(false);
    }

    // ── Document CustomEvent dispatch ─────────────────────────────────────────
    // global-call.js listens to these events on document.

    function _dispatch(name, detail) {
        document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    // ── window.TCN proxy ──────────────────────────────────────────────────────
    // global-call.js checks window.TCN.isReady and window.TCN._loggedIn before
    // allowing a call.  We keep a minimal proxy in sync with the iframe state so
    // those guards work without touching global-call.js.

    function _updateTcnProxy(state, callActive) {
        var ready = (state === 'ready' || state === 'calling' || state === 'on-call' || state === 'paused');
        if (!window.TCN || !window.TCN.__iframeProxy) {
            window.TCN = { __iframeProxy: true, isReady: false, _loggedIn: false, _callActive: false };
        }
        window.TCN.isReady    = ready;
        window.TCN._loggedIn  = ready;
        window.TCN._callActive = !!callActive;
        window.isSoftphoneReady = ready;
    }

    // ── postMessage to iframe ─────────────────────────────────────────────────

    function _postToFrame(msg) {
        if (!_frame) return;
        try { _frame.contentWindow.postMessage(msg, _origin); } catch (_) {}
    }

    // ── Handle incoming messages from iframe ──────────────────────────────────

    function _onMessage(event) {
        if (event.origin !== _origin)  return;
        if (!_frame || event.source !== _frame.contentWindow) return;

        var msg = event.data;
        if (!msg || typeof msg !== 'object') return;

        switch (msg.type) {

            // ── Ready ──────────────────────────────────────────────────────────
            case 'TCN_READY':
                _snap = Object.assign({}, _snap || {}, { state: 'ready', paused: false, callActive: false });
                _updateTcnProxy('ready', false);
                _setCallButtonsEnabled(true);
                _dispatch('tcn:tcn-host-ready', { snapshot: _snap });
                _dispatch('tcn:state-sync', _snap);
                break;

            // ── Full state sync (PING response or minimise/expand) ─────────────
            case 'TCN_STATE_SYNC':
                _snap = Object.assign({}, msg);
                _updateTcnProxy(msg.state, msg.callActive);
                if (msg.state === 'ready' || msg.state === 'on-call' || msg.state === 'paused') {
                    _setCallButtonsEnabled(true);
                }
                _dispatch('tcn:state-sync', _snap);
                break;

            // ── Call started (SIP INVITE sent) ─────────────────────────────────
            case 'TCN_CALL_STARTED':
                _snap = Object.assign({}, _snap || {}, {
                    state:     'calling',
                    phone:     msg.phone || (_snap && _snap.phone) || '',
                    callLogId: msg.callLogId || null,
                    callActive:true,
                });
                _updateTcnProxy('calling', true);
                _dispatch('tcn:tcn-call-started', { phone: msg.phone, callLogId: msg.callLogId });
                _dispatch('tcn:state-sync', _snap);
                break;

            // ── Call answered (remote audio established) ────────────────────────
            case 'TCN_CALL_ANSWERED':
                _snap = Object.assign({}, _snap || {}, {
                    state:              'on-call',
                    phone:              msg.phone || (_snap && _snap.phone) || '',
                    callLogId:          msg.callLogId || null,
                    callActive:         true,
                    callEstablishedAt:  Date.now(),
                });
                _updateTcnProxy('on-call', true);
                _dispatch('tcn:tcn-call-answered', { phone: msg.phone, callLogId: msg.callLogId });
                _dispatch('tcn:state-sync', _snap);
                _dispatch('gc:callAccepted', {});
                break;

            // ── Call ended ─────────────────────────────────────────────────────
            case 'TCN_CALL_ENDED':
                _snap = Object.assign({}, _snap || {}, {
                    state:             (_snap && _snap.paused) ? 'paused' : 'ready',
                    callActive:        false,
                    callLogId:         null,
                    callEstablishedAt: null,
                });
                _updateTcnProxy(_snap.state, false);
                _dispatch('tcn:tcn-call-ended', {
                    callLogId: msg.callLogId || null,
                    phone:     msg.phone     || null,
                    duration:  msg.duration  || null,
                });
                _dispatch('tcn:state-sync', _snap);
                // gc:callEnded triggers the call-outcome modal in the parent page.
                _dispatch('gc:callEnded', { callLogId: msg.callLogId || null, phone: msg.phone || null });
                break;

            // ── SIP presence session dropped (reconnecting) ────────────────────
            case 'TCN_SIP_DROPPED':
                if (_snap && !_snap.callActive) {
                    _snap = Object.assign({}, _snap, { state: 'connecting' });
                    _updateTcnProxy('connecting', false);
                    _setCallButtonsEnabled(false);
                }
                _dispatch('tcn:state-sync', _snap || {});
                break;

            // ── Logged out ─────────────────────────────────────────────────────
            case 'TCN_LOGGED_OUT':
                _snap = { state: 'connecting', callActive: false, callLogId: null };
                _updateTcnProxy('connecting', false);
                _setCallButtonsEnabled(false);
                _dispatch('tcn:tcn-logged-out', {});
                _dispatch('tcn:state-sync', _snap);
                break;

            // ── Error ──────────────────────────────────────────────────────────
            case 'TCN_ERROR':
                _dispatch('tcn:tcn-error', { message: msg.message });
                _dispatch('tcn:state-sync', _snap || {});
                break;

            // ── Softphone minimised / expanded ─────────────────────────────────
            // Adjust wrapper height to clip/reveal the iframe content.
            case 'SP_MINIMIZE':
                if (_wrapper) _wrapper.style.height = _H_MINIMIZED;
                if (_snap) _snap.minimized = true;
                break;

            case 'SP_EXPAND':
                if (_wrapper) _wrapper.style.height = _H_EXPANDED;
                if (_snap) _snap.minimized = false;
                break;
        }
    }

    window.addEventListener('message', _onMessage);

    // ── Public API ────────────────────────────────────────────────────────────

    window.TCNWidget = {

        /**
         * init({ frameId, wrapperId })
         * Called once per page from the layout after DOMContentLoaded.
         *
         * frameId   — id of the <iframe> element (default: 'sipClientFrame')
         * wrapperId — id of the wrapper <div> used for minimise animation
         */
        init: function (options) {
            options  = options  || {};
            _frame   = document.getElementById(options.frameId   || 'sipClientFrame');
            _wrapper = document.getElementById(options.wrapperId || 'sipClientWrapper');

            if (!_frame) {
                console.warn('[TCNWidget v5] #sipClientFrame not found — widget inactive.');
                return this;
            }

            // Ping the iframe once it finishes loading to get the current state.
            // This covers the case where the CRM page navigated during a live call.
            _frame.addEventListener('load', function () {
                // Short delay: give sip-client.blade.php time to finish its init.
                setTimeout(function () { _postToFrame({ type: 'PING' }); }, 500);
            });

            // If the iframe is already loaded (same src — browser served from cache),
            // ping immediately to hydrate _snap.
            if (_frame.contentDocument && _frame.contentDocument.readyState === 'complete') {
                _postToFrame({ type: 'PING' });
            }

            return this;
        },

        /**
         * Initiate an outbound call.
         * phone  — E.164 or local number; normalisation is done inside the iframe.
         * leadId — optional CRM lead ID attached to the call log.
         */
        call: function (phone, leadId) {
            _postToFrame({ type: 'CALL', phone: phone, leadId: leadId || null });
        },

        /** Hang up the active call. */
        end: function () {
            _postToFrame({ type: 'HANGUP' });
        },

        /** Expand the softphone widget (un-minimise). */
        open: function () {
            if (_wrapper) _wrapper.style.height = _H_EXPANDED;
            _postToFrame({ type: 'PING' });
        },

        /**
         * Generic message forwarding.
         * Handles: LOGOUT | CALL | HANGUP | MUTE | HOLD | DTMF | SET_PHONE | PING
         */
        send: function (msg) {
            _postToFrame(msg);
        },

        /** True when a call is active (used by global-call.js nav-intercept). */
        isCallActive: function () {
            return _snap ? !!_snap.callActive : false;
        },

        /** Last known state snapshot from the iframe, or null. */
        snapshot: function () {
            return _snap ? Object.assign({}, _snap) : null;
        },
    };

})(window, document);
