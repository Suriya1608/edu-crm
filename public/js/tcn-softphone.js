/**
 * tcn-softphone.js  â€” v5.0  SIP direct-dial edition
 * â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 * Architecture:
 *
 * Login flow (REST + SIP):
 *   1. /api/tcn/config  â†’ access_token, agent_id, hunt_group_id
 *   2. /tcn/skills      â†’ skills map
 *   3. /tcn/session     â†’ asmSessionSid, voiceSessionSid,
 *                         voiceRegistration { username, password, dialUrl }
 *   4. SIP.js UA created with credentials from step 3
 *   5. SIP INVITE â†’ sip:{dialUrl}@sg-webphone.tcnp3.com
 *      â†’ puts agent in READY state in TCN (presence session)
 *   6. Keep-alive: /tcn/keepalive every 30 s (fires immediately after login)
 *      â†’ on 3 consecutive failures: cleanup + re-login
 *
 * Outbound call flow (pure SIP â€” no manualdial REST API):
 *   7.  Create DB call-log via POST /tcn/call-log  (non-fatal if it fails)
 *   8.  Create new SIP.Inviter on the existing UA:
 *         target = sip:{+91XXXXXXXXXX}@sg-webphone.tcnp3.com
 *       â†’ agent sends SIP INVITE directly to TCN's WebRTC gateway
 *       â†’ TCN routes the call to PSTN
 *   9.  On stateChange Established: attach remote audio, fire tcn:callAnswered
 *   10. On stateChange Terminated:  update call-log, fire tcn:callEnded
 *   11. endCall: cancel() if still Establishing, bye() if Established
 *
 * Phone validation:
 *   - Strips non-digits, strips leading "91" (12 digits) or "00"
 *   - Requires exactly 10 local digits â†’ normalised to +91XXXXXXXXXX
 *
 * Events fired on window:
 *   tcn:ready        â€” login complete, agent READY
 *   tcn:callStarted  â€” SIP INVITE sent        { phone, callLogId }
 *   tcn:callAnswered â€” remote party answered  { phone, callLogId }
 *   tcn:callEnded    â€” call terminated        { phone, callLogId, duration }
 *   tcn:sipDropped   â€” presence SIP session fell, reconnect scheduled
 *   tcn:loggedOut    â€” logout() completed
 *   tcn:error        â€” { message }
 */

(function () {
    "use strict";

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Singleton guard â€” prevent double-loading this script.
    //
    // If tcn-softphone.js is injected a second time (e.g. by tcn-service.js
    // loading it dynamically while the layout already loaded it statically),
    // the second IIFE would overwrite window.TCN with a fresh, un-logged-in
    // object â€” wiping the active session.  Exit immediately if already loaded.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if (window.TCN && window.TCN.__initialized) {
        return; // already loaded â€” keep the existing session alive
    }

    // Global readiness flag â€” true only after Presence SIP Established.
    // Call buttons must not fire until this is true.
    window.isSoftphoneReady = false;

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Cross-tab session lock
    //
    // TCN's ACD assigns ONE voice session per agent.  If two browser tabs
    // both call login(), the second ASM session overwrites the first and
    // both tabs end up in an inconsistent state.
    //
    // We use localStorage as a cross-tab mutex:
    //   â€¢ login()  â†’ write _TAB_ID under TCN_TAB_LOCK_KEY (if no other tab holds it)
    //   â€¢ logout() â†’ release the lock (clear the key)
    //   â€¢ beforeunload â†’ release the lock so refreshes don't permanently block
    //
    // The lock is "soft" â€” it warns and blocks, but doesn't crash. If the
    // other tab's lock is stale (e.g. the tab crashed without beforeunload),
    // the agent can force-unlock by calling TCN.forceUnlock() in DevTools.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    var TCN_TAB_LOCK_KEY = 'tcn_active_tab_lock';
    var _TAB_ID = 'tab_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

    function _acquireTabLock() {
        try {
            var existing = localStorage.getItem(TCN_TAB_LOCK_KEY);
            if (existing && existing !== _TAB_ID) {
                return false; // another tab holds the lock
            }
            localStorage.setItem(TCN_TAB_LOCK_KEY, _TAB_ID);
            return true;
        } catch (_) {
            return true; // localStorage unavailable â€” allow login (private mode, etc.)
        }
    }

    function _releaseTabLock() {
        try {
            if (localStorage.getItem(TCN_TAB_LOCK_KEY) === _TAB_ID) {
                localStorage.removeItem(TCN_TAB_LOCK_KEY);
            }
        } catch (_) {}
    }

    window.addEventListener('beforeunload', _releaseTabLock);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // State
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    var TCN = {
        __initialized: true,  // singleton marker â€” checked at top of IIFE
        // Auth / session
        _accessToken: null,
        _agentSid: null,
        _clientSid: null,
        _huntGroupSid: null,
        _skills: {},
        _asmSessionSid: null,
        _voiceSessionSid: null,

        // SIP credentials
        _sipUser: null,
        _sipPass: null,
        _dialUrl: null,
        _callerId: null,
        _ua: null,     // presence UA (login session)
        _callUa: null,     // dedicated UA per outbound call (fresh credentials)
        _sipSession: null,     // presence/login SIP session
        _outboundSession: null,     // active outbound call SIP session (direct-SIP-dial mode, legacy)
        _bridgeSession: null,       // TCN-bridged call SIP session (inbound INVITE during Manual Dial)
        _registered: false,

        // Call tracking
        _callStartTime: 0,
        _callEstablishedAt: 0,
        _activePhone: null,
        _activeLogId: null,
        _activeLeadId: null,
        _activeCallSid: null,
        _callAnsweredSynced: false,
        _callAnswerTimer: null,

        // Keep-alive â€” login/presence session
        _keepAliveTimer: null,
        _keepAliveFailCount: 0,
        KEEPALIVE_MS: 30000,  // 30 s keep-alive interval

        // Keep-alive â€” outbound call session (ACD voice session SID)
        _callKeepAliveTimer: null,
        _callVoiceSessionSid: null,

        // Status polling â€” detect INCALL / call-ended during Manual Dial calls
        _callStatusPollTimer: null,

        // Lifecycle flags
        _loggedIn: false,
        _loginInProgress: false,
        _callActive: false,
        _endCallInProgress: false,  // guard against double-click on End button
        _reconnecting: false,

        // Public readiness flag â€” true only after Presence SIP Established.
        // External code (global-call.js, blade views) should read this property
        // rather than the private _loggedIn flag.
        isReady: false,

        // Declared here so the IDE type-checker knows these properties exist on TCN.
        // The real implementations are assigned below after the object literal.
        _isUaReady: /** @type {function(): boolean} */ (null),
        _waitForReady: /** @type {function(number=): Promise<void>} */ (null),
        _waitForAcdReady: /** @type {function(number=): Promise<string>} */ (null),

        CACHE_KEY:          'tcn_softphone_bootstrap_v1', // disabled for security (no credential persistence)
        CALL_STATE_KEY:     'tcn_active_call_v1',       // in-memory only (no browser storage persistence)
        CACHE_TTL_MS:       55 * 60 * 1000,
        CALL_STATE_TTL_MS:  2  * 60 * 60 * 1000,       // 2 h â€” stale call states are abandoned
        _apiBase: 'https://api.bom.tcn.com',
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Helpers
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function fire(name, detail) {
        window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    function log(msg, data) {
        if (data !== undefined) {
            console.log('[TCN]', msg, data);
        } else {
            console.log('[TCN]', msg);
        }
    }

    /**
     * Generic proxy POST â€” always includes Bearer token when available.
     * Throws on non-2xx so callers can catch and handle.
     */
    async function proxy(path, body) {
        var headers = {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
        };
        if (TCN._accessToken) {
            headers['Authorization'] = 'Bearer ' + TCN._accessToken;
        }
        var res = await fetch(path, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(body || {}),
        });
        var json = await res.json();
        if (!res.ok) {
            throw new Error('[TCN] ' + path + ' failed (' + res.status + '): ' + JSON.stringify(json));
        }
        return json;
    }

    function buildClientCallSid() {
        return 'tcn-web-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

    var __tcnCallStateMemory = null;

    function normalizePhone(phone) {
        var digits = String(phone || '').replace(/\D/g, '');
        if (digits.startsWith('91') && digits.length === 12) digits = digits.slice(2);
        if (digits.startsWith('00')) digits = digits.slice(2);
        if (digits.length < 7) {
            throw new Error('Invalid phone number: ' + phone);
        }
        return {
            digits: digits,
            e164: '+91' + digits,
        };
    }

    function readCache() {
        return null;
    }

    function writeCache() {
        // Security hardening: never persist access/SIP credentials in browser storage.
    }

    function clearCache() {
        // Security hardening: cache persistence disabled.
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Active Call State â€” memory-only (not persisted to browser storage).
    // Stores the minimum runtime state while the current page is alive.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    function saveCallState() {
        __tcnCallStateMemory = {
            savedAt:           Date.now(),
            sessionSid:        TCN._callVoiceSessionSid,
            phone:             TCN._activePhone,
            callLogId:         TCN._activeLogId,
            leadId:            TCN._activeLeadId,
            callEstablishedAt: TCN._callEstablishedAt,
            callStartTime:     TCN._callStartTime,
        };
    }

    function clearCallState() {
        __tcnCallStateMemory = null;
    }

    function readCallState() {
        return __tcnCallStateMemory;
    }

    function restoreCache(bootstrap) {
        if (!bootstrap) return false;

        TCN._accessToken = bootstrap.accessToken || TCN._accessToken;
        TCN._agentSid = bootstrap.agentSid || TCN._agentSid;
        TCN._huntGroupSid = bootstrap.huntGroupSid || TCN._huntGroupSid;
        TCN._skills = bootstrap.skills || {};
        TCN._asmSessionSid = bootstrap.asmSessionSid || null;
        TCN._voiceSessionSid = bootstrap.voiceSessionSid || null;
        TCN._sipUser = bootstrap.sipUser || null;
        TCN._sipPass = bootstrap.sipPass || null;
        TCN._dialUrl = bootstrap.dialUrl || null;

        // dialUrl is intentionally not cached (single-use) â€” not required here.
        return !!(TCN._accessToken && TCN._voiceSessionSid && TCN._sipUser && TCN._sipPass);
    }

    async function canResumeCachedSession() {
        return false;
    }

    function clearAnswerTimer() {
        if (TCN._callAnswerTimer) {
            clearTimeout(TCN._callAnswerTimer);
            TCN._callAnswerTimer = null;
        }
    }

    function markCallAnswered(source, extraDetail) {
        if (!TCN._callActive) return false;
        if (TCN._callAnsweredSynced) {
            log('Ignoring duplicate answered signal from ' + source);
            return false;
        }

        TCN._callAnsweredSynced = true;
        if (!TCN._callEstablishedAt) {
            TCN._callEstablishedAt = Date.now();
        }

        var detail = Object.assign({
            phone: TCN._activePhone,
            callLogId: TCN._activeLogId,
        }, extraDetail || {});

        fire('tcn:callAnswered', detail);

        if (TCN._activeLogId) {
            patchCallLog(TCN._activeLogId, {
                status: 'answered',
                answered_at: new Date(TCN._callEstablishedAt).toISOString(),
            });
        }

        saveCallState();
        log('Call answered via ' + source);
        return true;
    }

    async function patchCallLog(logId, payload) {
        if (!logId) return;
        try {
            await fetch('/tcn/call-log/' + logId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify(payload || {}),
            });
        } catch (_) { }
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // SIP.js loader (lazy â€” only loads the script once)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function loadSipJs() {
        if (window.SIP) return Promise.resolve();
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = '/js/sip.js';
            s.onload = resolve;
            s.onerror = function () { reject(new Error('Failed to load /js/sip.js')); };
            document.head.appendChild(s);
        });
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Remote audio â€” attach the inbound WebRTC track to <audio>
    //
    // CRITICAL: Without this the agent hears nothing even if the SIP
    // session reaches Established â€” the MediaStream exists but is not
    // rendered by any audio element.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    TCN._attachRemoteAudio = function (session, elementId) {
        elementId = elementId || 'tcn-remote-audio';
        try {
            var sdh = session.sessionDescriptionHandler;
            if (!sdh || !sdh.peerConnection) {
                log('attachRemoteAudio: no peerConnection yet');
                return;
            }
            var pc = sdh.peerConnection;

            var audio = document.getElementById(elementId);
            if (!audio) {
                audio = document.createElement('audio');
                audio.id = elementId;
                audio.autoplay = true;
                audio.style.display = 'none';
                audio.setAttribute('playsinline', '');
                document.body.appendChild(audio);
            }

            var remoteStream = new MediaStream();
            pc.getReceivers().forEach(function (rx) {
                if (rx.track) remoteStream.addTrack(rx.track);
            });
            audio.srcObject = remoteStream;

            // Handle future tracks â€” TCN bridges PSTN audio dynamically via SDP re-negotiation.
            // Without this, the audio element never receives the bridged call audio.
            pc.addEventListener('track', function (evt) {
                log('Remote track added (bridged by TCN)');
                if (evt.streams && evt.streams[0]) {
                    audio.srcObject = evt.streams[0];
                } else if (evt.track) {
                    remoteStream.addTrack(evt.track);
                    audio.srcObject = remoteStream;
                }
                var p = audio.play();
                if (p) p.catch(function () {});
            });

            var p = audio.play();
            if (p) p.catch(function (e) { log('audio.play() blocked (needs user gesture)', e.message); });
            log('Remote audio attached â†’ #' + elementId);
        } catch (e) {
            log('attachRemoteAudio error (non-fatal)', e.message);
        }
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // SIP cleanup â€” tear down UA and all sessions cleanly.
    // Must be called before every reconnect/logout to avoid dangling
    // WebSocket listeners from the old UA.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    TCN._cleanupSip = function () {
        clearAnswerTimer();
        TCN._stopCallKeepAlive();
        TCN._stopCallStatusPoll();
        if (TCN._bridgeSession) {
            try {
                var bs = TCN._bridgeSession.state;
                if (bs === 'Established') TCN._bridgeSession.bye();
                else if (bs === 'Initial' || bs === 'Establishing') TCN._bridgeSession.reject();
            } catch (_) {}
            TCN._bridgeSession = null;
        }
        if (TCN._outboundSession) {
            try {
                var s = TCN._outboundSession.state;
                if (s === 'Initial' || s === 'Establishing') {
                    TCN._outboundSession.cancel();
                } else if (s === 'Established') {
                    TCN._outboundSession.bye();
                }
            } catch (_) { }
            TCN._outboundSession = null;
        }
        if (TCN._sipSession) {
            try { TCN._sipSession.bye(); } catch (_) { }
            TCN._sipSession = null;
        }
        if (TCN._callUa) {
            try { TCN._callUa.stop(); } catch (_) { }
            TCN._callUa = null;
        }
        if (TCN._ua) {
            try { TCN._ua.stop(); } catch (_) { }
            TCN._ua = null;
        }
        TCN._registered = false;
        TCN._sipUser = null;
        TCN._sipPass = null;
        TCN._dialUrl = null;
        TCN._callEstablishedAt = 0;
        TCN._callAnsweredSynced = false;
        log('SIP cleaned up');
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Login Flow â€” 4 REST steps + SIP + keepalive
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN.login = async function () {
        if (TCN._loggedIn) { log('Already logged in'); return; }

        // Singleton guard â€” prevent duplicate login calls racing on same page.
        if (TCN._loginInProgress) { log('Login already in progress, skipping.'); return; }

        // Cross-tab singleton lock â€” prevent a second browser tab from creating
        // a competing ASM session for the same agent.
        if (!_acquireTabLock()) {
            var lockErr = 'TCN is already active in another browser tab. Close the other tab first.';
            log(lockErr);
            fire('tcn:error', { message: lockErr });
            throw new Error(lockErr);
        }

        TCN._loginInProgress = true;

        // Tear down any stale SIP state from a previous session or
        // failed reconnect attempt before starting fresh.
        TCN._cleanupSip();
        TCN._asmSessionSid = null;
        TCN._voiceSessionSid = null;

        try {
            // Step 1 â€” Fetch per-user config: exchanges stored refresh_token for a
            // short-lived access_token server-side. Also returns agent_id + hunt_group_id
            // so the separate /tcn/agent call is no longer needed.
            // client_secret and refresh_token NEVER reach the browser.
            log('Step 1: Fetching per-user TCN configâ€¦');
            var cfgResp = await fetch('/api/tcn/config', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                },
                credentials: 'same-origin',
            });
            var cfg = await cfgResp.json();
            if (!cfg.configured || !cfg.access_token) {
                throw new Error(cfg.error || 'TCN account not configured. Ask admin to connect your TCN account.');
            }
            TCN._accessToken = cfg.access_token;
            TCN._agentSid = cfg.agent_id;
            TCN._huntGroupSid = cfg.hunt_group_id;
            log('Config loaded', { agentSid: TCN._agentSid, huntGroupSid: TCN._huntGroupSid });

            // Step 2 â€” Agent skills (uses access_token set above)
            log('Step 2: Getting agent skillsâ€¦');
            var skillsData = await proxy('/tcn/skills', {
                huntGroupSid: parseInt(TCN._huntGroupSid),
                agentSid: parseInt(TCN._agentSid),
            });
            TCN._skills = skillsData.skills || {};
            log('Skills loaded', TCN._skills);

            // Step 3 â€” Create ASM session (SIP credentials)
            log('Step 3: Creating ASM session (SIP credentials)â€¦');
            var session = await proxy('/tcn/session', {
                huntGroupSid: parseInt(TCN._huntGroupSid),
                skills: TCN._skills,
                subsession_type: 'VOICE',
            });

            TCN._asmSessionSid = session.asmSessionSid || session.asm_session_sid;
            TCN._voiceSessionSid = session.voiceSessionSid || session.voice_session_sid;

            var vr = session.voiceRegistration || session.voice_registration;
            if (!vr) {
                throw new Error('ASM session missing voice_registration. Full response: ' + JSON.stringify(session));
            }
            TCN._sipUser = vr.username;
            TCN._sipPass = vr.password;
            TCN._dialUrl = vr.dialUrl || vr.dial_url;

            if (!TCN._sipUser || !TCN._sipPass || !TCN._dialUrl) {
                throw new Error('ASM session voice_registration missing username/password/dialUrl');
            }

            log('ASM session', {
                asmSid: TCN._asmSessionSid,
                voiceSid: TCN._voiceSessionSid,
                sipUser: TCN._sipUser,
                dialUrl: TCN._dialUrl,
            });

            // Step 4 â€” Load SIP.js and establish presence SIP session.
            // Keep-alive must NOT start before SIP is Established â€” TCN returns
            // keepAliveSucceeded=false / UNAVAILABLE until the SIP INVITE is answered.
            log('Loading SIP.jsâ€¦');
            await loadSipJs();

            var SIP = (window.SIP && window.SIP.SIP) ? window.SIP.SIP : window.SIP;
            if (!SIP || !SIP.UserAgent) {
                throw new Error('SIP.js not loaded â€” /js/sip.js must export window.SIP');
            }

            await callDialUrl(SIP);

            // Step 5 â€” Start keep-alive only after SIP Established (agent is READY).
            TCN._startKeepAlive();

            TCN._loggedIn = true;
            TCN._loginInProgress = false;
            TCN.isReady = true;
            window.isSoftphoneReady = true;
            log('Login complete â€” agent is READY');
            fire('tcn:ready');

        } catch (e) {
            TCN._loginInProgress = false;
            log('Login failed', e.message);
            fire('tcn:error', { message: e.message });
            throw e;
        }
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // SIP Presence Session
    //
    // TCN Operator API doc: "Use SIP.js to call in to the dial Url
    // returned in the create session response."
    //
    // This SIP INVITE (NOT REGISTER) establishes the agent's audio
    // channel on TCN and puts them in READY state.
    // Audio MUST be attached on Established â€” TCN bridges inbound or
    // bridged calls to this session's audio track.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    function callDialUrl(SIP) {
        return new Promise(function (resolve, reject) {
            var wsUri = 'wss://sg-webphone.tcnp3.com';
            var settled = false;

            var timer = setTimeout(function () {
                if (settled) return;
                settled = true;
                log('SIP presence INVITE timed out (20 s)');
                reject(new Error('SIP timed out â€” dial_url may be expired or credentials wrong'));
            }, 20000);

            TCN._ua = new SIP.UserAgent({
                uri: SIP.UserAgent.makeURI('sip:' + TCN._sipUser + '@sg-webphone.tcnp3.com'),
                transportConstructor: SIP.Web.Transport,
                transportOptions: { server: wsUri },
                authorizationUsername: TCN._sipUser,
                authorizationPassword: TCN._sipPass,
                logLevel: 'warn',
                sessionDescriptionHandlerFactoryOptions: {
                    constraints: { audio: true, video: false },
                    // STUN ensures ICE candidate gathering succeeds behind NAT.
                    // Without this, only host candidates are gathered and media
                    // fails on most enterprise/NAT environments.
                    peerConnectionConfiguration: {
                        iceServers: [
                            { urls: 'stun:stun.l.google.com:19302' },
                            { urls: 'stun:stun1.l.google.com:19302' },
                        ],
                    },
                },
                // Handle inbound SIP INVITEs from TCN.
                // Manual Dial bridges audio via a NEW inbound INVITE when the customer answers.
                // Rejecting it prevents the bridge â†’ TCN stays PEERED â†’ drops to WRAPUP.
                delegate: {
                    onInvite: function (invitation) {
                        if (TCN._callActive) {
                            // TCN is bridging the PSTN call audio to this agent session.
                            // Accept so TCN can complete the bridge â†’ PEERED â†’ INCALL.
                            log('Inbound SIP INVITE during active call â€” accepting as TCN audio bridge');
                            TCN._bridgeSession = invitation;

                            invitation.accept({
                                sessionDescriptionHandlerOptions: {
                                    constraints: { audio: true, video: false },
                                },
                            }).catch(function (e) {
                                log('Bridge INVITE accept error', e.message);
                            });

                            invitation.stateChange.addListener(function (state) {
                                log('TCN bridge session state: ' + state);

                                if (state === 'Established') {
                                    // Attach audio from the bridge session (not the presence session)
                                    TCN._attachRemoteAudio(invitation, 'tcn-remote-audio');

                                    markCallAnswered('sip-established');

                                } else if (state === 'Terminated') {
                                    TCN._bridgeSession = null;
                                    // Bridge SIP terminated while call was live = remote hangup via SIP
                                    if (TCN._callActive && TCN._callEstablishedAt) {
                                        log('TCN bridge SIP terminated â€” triggering call end');
                                        TCN._handleCallEnded();
                                    }
                                }
                            });

                        } else {
                            // No active call â€” reject unexpected invitations
                            log('Unexpected inbound SIP INVITE â€” rejecting (no active call)');
                            try { invitation.reject(); } catch (_) { }
                        }
                    },
                },
            });

            TCN._ua.start().then(function () {
                var target = SIP.UserAgent.makeURI('sip:' + TCN._dialUrl + '@sg-webphone.tcnp3.com');
                var inviter = new SIP.Inviter(TCN._ua, target);
                TCN._sipSession = inviter;

                inviter.stateChange.addListener(function (state) {
                    log('Presence SIP state: ' + state);

                    if (state === 'Established' && !settled) {
                        settled = true;
                        clearTimeout(timer);
                        TCN._registered = true;
                        // Attach to tcn-remote-audio so TCN-bridged Manual Dial audio reaches the UI.
                        TCN._attachRemoteAudio(inviter, 'tcn-remote-audio');
                        log('Presence SIP Established â€” agent READY');
                        resolve();

                    } else if (state === 'Terminated' && !settled) {
                        // Failed to establish
                        settled = true;
                        clearTimeout(timer);
                        reject(new Error('Presence SIP terminated before Established â€” check credentials/dial_url'));

                    } else if (state === 'Terminated' && settled) {
                        // Dropped after successful login â€” schedule reconnect
                        TCN._registered = false;
                        TCN._sipSession = null;
                        TCN.isReady = false;
                        window.isSoftphoneReady = false;
                        log('Presence SIP dropped â€” scheduling reconnectâ€¦');
                        fire('tcn:sipDropped');

                        // Always stop the presence keep-alive â€” the session SID is
                        // no longer valid once the presence SIP drops. During an active
                        // call this prevents repeated UNAVAILABLE pings on the dead SID.
                        TCN._stopKeepAlive();

                        if (!TCN._callActive && !TCN._reconnecting) {
                            TCN._reconnecting = true;
                            TCN._loggedIn = false;
                            // Clean up the dead UA so login() gets a clean slate
                            if (TCN._ua) {
                                try { TCN._ua.stop(); } catch (_) { }
                                TCN._ua = null;
                            }
                            setTimeout(function () {
                                TCN._reconnecting = false;
                                log('Auto-reconnectingâ€¦');
                                TCN.login().catch(function (e) {
                                    log('Auto-reconnect failed', e.message);
                                    fire('tcn:error', { message: 'Reconnect failed: ' + e.message });
                                });
                            }, 3000);
                        }
                    }
                });

                return inviter.invite({
                    sessionDescriptionHandlerOptions: {
                        constraints: { audio: true, video: false },
                    },
                });

            }).catch(function (err) {
                if (!settled) {
                    settled = true;
                    clearTimeout(timer);
                    log('SIP UA.start() failed', err);
                    reject(err);
                }
            });
        });
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Keep-alive
    //
    // Fires IMMEDIATELY after login (critical â€” do NOT wait 30 s),
    // then every 30 s.
    //
    // Uses voiceSessionSid (preferred) or asmSessionSid as fallback.
    // A null sessionSid would send "null" to TCN â€” validated here.
    //
    // After 3 consecutive failures the session is assumed expired;
    // SIP cleanup + re-login is triggered automatically.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    TCN._doKeepAlive = async function () {

        var sid = TCN._voiceSessionSid || TCN._asmSessionSid;

        if (!sid) {
            log('Keep-alive skipped â€” no valid sessionSid');
            return;
        }

        sid = String(sid);

        try {

            var data = await proxy('/tcn/keepalive', { sessionSid: sid });

            var status = ((data && data.statusDesc) || '').toUpperCase();
            var kaOk = !!(data && data.keepAliveSucceeded);

            // âœ… Initialize counter if not exists
            TCN._keepAliveFailCount = TCN._keepAliveFailCount || 0;

            log('Keep-alive response', {
                sessionSid: sid,
                keepAliveSucceeded: kaOk,
                statusDesc: status || '?',
                raw: data,
            });

            // âœ… SUCCESS CASE
            if (kaOk) {
                TCN._keepAliveFailCount = 0; // reset on success
                return;
            }

            // ðŸš¨ IMPORTANT FIX: Ignore temporary UNAVAILABLE during active call
            if (status === 'UNAVAILABLE' && TCN._callActive) {
                log('Transient UNAVAILABLE during active call â†’ ignoring');
                return;
            }

            // âš ï¸ Count failure only for real issues
            TCN._keepAliveFailCount++;

            log('Keep-alive warning (attempt ' + TCN._keepAliveFailCount + '/3)', data);

            // â— Only act after multiple failures
            if (TCN._keepAliveFailCount >= 3) {

                log('Keep-alive failed multiple times â€” checking session status');

                // Only trigger re-login for real disconnection states
                if (status === 'DISCONNECTED' || status === 'LOGGED_OUT') {
                    log('Session expired (' + status + ') â†’ triggering re-login');
                    TCN._keepAliveFailCount = 0;
                    TCN._handleSessionExpired();
                } else {
                    log('Ignoring non-critical keep-alive failure:', status);
                    TCN._keepAliveFailCount = 0;
                }
            }

        } catch (e) {

            TCN._keepAliveFailCount = (TCN._keepAliveFailCount || 0) + 1;

            log('Keep-alive error (attempt ' + TCN._keepAliveFailCount + '/3)', e.message);

            if (TCN._keepAliveFailCount >= 3) {
                log('Keep-alive failed 3 times â€” triggering re-login');
                TCN._keepAliveFailCount = 0;
                TCN._handleSessionExpired();
            }
        }
    };

    TCN._handleSessionExpired = function () {
        if (TCN._reconnecting || TCN._callActive) return;
        TCN._stopKeepAlive();
        TCN._loggedIn = false;
        TCN._reconnecting = true;
        TCN._cleanupSip();
        fire('tcn:sipDropped');
        setTimeout(function () {
            TCN._reconnecting = false;
            log('Re-initializing after session expiryâ€¦');
            TCN.login().catch(function (e) {
                log('Re-login failed', e.message);
                fire('tcn:error', { message: 'Session expired and re-login failed: ' + e.message });
            });
        }, 2000);
    };

    TCN._startKeepAlive = function () {
        TCN._stopKeepAlive();
        TCN._keepAliveFailCount = 0;
        // Do NOT fire immediately. TCN takes ~30s after SIP Established to activate a new
        // voice session on their backend. Pinging before that always returns UNAVAILABLE /
        // currentSessionId:0. The 30s interval aligns exactly with TCN's activation window.
        TCN._keepAliveTimer = setInterval(function () {
            TCN._doKeepAlive();
        }, TCN.KEEPALIVE_MS);
    };

    TCN._stopKeepAlive = function () {
        if (TCN._keepAliveTimer) {
            clearInterval(TCN._keepAliveTimer);
            TCN._keepAliveTimer = null;
        }
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Call-session keep-alive
    //
    // Each outbound call creates a FRESH ASM session with its own
    // voiceSessionSid. That session ALSO needs keep-alive pings â€”
    // the login-session keep-alive uses a different SID and does NOT
    // keep the call session alive. Without this, TCN expires the call
    // session and sends BYE immediately after 200 OK.
    //
    // Fires immediately on call setup, then every 25 s until the call
    // ends (Terminated state or endCall()).
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    TCN._doCallKeepAlive = async function () {
        var sid = TCN._callVoiceSessionSid;
        if (!sid) return;
        try {
            var data = await proxy('/tcn/keepalive', { sessionSid: sid });
            var kaOk = !!(data && data.keepAliveSucceeded);
            log('Call keep-alive OK', {
                sessionSid: sid,
                keepAliveSucceeded: kaOk,
                statusDesc: ((data && data.statusDesc) || '?').toUpperCase(),
            });
            if (!kaOk) {
                log('Call keep-alive WARNING: keepAliveSucceeded=false â€” call session may expire', data);
            }
        } catch (e) {
            log('Call keep-alive failed (non-fatal)', e.message);
        }
    };

    TCN._startCallKeepAlive = function (voiceSid) {

        // âœ… Prevent duplicate start
        if (TCN._callKeepAliveTimer) {
            log('Call keep-alive already running, skipping restart');
            return;
        }

        TCN._callVoiceSessionSid = String(voiceSid);

        setTimeout(function () {
            log('Call keep-alive started after delay');

            TCN._doCallKeepAlive();

            TCN._callKeepAliveTimer = setInterval(function () {
                TCN._doCallKeepAlive();
            }, TCN.KEEPALIVE_MS);

        }, 5000);
    };

    TCN._stopCallKeepAlive = function () {
        if (TCN._callKeepAliveTimer) {
            clearInterval(TCN._callKeepAliveTimer);
            TCN._callKeepAliveTimer = null;
        }
        TCN._callVoiceSessionSid = null;
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Readiness wait
    //
    // Polls until the presence SIP session is Established and the
    // SIP.js UserAgent's internal userAgentCore is non-null.
    //
    // Why we need this:
    //   startCall() contains several `await` points (fetch call-log,
    //   proxy /tcn/session). Between those awaits the JS event loop
    //   can process a SIP `Terminated` event that stops the UA and
    //   sets TCN._ua = null. A plain up-front `_registered` check
    //   passes but by the time `new SIP.Inviter(TCN._ua, â€¦)` runs,
    //   _ua is null â†’ "Cannot read properties of null (reading
    //   'getLogger')".
    //
    // Also used when the call button is pressed during the ~3-second
    // reconnect window after a presence-session drop.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    TCN._isUaReady = function () {
        return !!(
            TCN._registered &&
            TCN._ua &&
            TCN._ua.userAgentCore   // null if ua.stop() was called
        );
    };

    TCN._waitForReady = function (timeoutMs) {
        timeoutMs = timeoutMs || 15000;
        return new Promise(function (resolve, reject) {
            if (TCN._isUaReady()) { resolve(); return; }
            var elapsed = 0;
            var interval = 300;
            var poll = setInterval(function () {
                elapsed += interval;
                if (TCN._isUaReady()) {
                    clearInterval(poll);
                    resolve();
                } else if (elapsed >= timeoutMs) {
                    clearInterval(poll);
                    reject(new Error(
                        'Timed out waiting for SIP agent to be READY (' + timeoutMs + 'ms)'
                    ));
                }
            }, interval);
        });
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // ACD Readiness wait
    //
    // Polls agentgetstatus until:
    //   statusDesc === 'READY'  AND  currentSessionId !== '0'
    //
    // WHY this is required:
    //   TCN takes ~30s after SIP Established to activate the ACD voice
    //   session. During that window agentgetstatus returns UNAVAILABLE /
    //   currentSessionId=0. Dialing with a zero/stale session SID causes
    //   TCN to immediately drop the call to WRAPUP with 0 duration.
    //   Likewise, if the previous call is still in WRAPUP the agent cannot
    //   accept a new dial until TCN transitions back to READY.
    //
    // Returns: the valid currentSessionId string to pass as sessionSid.
    // Throws:  if not READY within timeoutMs.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    TCN._waitForAcdReady = async function (timeoutMs) {
        timeoutMs = timeoutMs || 60000;
        var start   = Date.now();
        var POLL_MS = 3000;

        log('Waiting for TCN ACD READY + active sessionId (timeout=' + (timeoutMs / 1000) + 's)â€¦');

        while (Date.now() - start < timeoutMs) {
            var sid = TCN._voiceSessionSid || TCN._asmSessionSid;
            if (sid) {
                try {
                    var data      = await proxy('/tcn/status', { sessionSid: String(sid) });
                    var status    = (data.statusDesc || '').toUpperCase();
                    var currentId = String(data.currentSessionId || '0');

                    log('ACD readiness check', { statusDesc: status, currentSessionId: currentId });

                    if (status === 'READY' && currentId !== '0') {
                        log('TCN ACD READY â€” sessionId=' + currentId);
                        return currentId;
                    }

                    if (status === 'WRAPUP') {
                        log('Agent in WRAPUP â€” waiting for READY transitionâ€¦');
                    } else if (status === 'UNAVAILABLE' || currentId === '0') {
                        log('ACD session not yet active (status=' + status + ', sid=' + currentId + ') â€” retryingâ€¦');
                    } else {
                        log('ACD not ready (status=' + status + ') â€” retryingâ€¦');
                    }
                } catch (e) {
                    log('ACD readiness poll error (retrying)', e.message);
                }
            }
            await new Promise(function (r) { setTimeout(r, POLL_MS); });
        }

        throw new Error(
            'Timed out waiting for TCN ACD READY after ' + (timeoutMs / 1000) + 's. ' +
            'Ensure the SIP presence session is established and the TCN account is configured correctly.'
        );
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Outbound Call â€” per-call ASM session + SIP dial_url
    //
    // Each call creates a FRESH ASM session that includes the
    // destination phoneNumber. TCN configures the PSTN leg and
    // returns a call-specific dial_url in voiceRegistration.
    // The agent then invites sip:{dial_url}@sg-webphone.tcnp3.com â€”
    // TCN's gateway bridges that SIP session to the PSTN customer.
    //
    // Why not dial phone number directly?
    //   Dialling sip:+91XXXXXXXXXX@sg-webphone.tcnp3.com fails
    //   instantly â€” TCN's gateway only accepts dial_url tokens, not
    //   raw E.164 numbers, on the agent WebRTC transport.
    //
    // Lifecycle:
    //   new ASM session â†’ SIP INVITE(dial_url) â†’ Established â†’ Terminated
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Outbound Call â€” Manual Dial Operator API flow (v7.0)
    //
    // Architecture:
    //   1. Verify mic permission.
    //   2. Ensure presence SIP UA is alive (registered, userAgentCore non-null).
    //   3. Poll agentgetstatus with PRESENCE session SID until ACD reports
    //      statusDesc=READY AND currentSessionIdâ‰ 0. That currentSessionId is
    //      the ACD-registered session SID required by dialmanualprepare /
    //      manualdialstart. Polling the per-call session SID always returns
    //      currentSessionId=0 â€” only the presence session is in ACD.
    //   4. POST /tcn/dial (sessionSid=currentSessionId) â†’ server runs
    //      dialmanualprepare + processmanualdialcall + manualdialstart.
    //   5. TCN places the PSTN leg and bridges audio back to the agent via an
    //      INBOUND SIP INVITE (handled by onInvite â†’ _bridgeSession).
    //   6. Status poll every 10s: OUTBOUND_LOCKED â†’ PEERED â†’ INCALL â†’ READY.
    //
    // Why Manual Dial instead of SIP direct-dial:
    //   Direct SIP INVITE bypasses TCN's ACD. The agent stays READY in TCN's
    //   state machine, so HOLD / MUTE / DISCONNECT Operator APIs fail with
    //   "state READY does not handle event fsm.PutCallOnHold".
    //
    // Why NO per-call ASM session / outbound SIP INVITE:
    //   dialmanualprepare expects the ACD presence session SID, not a per-call
    //   voiceSessionSid. Sending a per-call SID causes TCN to fail to find the
    //   agent's READY state â†’ immediate WRAPUP with 0 duration.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN.startCall = async function (phone, leadId) {
        // â”€â”€ Pre-checks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if (!TCN._loggedIn) {
            throw new Error('TCN not logged in. Call TCN.login() first.');
        }
        if (TCN._callActive) {
            throw new Error('A call is already active.');
        }

        // â”€â”€ Validate phone (exactly 10 local digits) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // countryCode "91" is sent separately in the TCN API payload.
        // Sending e.g. "916383702482" (12 digits) + countryCode="91" causes
        // duplicate country-code â†’ TCN validation failure â†’ Result: Invalid, 0 duration.
        var digits = String(phone || '').replace(/\D/g, '');
        if (digits.startsWith('91') && digits.length === 12) digits = digits.slice(2);
        if (digits.startsWith('00')) digits = digits.slice(2);
        if (digits.length !== 10) {
            throw new Error('Invalid phone number â€” exactly 10 digits required, got ' + digits.length + ' ("' + phone + '"). Do not include country code.');
        }
        var e164Display = '+91' + digits;

        // â”€â”€ Mark call active early so state is consistent during awaits â”€â”€
        TCN._callActive        = true;
        TCN._callStartTime     = Date.now();
        TCN._callEstablishedAt = 0;
        TCN._callAnsweredSynced = false;
        TCN._activePhone       = phone;
        TCN._activeLeadId      = leadId || null;

        // Helper: full rollback on any error path
        function _abortCall(callLogId) {
            TCN._stopCallKeepAlive();
            TCN._stopCallStatusPoll();
            clearAnswerTimer();
            // BYE / cancel the per-call SIP session if it was created
            if (TCN._outboundSession) {
                var obs = TCN._outboundSession;
                TCN._outboundSession = null;
                try {
                    if (obs.state === 'Initial' || obs.state === 'Establishing') obs.cancel();
                    else if (obs.state === 'Established') obs.bye();
                } catch (_) {}
            }
            TCN._callActive          = false;
            TCN._callStartTime       = 0;
            TCN._callEstablishedAt   = 0;
            TCN._activePhone         = null;
            TCN._activeLogId         = null;
            TCN._activeLeadId        = null;
            TCN._callVoiceSessionSid = null;
            if (callLogId) patchCallLog(callLogId, { status: 'failed' });
        }

        var callLogId = null;

        try {

            // â”€â”€ Step 1: Ensure microphone permission â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // getUserMedia BEFORE the SIP INVITE so the SDP offer always
            // carries a real audio track. Without this, TCN can establish
            // the SIP dialog but has no audio to bridge â†’ instant WRAPUP.
            log('Requesting microphone permissionâ€¦');
            try {
                var micStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                // Release the test stream; SIP.js will acquire its own during invite().
                micStream.getTracks().forEach(function (t) { t.stop(); });
                log('Microphone permission granted');
            } catch (micErr) {
                throw new Error('Microphone permission denied or unavailable: ' + micErr.message);
            }

            // â”€â”€ Step 2: Ensure presence UA is alive â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            if (!TCN._isUaReady()) {
                log('startCall: SIP UA not ready â€” waiting up to 15sâ€¦');
                try {
                    await TCN._waitForReady(15000);
                } catch (waitErr) {
                    throw new Error('TCN agent not READY â€” ' + waitErr.message);
                }
            }

            // â”€â”€ Step 3: Wait for ACD READY using the PRESENCE session SID â”€â”€
            //
            // Manual Dial Operator API requires the ACD-registered session SID
            // (currentSessionId from agentgetstatus on the PRESENCE session).
            // Polling the per-call session SID always returns currentSessionId=0
            // because the ACD doesn't recognise per-call sub-sessions â€” only the
            // presence (login) session is registered in the ACD state machine.
            //
            // Audio for the call arrives as an INBOUND SIP INVITE from TCN
            // (handled by the onInvite delegate above as _bridgeSession).
            // No per-call ASM session or outbound SIP INVITE is needed here.
            var presenceSid = String(TCN._voiceSessionSid || TCN._asmSessionSid || '');
            if (!presenceSid || presenceSid === '0') {
                throw new Error('No presence session SID â€” cannot initiate Manual Dial. Ensure login() completed successfully.');
            }

            log('Waiting for ACD READY (presence sessionSid=' + presenceSid + ')â€¦');
            var acdSessionSid = null;
            var acdDeadline   = Date.now() + 60000;  // 60 s â€” TCN can take ~30 s to activate

            while (Date.now() < acdDeadline) {
                // Guard: UA may have stopped while we were waiting
                if (!TCN._ua || !TCN._ua.userAgentCore) {
                    throw new Error('SIP presence UA stopped during ACD wait â€” reload and retry');
                }
                try {
                    var statusData = await proxy('/tcn/status', { sessionSid: presenceSid });
                    var acdStatus  = (statusData.statusDesc || '').toUpperCase();
                    var currentSid = String(statusData.currentSessionId || '0');

                    log('ACD readiness check', { statusDesc: acdStatus, currentSessionId: currentSid });

                    if (acdStatus === 'READY' && currentSid !== '0') {
                        acdSessionSid = currentSid;
                        log('ACD READY â€” using sessionSid=' + acdSessionSid);
                        break;
                    }
                    if (acdStatus === 'WRAPUP') {
                        log('Agent in WRAPUP â€” waiting for READY transitionâ€¦');
                    } else if (acdStatus === 'UNAVAILABLE' || currentSid === '0') {
                        log('ACD session not yet active (status=' + acdStatus + ', currentSid=' + currentSid + ') â€” retryingâ€¦');
                    } else {
                        log('ACD not ready (status=' + acdStatus + ') â€” retryingâ€¦');
                    }
                } catch (pollErr) {
                    log('ACD readiness poll error (retrying)', pollErr.message);
                }
                await new Promise(function (r) { setTimeout(r, 3000); });
            }

            if (!acdSessionSid) {
                throw new Error(
                    'Timed out waiting for TCN ACD READY after 60s. ' +
                    'Check: 1) Caller ID configured? 2) Hunt group allows manual dial? ' +
                    '3) SIP presence session fully established?'
                );
            }

            // â”€â”€ Step 6: Create DB call-log (non-fatal) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            try {
                var logRes = await fetch('/tcn/call-log', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body:    JSON.stringify({ lead_id: leadId || null, phone: phone }),
                });
                if (logRes.ok) {
                    callLogId = (await logRes.json()).call_log_id;
                } else {
                    log('call-log create failed (non-fatal), HTTP ' + logRes.status);
                }
            } catch (logErr) {
                log('call-log create error (non-fatal)', logErr.message);
            }
            TCN._activeLogId = callLogId;

            // Store the confirmed ACD session SID.
            TCN._callVoiceSessionSid = acdSessionSid;

            log('Manual Dial â€” sessionSid=' + TCN._callVoiceSessionSid + ', phone=' + e164Display + ' (digits=' + digits + ')');

            fire('tcn:callStarted', { phone: phone, callLogId: callLogId });

            // Keep-alive on the per-call ACD session
            TCN._startCallKeepAlive(TCN._callVoiceSessionSid);

            // â”€â”€ Step 7: Manual Dial Operator API â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // Server: dialmanualprepare â†’ processmanualdialcall â†’ manualdialstart
            // sessionSid = ACD currentSessionId (from presence session poll above).
            // TCN places the PSTN leg and bridges audio back via inbound SIP INVITE.
            log('Manual Dial payload', { sessionSid: TCN._callVoiceSessionSid, phoneNumber: digits });
            var dialResult = await proxy('/tcn/dial', {
                sessionSid: TCN._callVoiceSessionSid,
                phone:      digits,   // 10 local digits; countryCode "91" added server-side
            });

            log('Manual Dial result', dialResult);
            log('Manual Dial validation flags', {
                callSid:            dialResult.callSid,
                taskGroupSid:       dialResult.taskGroupSid,
                ok:                 dialResult.ok,
                tcn_status:         dialResult.tcn_status,
                isDialValidationOk: dialResult.isDialValidationOk,
                isDnclScrubOk:      dialResult.isDnclScrubOk,
                isTimeZoneScrubOk:  dialResult.isTimeZoneScrubOk,
                tcn_body:           dialResult.tcn_body,
            });

            if (dialResult.validationError) {
                throw new Error('TCN validation failed: ' + dialResult.validationError);
            }
            if (!dialResult.ok) {
                log('WARNING: manualdialstart returned not-ok â€” call may go to WRAPUP immediately', dialResult.tcn_body);
            }

            // Persist call state across page navigations.
            saveCallState();

            // â”€â”€ Step 8: Poll for INCALL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // READY â†’ OUTBOUND_LOCKED â†’ PEERED â†’ INCALL
            TCN._startCallStatusPoll();

        } catch (err) {
            _abortCall(callLogId);
            fire('tcn:error', { message: 'Call failed: ' + (err.message || err) });
            throw err;
        }
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // End Call â€” agentdisconnect Operator API + SIP BYE
    //
    // Sends TCN agentdisconnect to terminate the PSTN leg, then BYEs
    // the per-call SIP session (_outboundSession) created in startCall.
    // The presence SIP session (_sipSession / _ua) stays alive so the
    // agent remains READY for the next call.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN.endCall = async function (outcome) {
        if (!TCN._callActive) {
            log('endCall: no active call');
            return;
        }
        // Guard against double-click / multiple callers racing on the End button.
        if (TCN._endCallInProgress) {
            log('endCall: already in progress â€” ignoring duplicate call');
            return;
        }
        TCN._endCallInProgress = true;

        TCN._stopCallStatusPoll();
        TCN._stopCallKeepAlive();
        clearAnswerTimer();

        // Signal UI immediately so the End button disables and "Endingâ€¦" appears.
        fire('tcn:callEnding');

        var sid        = TCN._callVoiceSessionSid;
        var endedLogId = TCN._activeLogId;
        var endedPhone = TCN._activePhone;
        var duration   = TCN._callEstablishedAt
            ? Math.round((Date.now() - TCN._callEstablishedAt) / 1000) : 0;
        // Capture answered_at BEFORE clearing _callEstablishedAt below.
        // Required so the final patchCallLog can include it (backend rejects
        // status=completed without answered_at â†’ 422).
        var answeredAt = TCN._callEstablishedAt
            ? new Date(TCN._callEstablishedAt).toISOString() : null;

        // Patch outcome to DB (non-fatal, fire-and-forget)
        if (endedLogId && outcome) {
            try {
                await fetch('/tcn/call-log/' + endedLogId, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ outcome: outcome }),
                });
            } catch (_) {}
        }

        // â”€â”€ Terminate bridge and per-call SIP sessions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // Bridge session = inbound TCN INVITE accepted during Manual Dial.
        if (TCN._bridgeSession) {
            var bsEnd = TCN._bridgeSession;
            TCN._bridgeSession = null;
            try {
                if (bsEnd.state === 'Established') bsEnd.bye();
                else if (bsEnd.state === 'Initial' || bsEnd.state === 'Establishing') bsEnd.reject();
            } catch (_) {}
            log('endCall: bridge SIP session terminated');
        }
        // _outboundSession is only set in direct-SIP-dial mode (legacy path).
        if (TCN._outboundSession) {
            var obs = TCN._outboundSession;
            TCN._outboundSession = null;
            try {
                if (obs.state === 'Initial' || obs.state === 'Establishing') obs.cancel();
                else if (obs.state === 'Established') obs.bye();
            } catch (_) {}
            log('endCall: outbound SIP session terminated');
        }
        if (TCN._callUa) {
            try { TCN._callUa.stop(); } catch (_) {}
            TCN._callUa = null;
        }

        // â”€â”€ Send agentdisconnect â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if (sid) {
            try {
                var disconnResp = await proxy('/tcn/disconnect', { sessionSid: String(sid) });
                log('agentdisconnect sent (sessionSid=' + sid + ')', disconnResp);
            } catch (e) {
                log('endCall: agentdisconnect failed (non-fatal) â€” proceeding', e.message);
            }

            // â”€â”€ Confirmation poll (max 8 s) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // agentdisconnect is asynchronous on TCN's side: the ACD state machine
            // transitions INCALL â†’ WRAPUP after the HTTP response returns.
            // Poll until we see WRAPUP or READY before updating the UI, so the
            // displayed end state is always in sync with TCN's reality.
            var confirmed = false;
            for (var attempt = 0; attempt < 4; attempt++) {
                await new Promise(function (r) { setTimeout(r, 2000); });
                try {
                    var check = await proxy('/tcn/status', { sessionSid: String(sid) });
                    var confirmStatus = (check.statusDesc || '').toUpperCase();
                    log('Post-disconnect status #' + (attempt + 1), confirmStatus);
                    if (confirmStatus === 'WRAPUP' || confirmStatus === 'READY') {
                        confirmed = true;
                        log('TCN confirmed call ended (status=' + confirmStatus + ')');
                        break;
                    }
                } catch (_) { break; }
            }
            if (!confirmed) {
                log('endCall: confirmation timed out after 8 s â€” proceeding with local teardown');
            }
        }

        // Clear persisted call state â€” must happen BEFORE firing tcn:callEnded
        // so that if resumeActiveCall() runs before the event is handled it finds nothing.
        clearCallState();

        // Reset call state â€” presence SIP session stays alive; agent returns to READY.
        TCN._callActive          = false;
        TCN._endCallInProgress   = false;
        TCN._callStartTime       = 0;
        TCN._callEstablishedAt   = 0;
        TCN._callAnsweredSynced  = false;
        TCN._activePhone         = null;
        TCN._activeLogId         = null;
        TCN._activeLeadId        = null;
        TCN._callVoiceSessionSid = null;
        TCN._onHold              = false;

        if (endedLogId) {
            // status=completed requires answered_at â€” send it when the call was live.
            // When call was never answered (answeredAt===null) use status=failed to
            // avoid a 422 from the backend's answered_at requirement.
            var endCallPatch = {
                duration: duration,
                ended_at: new Date().toISOString(),
            };
            if (answeredAt) {
                endCallPatch.status      = 'completed';
                endCallPatch.answered_at = answeredAt;
            } else {
                endCallPatch.status = 'failed';
            }
            patchCallLog(endedLogId, endCallPatch);
        }

        fire('tcn:callEnded', { phone: endedPhone, callLogId: endedLogId, duration: duration });
        log('endCall complete â€” duration ' + duration + 's, answered=' + !!answeredAt);
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Mute / Unmute â€” disable / enable local mic track
    //
    // Priority: per-call SIP session (_outboundSession) > bridge session
    // (_bridgeSession) > presence session (_sipSession).
    // _outboundSession is created fresh for every outbound call (startCall),
    // so it always carries the call's microphone sender track.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    function _activeCallSession() {
        // Return the session whose peer connection holds the live mic sender.
        if (TCN._callActive) {
            if (TCN._outboundSession && TCN._outboundSession.sessionDescriptionHandler) return TCN._outboundSession;
            if (TCN._bridgeSession   && TCN._bridgeSession.sessionDescriptionHandler)   return TCN._bridgeSession;
        }
        return TCN._sipSession;
    }

    TCN.mute = function () {
        var session = _activeCallSession();
        if (!session || !session.sessionDescriptionHandler) {
            log('mute: no active SIP session');
            return;
        }
        session.sessionDescriptionHandler.peerConnection
            .getSenders().forEach(function (s) {
                if (s.track && s.track.kind === 'audio') s.track.enabled = false;
            });
        log('Muted (local mic disabled)');
    };

    TCN.unmute = function () {
        var session = _activeCallSession();
        if (!session || !session.sessionDescriptionHandler) {
            log('unmute: no active SIP session');
            return;
        }
        session.sessionDescriptionHandler.peerConnection
            .getSenders().forEach(function (s) {
                if (s.track && s.track.kind === 'audio') s.track.enabled = true;
            });
        log('Unmuted (local mic enabled)');
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Hold / Resume  (TCN Operator API â€” agentputcallonhold / agentgetcallfromhold)
    //
    // sessionSid = TCN._callVoiceSessionSid  (set on each outbound call)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN._onHold = false;

    TCN.hold = async function () {
        var sid = TCN._callVoiceSessionSid;
        if (!sid) { log('hold: no call session SID'); return; }

        // Use _callEstablishedAt as the authoritative "call is live" indicator.
        // Querying agentgetstatus can return PEERED even when audio is bridged and
        // the call is fully active (some TCN environments stay PEERED while audio
        // flows via SDP re-negotiation). TCN returns 500
        // "state READY does not handle event fsm.PutCallOnHold" only when there
        // is genuinely no call registered in ACD â€” i.e. before the dial completed.
        // _callEstablishedAt is set by either:
        //   â€¢ the SIP bridge INVITE reaching Established, or
        //   â€¢ the status-poll detecting INCALL/TALKING, or
        //   â€¢ the 20s PEERED fallback (audio already flowing).
        if (!TCN._callEstablishedAt) {
            log('hold blocked â€” call not yet answered (_callEstablishedAt=0)');
            fire('tcn:error', { message: 'Cannot hold â€” call not yet connected' });
            return;
        }

        try {
            await proxy('/tcn/hold', { sessionSid: String(sid), holdType: 'SIMPLE' });
            TCN._onHold = true;
            log('Call placed on hold (sessionSid=' + sid + ')');
            fire('tcn:onHold');
        } catch (e) {
            log('hold failed', e.message);
            fire('tcn:error', { message: 'Hold failed: ' + e.message });
        }
    };

    TCN.resume = async function () {
        var sid = TCN._callVoiceSessionSid;
        if (!sid) { log('resume: no call session SID'); return; }
        try {
            await proxy('/tcn/resume', { sessionSid: String(sid) });
            TCN._onHold = false;
            log('Call resumed from hold (sessionSid=' + sid + ')');
            fire('tcn:offHold');
        } catch (e) {
            log('resume failed (non-fatal)', e.message);
        }
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // DTMF  (TCN Operator API â€” playdtmf)
    // digit: '0'-'9', '*', '#'
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN.dtmf = async function (digit) {
        var sid = TCN._callVoiceSessionSid;
        if (!sid) { log('dtmf: no call session SID'); return; }
        try {
            await proxy('/tcn/dtmf', { sessionSid: String(sid), digit: String(digit) });
            log('DTMF sent: ' + digit);
        } catch (e) {
            log('dtmf failed (non-fatal)', e.message);
        }
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Call Status Polling
    //
    // Polls agentgetstatus every 10s during an active Manual Dial call.
    //   READY â†’ INCALL  : customer answered â†’ fire tcn:callAnswered
    //   INCALL â†’ READY  : remote hangup â†’ fire tcn:callEnded via _handleCallEnded
    //   No answer after 3 min: assume failed â†’ _handleCallEnded
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN._startCallStatusPoll = function () {
        TCN._stopCallStatusPoll();
        var pollCount = 0;
        var wrapupCount = 0;   // consecutive WRAPUP polls before INCALL
        var POLL_MS = 10000;
        var MAX_POLLS = 18;    // 3 minutes at 10s intervals
        var WRAPUP_FAIL = 3;   // 3 consecutive WRAPUP polls (30s) = call failed on TCN side

        TCN._callStatusPollTimer = setInterval(async function () {
            if (!TCN._callActive) {
                TCN._stopCallStatusPoll();
                return;
            }

            var sid = TCN._callVoiceSessionSid;
            if (!sid) return;

            try {
                var data       = await proxy('/tcn/status', { sessionSid: sid });
                var status     = (data.statusDesc || '').toUpperCase();
                var currentSid = String(data.currentSessionId || '0');
                pollCount++;

                log('Call status poll #' + pollCount, {
                    statusDesc: status,
                    currentSessionId: currentSid,
                });

                // OUTBOUND_LOCKED / PEERED = TCN accepted the dial, placing the PSTN leg.
                //   OUTBOUND_LOCKED â†’ TCN received the request, initiating the PSTN leg.
                //   PEERED          â†’ bridging to PSTN in progress (phone ringing on customer side).
                // Full normal sequence: READY â†’ OUTBOUND_LOCKED â†’ PEERED â†’ INCALL.
                if (status === 'OUTBOUND_LOCKED' || status === 'PEERED') {
                    wrapupCount = 0;
                    log(status + ' â€” waiting for PSTN answerâ€¦');
                    if (status === 'PEERED') {
                        // Notify UI to show "Connectingâ€¦" â€” timer starts only on real INCALL/SIP-Established.
                        fire('tcn:callPeered', { phone: TCN._activePhone });
                    }
                    return;
                }

                // Customer answered â€” TCN confirmed INCALL state.
                // Route through markCallAnswered() which guards against duplicate fires
                // via _callAnsweredSynced â€” SIP bridge Established may have already fired.
                if (status === 'INCALL' || status === 'TALKING') {
                    wrapupCount = 0;
                    markCallAnswered('status-poll-incall');
                }

                // Remote party ended the call.
                // TCN state machine after customer hangs up: INCALL â†’ WRAPUP â†’ READY.
                // Catch WRAPUP immediately â€” do NOT wait for the READY poll cycle.
                // Both states with callEstablishedAt set reliably indicate the call is over.
                if ((status === 'WRAPUP' || status === 'READY') && TCN._callEstablishedAt) {
                    log('Remote hangup detected (TCN status=' + status + ')');
                    TCN._handleCallEnded();
                    return;
                }

                // WRAPUP after dial without ever reaching INCALL = call failed on TCN side.
                // TCN drops directly to WRAPUP when: duplicate country code causes validation
                // failure, session mismatch, ACD routing error, or DNCL/timezone scrub block.
                if (status === 'WRAPUP' && !TCN._callEstablishedAt) {
                    wrapupCount++;
                    log('Call stuck in WRAPUP (' + wrapupCount + '/' + WRAPUP_FAIL + ') â€” never reached INCALL');
                    if (wrapupCount >= WRAPUP_FAIL) {
                        log('Call failed â€” agent stuck in WRAPUP (likely TCN validation failure)');
                        TCN._handleCallEnded();
                        return;
                    }
                } else if (status !== 'WRAPUP') {
                    wrapupCount = 0;
                }

                // No answer timeout
                if (pollCount >= MAX_POLLS && !TCN._callEstablishedAt) {
                    log('Call timed out â€” no answer after ' + Math.round((pollCount * POLL_MS) / 1000) + 's');
                    TCN._handleCallEnded();
                }

            } catch (e) {
                log('Call status poll error (non-fatal)', e.message);
            }
        }, POLL_MS);
    };

    TCN._stopCallStatusPoll = function () {
        if (TCN._callStatusPollTimer) {
            clearInterval(TCN._callStatusPollTimer);
            TCN._callStatusPollTimer = null;
        }
    };

    // Common teardown for remote-hangup and timeout (NOT agent-initiated endCall).
    // endCall() has its own confirmation poll; this path fires from the status poll.
    TCN._handleCallEnded = function () {
        TCN._stopCallStatusPoll();
        TCN._stopCallKeepAlive();
        clearAnswerTimer();

        var duration     = TCN._callEstablishedAt
            ? Math.round((Date.now() - TCN._callEstablishedAt) / 1000) : 0;
        // Capture answered_at BEFORE _callEstablishedAt is zeroed below.
        var answeredAtTs = TCN._callEstablishedAt
            ? new Date(TCN._callEstablishedAt).toISOString() : null;
        var endedLogId   = TCN._activeLogId;
        var endedPhone   = TCN._activePhone;

        clearCallState(); // remove persisted call so reload doesn't try to resume it

        TCN._callActive          = false;
        TCN._endCallInProgress   = false;
        TCN._callStartTime       = 0;
        TCN._callEstablishedAt   = 0;
        TCN._callAnsweredSynced  = false;
        TCN._activePhone         = null;
        TCN._activeLogId         = null;
        TCN._activeLeadId        = null;
        TCN._callVoiceSessionSid = null;
        TCN._onHold              = false;

        if (endedLogId) {
            // status=completed requires answered_at â€” only send it when the call was
            // actually live. Unanswered calls (answeredAtTs===null) use status=failed
            // to avoid a 422 from the backend's answered_at validation.
            var handleEndPatch = {
                duration: duration,
                ended_at: new Date().toISOString(),
            };
            if (answeredAtTs) {
                handleEndPatch.status      = 'completed';
                handleEndPatch.answered_at = answeredAtTs;
            } else {
                handleEndPatch.status = 'failed';
            }
            patchCallLog(endedLogId, handleEndPatch);
        }

        fire('tcn:callEnded', { phone: endedPhone, callLogId: endedLogId, duration: duration });
        log('Call ended (remote/timeout) â€” duration ' + duration + 's, answered=' + !!answeredAtTs);
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // loginWithToken â€” skip /api/tcn/config fetch (step 1) when the
    // caller already has credentials. Used by TcnService.init() to
    // avoid a redundant config request. Runs steps 2â€“5 identically
    // to login().
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN.loginWithToken = async function (accessToken, agentId, huntGroupId, callerId) {
        if (TCN._loggedIn) { log('Already logged in'); return; }
        if (TCN._loginInProgress) { log('Login already in progress, skipping.'); return; }
        TCN._loginInProgress = true;

        try {
            TCN._accessToken = accessToken;
            TCN._agentSid = String(agentId || '');
            TCN._huntGroupSid = String(huntGroupId || '');
            TCN._callerId = String(callerId || '');
            log('loginWithToken: credentials injected', {
                agentSid: TCN._agentSid,
                huntGroupSid: TCN._huntGroupSid,
            });

            if (await canResumeCachedSession()) {
                // Session is alive â€” skip config + skills fetch.
                // dialUrl is single-use so always create a fresh ASM session for new SIP creds.
                log('Step 3 (cached): Creating fresh ASM session for new dial URL\u2026');
                var cachedSessionResp = await proxy('/tcn/session', {
                    huntGroupSid: parseInt(TCN._huntGroupSid) || 0,
                    skills: TCN._skills,
                    subsession_type: 'VOICE',
                });
                TCN._asmSessionSid = cachedSessionResp.asmSessionSid || cachedSessionResp.asm_session_sid;
                TCN._voiceSessionSid = cachedSessionResp.voiceSessionSid || cachedSessionResp.voice_session_sid;
                var cachedVr = cachedSessionResp.voiceRegistration || cachedSessionResp.voice_registration;
                if (!cachedVr || !cachedVr.dialUrl && !cachedVr.dial_url) {
                    clearCache();
                    throw new Error('Cached-path ASM session missing voice_registration');
                }
                TCN._sipUser = cachedVr.username;
                TCN._sipPass = cachedVr.password;
                TCN._dialUrl = cachedVr.dialUrl || cachedVr.dial_url;
                writeCache();
                log('ASM session (cached path)', {
                    asmSid: TCN._asmSessionSid,
                    voiceSid: TCN._voiceSessionSid,
                    dialUrl: TCN._dialUrl,
                });

                await loadSipJs();
                var CachedSIP = (window.SIP && window.SIP.SIP) ? window.SIP.SIP : window.SIP;
                if (!CachedSIP || !CachedSIP.UserAgent) {
                    throw new Error('SIP.js not loaded - /js/sip.js must export window.SIP');
                }

                await callDialUrl(CachedSIP);

                // Keep-alive only after SIP Established
                TCN._startKeepAlive();

                TCN._loggedIn = true;
                TCN._loginInProgress = false;
                TCN.isReady = true;
                window.isSoftphoneReady = true;
                log('loginWithToken complete using cached session');
                fire('tcn:ready');
                return;
            }

            TCN._cleanupSip();
            TCN._asmSessionSid = null;
            TCN._voiceSessionSid = null;

            // Step 2 â€” Agent skills
            log('Step 2: Getting agent skills\u2026');
            var skillsData = await proxy('/tcn/skills', {
                huntGroupSid: parseInt(TCN._huntGroupSid) || 0,
                agentSid: parseInt(TCN._agentSid) || 0,
            });
            TCN._skills = skillsData.skills || {};
            log('Skills loaded', TCN._skills);

            // Step 3 â€” Create ASM session (SIP credentials)
            log('Step 3: Creating ASM session\u2026');
            var session = await proxy('/tcn/session', {
                huntGroupSid: parseInt(TCN._huntGroupSid) || 0,
                skills: TCN._skills,
                subsession_type: 'VOICE',
            });

            TCN._asmSessionSid = session.asmSessionSid || session.asm_session_sid;
            TCN._voiceSessionSid = session.voiceSessionSid || session.voice_session_sid;

            var vr = session.voiceRegistration || session.voice_registration;
            if (!vr) {
                throw new Error('ASM session missing voice_registration. Full: ' + JSON.stringify(session));
            }
            TCN._sipUser = vr.username;
            TCN._sipPass = vr.password;
            TCN._dialUrl = vr.dialUrl || vr.dial_url;
            if (!TCN._sipUser || !TCN._sipPass || !TCN._dialUrl) {
                throw new Error('ASM session voice_registration missing username/password/dialUrl');
            }
            writeCache();
            log('ASM session', {
                asmSid: TCN._asmSessionSid,
                voiceSid: TCN._voiceSessionSid,
                sipUser: TCN._sipUser,
                dialUrl: TCN._dialUrl,
            });

            // Step 4 â€” Load SIP.js and establish presence SIP session
            log('Loading SIP.js\u2026');
            await loadSipJs();
            var SIP = (window.SIP && window.SIP.SIP) ? window.SIP.SIP : window.SIP;
            if (!SIP || !SIP.UserAgent) {
                throw new Error('SIP.js not loaded \u2014 /js/sip.js must export window.SIP');
            }
            await callDialUrl(SIP);

            // Step 5 â€” Start keep-alive only after SIP Established (agent is READY)
            TCN._startKeepAlive();

            TCN._loggedIn = true;
            TCN._loginInProgress = false;
            TCN.isReady = true;
            window.isSoftphoneReady = true;
            log('loginWithToken complete \u2014 agent READY');
            fire('tcn:ready');

        } catch (e) {
            clearCache();
            TCN._loginInProgress = false;
            log('loginWithToken failed', e.message);
            fire('tcn:error', { message: e.message });
            throw e;
        }
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Logout â€” explicit teardown
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN.logout = function () {
        TCN._stopKeepAlive();
        TCN._stopCallKeepAlive();
        TCN._stopCallStatusPoll();
        TCN._cleanupSip();

        TCN._loggedIn = false;
        TCN._callActive = false;
        TCN._reconnecting = false;
        TCN._callAnsweredSynced = false;
        TCN._accessToken = null;
        TCN._asmSessionSid = null;
        TCN._voiceSessionSid = null;
        TCN._callStartTime = 0;
        TCN._activePhone = null;
        TCN._activeLogId = null;
        TCN._keepAliveFailCount = 0;
        TCN.isReady = false;
        window.isSoftphoneReady = false;

        _releaseTabLock();
        log('Logged out');
        fire('tcn:loggedOut');
    };

    // Allow agents to force-clear a stale cross-tab lock from DevTools
    // if the other tab crashed without firing beforeunload.
    TCN.forceUnlock = function () {
        _releaseTabLock();
        log('Cross-tab lock force-released');
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Resume Active Call â€” called once after login completes.
    //
    // If the agent had an active call when they navigated away or
    // refreshed the page, this restores only in-memory in-call state.
    //
    // Flow:
    //   1. Read in-memory call state
    //   2. Poll TCN agentgetstatus with the saved sessionSid
    //   3a. INCALL / OUTBOUND_LOCKED â†’ restore in-memory state, restart
    //       the status poll and keep-alive, fire tcn:callStarted /
    //       tcn:callAnswered so the softphone UI snaps to the right panel
    //   3b. WRAPUP / READY / other â†’ call ended while page was unloaded;
    //       patch the call-log and discard the saved state
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    TCN.resumeActiveCall = async function () {
        var saved = readCallState();
        if (!saved || !saved.sessionSid) return false;

        // Discard states older than CALL_STATE_TTL_MS (default 2 h)
        if ((Date.now() - (saved.savedAt || 0)) > TCN.CALL_STATE_TTL_MS) {
            log('resumeActiveCall: persisted state is stale â€” discarding');
            clearCallState();
            return false;
        }

        log('resumeActiveCall: checking persisted callâ€¦', {
            sessionSid: saved.sessionSid,
            phone:      saved.phone,
        });

        try {
            var data          = await proxy('/tcn/status', { sessionSid: String(saved.sessionSid) });
            var status        = (data.statusDesc || '').toUpperCase();
            var currentSid    = String(data.currentSessionId || '0');
            var liveSid       = (currentSid !== '0') ? currentSid : saved.sessionSid;

            log('resumeActiveCall: TCN status', { status, currentSid });

            if (status === 'INCALL' || status === 'TALKING' || status === 'OUTBOUND_LOCKED') {
                // â”€â”€ Restore in-call state â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                TCN._callActive          = true;
                TCN._callVoiceSessionSid = String(liveSid);
                TCN._activePhone         = saved.phone      || '';
                TCN._activeLogId         = saved.callLogId  || null;
                TCN._activeLeadId        = saved.leadId     || null;
                TCN._callStartTime       = saved.callStartTime  || Date.now();
                TCN._callEstablishedAt   = saved.callEstablishedAt || 0;

                if (status === 'INCALL' || status === 'TALKING') {
                    // Call was answered â€” restore the established timestamp if missing
                    if (!TCN._callEstablishedAt) {
                        TCN._callEstablishedAt = Date.now();
                    }
                    // Save updated state (fixes missing callEstablishedAt)
                    saveCallState();
                    // Notify UI: jump straight to the in-call (answered) panel
                    fire('tcn:callStarted',  { phone: TCN._activePhone, callLogId: TCN._activeLogId });
                    fire('tcn:callAnswered', { phone: TCN._activePhone, callLogId: TCN._activeLogId, restored: true });
                    log('resumeActiveCall: restored INCALL â€” timer offset = ' +
                        Math.round((Date.now() - TCN._callEstablishedAt) / 1000) + 's');
                } else {
                    // Still ringing (OUTBOUND_LOCKED)
                    fire('tcn:callStarted', { phone: TCN._activePhone, callLogId: TCN._activeLogId });
                    log('resumeActiveCall: restored OUTBOUND_LOCKED (still ringing)');
                }

                TCN._startCallKeepAlive(TCN._callVoiceSessionSid);
                TCN._startCallStatusPoll();
                return true;
            }

            // Call ended while the page was unloaded â€” clean up
            var elapsed = (saved.callEstablishedAt && saved.callEstablishedAt > 0)
                ? Math.round((Date.now() - saved.callEstablishedAt) / 1000)
                : 0;
            clearCallState();
            if (saved.callLogId) {
                patchCallLog(saved.callLogId, {
                    status:   'completed',
                    duration: elapsed,
                    ended_at: new Date().toISOString(),
                });
            }
            log('resumeActiveCall: call ended during reload (TCN status=' + status + '), log patched');
            return false;

        } catch (e) {
            log('resumeActiveCall: status check failed (non-fatal) â€” discarding state', e.message);
            clearCallState();
            return false;
        }
    };

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Expose globally
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    window.TCN = TCN;

})();

