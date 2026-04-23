/**
 * tcn-softphone.js  — v5.0  SIP direct-dial edition
 * ──────────────────────────────────────────────────────────────────
 * Architecture:
 *
 * Login flow (REST + SIP):
 *   1. /api/tcn/config  → access_token, agent_id, hunt_group_id
 *   2. /tcn/skills      → skills map
 *   3. /tcn/session     → asmSessionSid, voiceSessionSid,
 *                         voiceRegistration { username, password, dialUrl }
 *   4. SIP.js UA created with credentials from step 3
 *   5. SIP INVITE → sip:{dialUrl}@sg-webphone.tcnp3.com
 *      → puts agent in READY state in TCN (presence session)
 *   6. Keep-alive: /tcn/keepalive every 30 s (fires immediately after login)
 *      → on 3 consecutive failures: cleanup + re-login
 *
 * Outbound call flow (pure SIP — no manualdial REST API):
 *   7.  Create DB call-log via POST /tcn/call-log  (non-fatal if it fails)
 *   8.  Create new SIP.Inviter on the existing UA:
 *         target = sip:{+91XXXXXXXXXX}@sg-webphone.tcnp3.com
 *       → agent sends SIP INVITE directly to TCN's WebRTC gateway
 *       → TCN routes the call to PSTN
 *   9.  On stateChange Established: attach remote audio, fire tcn:callAnswered
 *   10. On stateChange Terminated:  update call-log, fire tcn:callEnded
 *   11. endCall: cancel() if still Establishing, bye() if Established
 *
 * Phone validation:
 *   - Strips non-digits, strips leading "91" (12 digits) or "00"
 *   - Requires exactly 10 local digits → normalised to +91XXXXXXXXXX
 *
 * Events fired on window:
 *   tcn:ready        — login complete, agent READY
 *   tcn:callStarted  — SIP INVITE sent        { phone, callLogId }
 *   tcn:callAnswered — remote party answered  { phone, callLogId }
 *   tcn:callEnded    — call terminated        { phone, callLogId, duration }
 *   tcn:sipDropped   — presence SIP session fell, reconnect scheduled
 *   tcn:loggedOut    — logout() completed
 *   tcn:error        — { message }
 */

(function () {
    "use strict";

    // ─────────────────────────────────────────────────────────────
    // State
    // ─────────────────────────────────────────────────────────────
    var TCN = {
        // Auth / session
        _accessToken: null,
        _agentSid: null,
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
        _outboundSession: null,     // active outbound call SIP session
        _registered: false,

        // Call tracking
        _callStartTime: 0,
        _callEstablishedAt: 0,
        _activePhone: null,
        _activeLogId: null,
        _activeLeadId: null,
        _activeCallSid: null,
        // Disposition to apply once createInboundCallLog resolves (race condition guard)
        _pendingIncomingDisposition: null,
        // True when the agent explicitly clicked End on an accepted incoming call
        _agentEndedIncomingCall: false,

        // Keep-alive — login/presence session
        _keepAliveTimer: null,
        _keepAliveFailCount: 0,
        KEEPALIVE_MS: 30000,  // 30 s keep-alive interval

        // Keep-alive — outbound call session (ACD voice session SID)
        _callKeepAliveTimer: null,
        _callVoiceSessionSid: null,

        // Status polling — detect INCALL / call-ended during Manual Dial calls
        _callStatusPollTimer: null,

        // WRAPUP state — post-call wrap-up monitoring
        _inWrapup: false,
        _wrapupPollTimer: null,
        _stopWrapupPoll: null,
        _endWrapup: null,
        _startWrapupMonitor: null,
        _readyTimeout: null,

        // Outbound call ACD session SID (permanent backup, never cleared after set)
        _fixedSessionSid: null,

        // Lifecycle flags
        _loggedIn: false,
        _loginInProgress: false,
        _callActive: false,
        _isIncoming: false,           // true when the active call is an inbound SIP INVITE
        _reconnecting: false,

        // Incoming call state
        _incomingSession: null,       // pending Invitation (not yet accepted/rejected)
        _acceptedInvitation: null,    // Invitation that was accepted (used to BYE on hangup)
        _incomingTimeout: null,       // auto-reject timer handle

        // Real-time agent status — updated on every keepAlive/wrapup poll response.
        // Never set manually; always reflects what TCN returns.
        _currentStatus: 'UNKNOWN',

        // Reconnect throttle — prevents infinite reconnect loops.
        // Reset to 0 on every successful login.
        _reconnectAttempts: 0,
        MAX_RECONNECT_ATTEMPTS: 3,
        RECONNECT_BASE_DELAY_MS: 5000,   // 5 s, 10 s, 15 s backoff

        // Timestamp (ms) when the presence SIP session reached Established.
        // Used to detect immediate Terminated (< 10 s) which indicates a
        // credential / rate-limit rejection rather than a genuine drop.
        _sipEstablishedAt: 0,

        CACHE_KEY: 'tcn_softphone_bootstrap_v1',
        CACHE_TTL_MS: 55 * 60 * 1000,
    };

    // SIP user-part labels that mean "no real caller ID" — used to decide when ANI resolution should overwrite
    var _BLANK_LABELS = ['incoming', 'unknown', 'anonymous', 'private'];

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────
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
     * Generic proxy POST — always includes Bearer token when available.
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
            var e = new Error('[TCN] ' + path + ' failed (' + res.status + '): ' + JSON.stringify(json));
            e.status = res.status;
            e.body = json;
            e.userMessage = (json && (json.error || json.message)) || null;
            throw e;
        }
        return json;
    }

    function readCache() {
        try {
            // localStorage persists across page navigations and iframe recreations,
            // unlike sessionStorage which is wiped whenever the iframe is destroyed.
            var raw = localStorage.getItem(TCN.CACHE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (_) {
            return null;
        }
    }

    function writeCache() {
        try {
            // dialUrl is single-use per TCN API — never cache it.
            // A new ASM session must be created each login to get a fresh dialUrl.
            localStorage.setItem(TCN.CACHE_KEY, JSON.stringify({
                savedAt: Date.now(),
                accessToken: TCN._accessToken,
                agentSid: TCN._agentSid,
                huntGroupSid: TCN._huntGroupSid,
                skills: TCN._skills,
                asmSessionSid: TCN._asmSessionSid,
                voiceSessionSid: TCN._voiceSessionSid,
                sipUser: TCN._sipUser,
                sipPass: TCN._sipPass,
            }));
        } catch (_) { }
    }

    function clearCache() {
        try {
            localStorage.removeItem(TCN.CACHE_KEY);
        } catch (_) { }
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

        // dialUrl is intentionally not cached (single-use) — not required here.
        return !!(TCN._accessToken && TCN._voiceSessionSid && TCN._sipUser && TCN._sipPass);
    }

    async function canResumeCachedSession() {
        var bootstrap = readCache();
        if (!bootstrap || !bootstrap.savedAt || (Date.now() - bootstrap.savedAt) > TCN.CACHE_TTL_MS) {
            clearCache();
            return false;
        }

        if (!restoreCache(bootstrap)) {
            clearCache();
            return false;
        }

        try {
            var keepAlive = await proxy('/tcn/keepalive', {
                sessionSid: String(TCN._voiceSessionSid || TCN._asmSessionSid || ''),
            });
            var status = String((keepAlive && keepAlive.statusDesc) || '').toUpperCase();
            var currentSessionId = String((keepAlive && (keepAlive.currentSessionId || keepAlive.sessionId || 0)) || 0);
            var keepAliveOk = !!(keepAlive && keepAlive.keepAliveSucceeded !== false);

            if (!keepAliveOk || currentSessionId === '0' || status === 'DISCONNECTED' || status === 'LOGGED_OUT' || status === 'WRAPUP') {
                clearCache();
                return false;
            }

            log('Reusing cached bootstrap', {
                voiceSid: TCN._voiceSessionSid,
                currentSessionId: currentSessionId,
                statusDesc: status,
            });
            return true;
        } catch (_) {
            clearCache();
            return false;
        }
    }

    // Returns 'manager' or 'telecaller' based on the authenticated user's role.
    // Reads from parent page meta tag (same-origin iframe access).
    function agentRole() {
        try {
            var meta = (window.parent || window).document.querySelector('meta[name="user-role"]');
            return meta && meta.getAttribute('content') === 'manager' ? 'manager' : 'telecaller';
        } catch (_) { return 'telecaller'; }
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

    // Calls /tcn/caller-info (TCN getclientinfodata) to resolve the real caller phone
    // from the integer callSid. Updates TCN._activePhone, the DB call log, and fires
    // tcn:phoneResolved so the widget can update the incoming number display in real-time.
    // Non-fatal — silently swallows errors.
    async function resolveCallerFromCallSid(callSid, logId) {
        if (!callSid) return;
        // SIP Call-IDs are UUID strings (e.g. "4c8e6f3c-abc@domain") — NOT the TCN integer callSid.
        // TCN's getclientinfodata requires an integer. Passing a UUID causes a (int) cast → 0 → no data.
        if (!/^\d+$/.test(String(callSid).trim())) {
            log('resolveCallerFromCallSid: skipping SIP Call-ID (not an integer TCN callSid): ' + callSid);
            return;
        }
        try {
            var payload = { callSid: String(callSid) };
            if (logId) payload.call_log_id = logId;
            var data = await proxy('/tcn/caller-info', payload);
            // Log full response so we can see what TCN returns during debugging
            console.log('[TCN] caller-info response', { callSid: callSid, logId: logId, response: data });
            if (data && data.ok) {
                if (data.call_sid && !TCN._activeCallSid) {
                    TCN._activeCallSid = String(data.call_sid);
                }
                if (data.phone) {
                    var phone = String(data.phone);
                    TCN._activePhone = phone;
                    fire('tcn:phoneResolved', { phone: phone, name: data.name || null });
                    log('Caller resolved', { phone: phone, callSid: callSid });
                } else {
                    log('caller-info returned no phone (check Laravel log for TCN response body)', { callSid: callSid });
                }
            }
        } catch (e) {
            log('resolveCallerFromCallSid failed (non-fatal)', e.message);
        }
    }

    // Resolves the caller's real phone number from TCN's getclientinfodata API.
    // Safe to call during ringing — retries handle the initial 404 while TCN builds the record.
    // Updates UI (tcn:phoneResolved) and DB (customer_number) for ALL call states
    // (accepted, missed, rejected) using logId OR TCN._activeLogId (whichever is available).
    // A singleton guard (_callerResolutionPending) prevents concurrent duplicate resolutions.
    async function resolveCallerWithRetry(callSid, logId, retries) {
        if (!callSid || !/^\d+$/.test(String(callSid).trim())) return;
        if (TCN._callerResolutionPending) {
            log('resolveCallerWithRetry: already in progress — skipping duplicate');
            return;
        }
        TCN._callerResolutionPending = true;
        retries = retries || 5;
        try {
            for (var _i = 0; _i < retries; _i++) {
                try {
                    var _payload = { callSid: String(callSid) };
                    // Always use the most up-to-date logId — the log may be created after this
                    // function is first called (race between async createInboundCallLog and poll).
                    var _effectiveLogId = logId || TCN._activeLogId;
                    if (_effectiveLogId) _payload.call_log_id = _effectiveLogId;
                    var _data = await proxy('/tcn/caller-info', _payload);
                    console.log('[TCN] caller-info response (attempt ' + (_i + 1) + '/' + retries + ')',
                        { callSid: callSid, logId: _effectiveLogId, response: _data });
                    if (_data && _data.ok) {
                        if (_data.call_sid && !TCN._activeCallSid) {
                            TCN._activeCallSid = String(_data.call_sid);
                        }
                        if (_data.phone) {
                            var _resolvedPhone = String(_data.phone);
                            TCN._activePhone = _resolvedPhone;
                            fire('tcn:phoneResolved', { phone: _resolvedPhone, name: _data.name || null });
                            console.log('[TCN] Resolved number:', _resolvedPhone);
                            log('Caller resolved (attempt ' + (_i + 1) + ')', { phone: _resolvedPhone, callSid: callSid });
                            // Re-read logId — it may have been set by createInboundCallLog between retries
                            var _patchLogId = _effectiveLogId || TCN._activeLogId;
                            if (_patchLogId) {
                                patchCallLog(_patchLogId, { customer_number: _resolvedPhone });
                            }
                            return;
                        }
                    }
                } catch (_e) {
                    log('resolveCallerWithRetry attempt ' + (_i + 1) + ' failed (non-fatal, will retry)', _e.message);
                }
                if (_i < retries - 1) {
                    await new Promise(function (r) { setTimeout(r, 2000); });
                }
            }
            log('resolveCallerWithRetry: all ' + retries + ' attempts exhausted for callSid=' + callSid);
        } finally {
            TCN._callerResolutionPending = false;
        }
    }

    // Creates a DB call log for an inbound call and stores the ID in TCN._activeLogId.
    // call_sid is intentionally NOT stored here — it is only available AFTER approve-call.
    // Storing a sessionSid as call_sid at ring time causes getclientinfodata 404s.
    // Non-fatal — silently ignores network/server errors.
    async function createInboundCallLog(phone) {
        try {
            var payload = { direction: 'inbound', status: 'ringing' };
            if (phone && !_BLANK_LABELS.includes(String(phone).toLowerCase())) {
                payload.phone = phone;
            }
            var res = await fetch('/tcn/call-log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(payload),
            });
            if (res.ok) {
                var data = await res.json();
                TCN._activeLogId = data.call_log_id || null;
                log('Inbound call log created (ringing)', { callLogId: TCN._activeLogId, phone: phone });

                // Apply any disposition (reject/missed) that arrived before the log was ready
                if (TCN._pendingIncomingDisposition && TCN._activeLogId) {
                    patchCallLog(TCN._activeLogId, TCN._pendingIncomingDisposition);
                    TCN._pendingIncomingDisposition = null;
                    TCN._activeLogId = null;
                }
            } else {
                var errBody = '';
                try { errBody = await res.text(); } catch (_) {}
                log('createInboundCallLog: server returned ' + res.status + ' — ' + errBody + ' (non-fatal)');
            }
        } catch (e) {
            log('createInboundCallLog failed (non-fatal)', e.message);
        }
    }

    // Creates a call log for an inbound call that was already accepted (fallback
    // when createInboundCallLog failed on INVITE arrival — e.g. DB not yet ready).
    async function createInboundCallLogFallback(phone, answeredAt, callSid) {
        try {
            var payload = { direction: 'inbound' };
            if (phone) payload.phone = phone;
            if (callSid) payload.call_sid = callSid;
            var res = await fetch('/tcn/call-log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(payload),
            });
            if (res.ok) {
                var data = await res.json();
                var logId = data.call_log_id || null;
                if (logId) {
                    TCN._activeLogId = logId;
                    // Immediately mark as answered since the call is already connected
                    patchCallLog(logId, {
                        status: 'answered',
                        answered_at: answeredAt || new Date().toISOString(),
                    });
                    log('Inbound call log created (fallback)', { callLogId: logId, phone: phone });
                }
            } else {
                var errBody = '';
                try { errBody = await res.text(); } catch (_) {}
                log('createInboundCallLogFallback: server returned ' + res.status + ' — ' + errBody);
            }
        } catch (e) {
            log('createInboundCallLogFallback failed', e.message);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // SIP.js loader (lazy — only loads the script once)
    // ─────────────────────────────────────────────────────────────
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

    // ─────────────────────────────────────────────────────────────
    // Remote audio — attach the inbound WebRTC track to <audio>
    //
    // CRITICAL: Without this the agent hears nothing even if the SIP
    // session reaches Established — the MediaStream exists but is not
    // rendered by any audio element.
    // ─────────────────────────────────────────────────────────────
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

            // Handle future tracks — TCN bridges PSTN audio dynamically via SDP re-negotiation.
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
                if (p) p.catch(function () { });
            });

            var p = audio.play();
            if (p) p.catch(function (e) { log('audio.play() blocked (needs user gesture)', e.message); });
            log('Remote audio attached → #' + elementId);
        } catch (e) {
            log('attachRemoteAudio error (non-fatal)', e.message);
        }
    };

    // ─────────────────────────────────────────────────────────────
    // SIP cleanup — tear down UA and all sessions cleanly.
    // Must be called before every reconnect/logout to avoid dangling
    // WebSocket listeners from the old UA.
    // ─────────────────────────────────────────────────────────────
    TCN._cleanupSip = function () {
        TCN._stopCallKeepAlive();
        TCN._stopCallStatusPoll();
        TCN._stopIncomingPoll();
        TCN._stopSipAniPoll();
        TCN._stopIdlePoll();
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
        // Clean up any pending or accepted incoming call
        if (TCN._incomingTimeout) {
            clearTimeout(TCN._incomingTimeout);
            TCN._incomingTimeout = null;
        }
        if (TCN._incomingSession) {
            try { TCN._incomingSession.reject(); } catch (_) { }
            TCN._incomingSession = null;
        }
        if (TCN._acceptedInvitation) {
            try { TCN._acceptedInvitation.bye(); } catch (_) { }
            TCN._acceptedInvitation = null;
        }
        TCN._isIncoming = false;

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
        log('SIP cleaned up');
    };

    // ─────────────────────────────────────────────────────────────
    // Login Flow — 4 REST steps + SIP + keepalive
    // ─────────────────────────────────────────────────────────────

    TCN.login = async function () {
        if (TCN._loggedIn) { log('Already logged in'); return; }

        // Singleton guard — prevent duplicate login calls racing on same page.
        if (TCN._loginInProgress) { log('Login already in progress, skipping.'); return; }
        TCN._loginInProgress = true;

        // Tear down any stale SIP state from a previous session or
        // failed reconnect attempt before starting fresh.
        TCN._cleanupSip();
        TCN._asmSessionSid = null;
        TCN._voiceSessionSid = null;

        try {
            // Step 1 — Fetch per-user config: exchanges stored refresh_token for a
            // short-lived access_token server-side. Also returns agent_id + hunt_group_id
            // so the separate /tcn/agent call is no longer needed.
            // client_secret and refresh_token NEVER reach the browser.
            log('Step 1: Fetching per-user TCN config…');
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

            // Step 2 — Agent skills (uses access_token set above)
            log('Step 2: Getting agent skills…');
            var skillsData = await proxy('/tcn/skills', {
                huntGroupSid: parseInt(TCN._huntGroupSid),
                agentSid: parseInt(TCN._agentSid),
            });
            TCN._skills = skillsData.skills || {};
            log('Skills loaded', TCN._skills);

            // Step 3 — Create ASM session (SIP credentials)
            log('Step 3: Creating ASM session (SIP credentials)…');
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

            // Step 4 — Load SIP.js and establish presence SIP session.
            // Keep-alive must NOT start before SIP is Established — TCN returns
            // keepAliveSucceeded=false / UNAVAILABLE until the SIP INVITE is answered.
            log('Loading SIP.js…');
            await loadSipJs();

            var SIP = (window.SIP && window.SIP.SIP) ? window.SIP.SIP : window.SIP;
            if (!SIP || !SIP.UserAgent) {
                throw new Error('SIP.js not loaded — /js/sip.js must export window.SIP');
            }

            await callDialUrl(SIP);

            // Step 5 — Start keep-alive only after SIP Established (agent is READY).
            TCN._startKeepAlive();

            TCN._loggedIn = true;
            TCN._loginInProgress = false;
            TCN._reconnectAttempts = 0;
            log('Login complete — agent is READY');
            fire('tcn:ready');
            TCN._startIdlePoll();

        } catch (e) {
            TCN._loginInProgress = false;
            log('Login failed', e.message);
            fire('tcn:error', { message: e.message });
            throw e;
        }
    };

    // ─────────────────────────────────────────────────────────────
    // SIP Presence Session
    //
    // TCN Operator API doc: "Use SIP.js to call in to the dial Url
    // returned in the create session response."
    //
    // This SIP INVITE (NOT REGISTER) establishes the agent's audio
    // channel on TCN and puts them in READY state.
    // Audio MUST be attached on Established — TCN bridges inbound or
    // bridged calls to this session's audio track.
    // ─────────────────────────────────────────────────────────────
    function callDialUrl(SIP) {
        return new Promise(function (resolve, reject) {
            var wsUri = 'wss://sg-webphone.tcnp3.com';
            var settled = false;

            var timer = setTimeout(function () {
                if (settled) return;
                settled = true;
                log('SIP presence INVITE timed out (20 s)');
                reject(new Error('SIP timed out — dial_url may be expired or credentials wrong'));
            }, 20000);

            TCN._ua = new SIP.UserAgent({
                uri: SIP.UserAgent.makeURI('sip:' + TCN._sipUser + '@sg-webphone.tcnp3.com'),
                transportConstructor: SIP.Web.Transport,
                transportOptions: {
                    server: wsUri,
                    // Send a SIP OPTIONS keep-alive every 20 s so NAT/firewall
                    // mappings stay alive and the WSS connection isn't silently
                    // dropped by intermediate proxies after 30–60 s of idle.
                    keepAliveInterval: 20,
                },
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
                // Handle inbound SIP INVITEs — incoming ACD calls routed by TCN.
                delegate: {
                    onInvite: function (invitation) {

                        console.log('[TCN] Incoming call detected — callSid NOT available at this stage (only after approve-call)', invitation);

                        // 🚫 If already on call → reject immediately
                        if (TCN._callActive || TCN._isIncoming) {
                            console.log('[TCN] Busy — rejecting incoming call');
                            try { invitation.reject(); } catch (e) { }
                            return;
                        }

                        // ✅ Store incoming session
                        TCN._incomingSession = invitation;
                        TCN._isIncoming = true;
                        TCN._activeLogId = null;

                        // ✅ Extract phone — treat generic SIP labels as null so ANI resolution can fill them
                        let rawSipUser = null;
                        try {
                            rawSipUser = invitation.remoteIdentity.uri.user || null;
                        } catch (e) { }
                        let phone = (rawSipUser && !_BLANK_LABELS.includes(rawSipUser.toLowerCase())) ? rawSipUser : null;

                        // ── Try to extract ANI from standard SIP headers ──────────────
                        // TCN may carry the real caller number in P-Asserted-Identity or
                        // Remote-Party-ID (both are common in telco SIP deployments).
                        // If found, it overrides the generic "incoming" SIP From user.
                        if (!phone) {
                            try {
                                var _hdrs = invitation.request.headers || {};
                                var _paiHdr = _hdrs['P-Asserted-Identity'] || _hdrs['Remote-Party-ID'] ||
                                              _hdrs['X-ANI'] || _hdrs['P-Preferred-Identity'];
                                if (_paiHdr && _paiHdr[0]) {
                                    var _paiRaw = typeof _paiHdr[0] === 'object'
                                        ? (_paiHdr[0].raw || String(_paiHdr[0]))
                                        : String(_paiHdr[0]);
                                    // Match sip:DIGITS@… or tel:DIGITS
                                    var _paiMatch = _paiRaw.match(/(?:sip:|tel:)(\+?[\d]+)[@;>]/);
                                    if (_paiMatch) {
                                        var _paiNum = _paiMatch[1].replace(/^\+91/, '').replace(/\D/g, '');
                                        if (_paiNum.length >= 6) phone = _paiNum;
                                    }
                                }
                            } catch (_) {}
                        }

                        // ── Try to extract real TCN integer callSid from custom headers ─
                        // TCN may inject callSid in X-Cid or similar custom headers.
                        // Do NOT use invitation.request.callId — that is the SIP Call-ID
                        // (a UUID), not the TCN integer callSid needed by getclientinfodata.
                        let tcnIntegerCallSid = null;
                        try {
                            var _hdrs2 = invitation.request.headers || {};
                            var _cidKeys = ['X-Cid', 'X-Call-Sid', 'X-TCN-Call-Sid', 'X-Tcn-Sid', 'X-Callsid', 'X-Call-Id'];
                            for (var _hi = 0; _hi < _cidKeys.length; _hi++) {
                                var _hv = _hdrs2[_cidKeys[_hi]];
                                if (_hv && _hv[0]) {
                                    var _hvRaw = typeof _hv[0] === 'object' ? (_hv[0].raw || '') : String(_hv[0]);
                                    var _hvDigits = _hvRaw.trim().replace(/\D/g, '');
                                    if (_hvDigits.length > 3) { tcnIntegerCallSid = _hvDigits; break; }
                                }
                            }
                        } catch (_) {}

                        TCN._activePhone = phone;
                        // callSid is NOT stored at ring time — approve-call returns the real callSid.
                        // Even if X-Cid headers carry an integer, we wait for approve-call to confirm it.
                        TCN._activeCallSid = null;

                        console.log('[TCN] Incoming phone:', phone, 'sipCallId:',
                                    (function(){ try{ return invitation.request.callId; }catch(_){return null;} })());

                        // Create DB log with status=ringing and no call_sid (call_sid is set after approve-call).
                        createInboundCallLog(phone);

                        // ✅ Start lightweight ANI-resolution poll (SIP INVITE mode).
                        // TCN's agentgetstatus returns the real ANI + integer callSid when
                        // the agent status transitions to INCALL. This poll captures that data
                        // and updates the UI + DB without waiting for the full keepalive cycle.
                        var _needsAni = !phone || _BLANK_LABELS.includes(String(phone || '').toLowerCase());
                        if (_needsAni) {
                            TCN._startSipAniPoll();
                        }

                        // ✅ FIRE EVENT TO UI
                        window.dispatchEvent(new CustomEvent('tcn:incomingCall', {
                            detail: { phone: phone }
                        }));

                        console.log('[TCN] Incoming event fired → UI should show now');

                        // ✅ AUTO REJECT AFTER 15s
                        TCN._incomingTimeout = setTimeout(function () {
                            if (TCN._incomingSession) {
                                console.log('[TCN] Auto rejecting (timeout)');

                                try { TCN._incomingSession.reject(); } catch (e) { }

                                TCN._incomingSession = null;
                                TCN._isIncoming = false;

                                var missedPatch = { status: 'missed', ended_at: new Date().toISOString(), ended_by: 'system' };
                                if (TCN._activeLogId) {
                                    patchCallLog(TCN._activeLogId, missedPatch);
                                    TCN._activeLogId = null;
                                } else {
                                    TCN._pendingIncomingDisposition = missedPatch;
                                }

                                window.dispatchEvent(new CustomEvent('tcn:incomingCallRejected'));
                            }
                        }, 15000);
                    }
                }
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
                        TCN._sipEstablishedAt = Date.now();
                        // Attach to tcn-remote-audio so TCN-bridged Manual Dial audio reaches the UI.
                        TCN._attachRemoteAudio(inviter, 'tcn-remote-audio');
                        log('Presence SIP Established — agent READY');
                        resolve();

                    } else if (state === 'Terminated' && !settled) {
                        // Failed to establish before resolving the Promise
                        settled = true;
                        clearTimeout(timer);
                        reject(new Error('Presence SIP terminated before Established — check credentials/dial_url'));

                    } else if (state === 'Terminated' && settled) {
                        // ── Dropped after successful login ──────────────────────────
                        TCN._registered = false;
                        TCN._sipSession = null;
                        log('Presence SIP dropped (was up for ' +
                            Math.round((Date.now() - TCN._sipEstablishedAt) / 1000) + ' s)');

                        // Stop presence keep-alive — the session SID is invalid now.
                        TCN._stopKeepAlive();
                        fire('tcn:sipDropped');

                        // Do NOT reconnect if a call is active or a reconnect is already
                        // in-flight. Audio for the active call flows through the presence
                        // SIP session so the call will finish naturally; a forced
                        // reconnect here would tear down the audio mid-call.
                        if (TCN._callActive) {
                            log('SIP dropped during active call — skipping reconnect until call ends');
                            return;
                        }

                        if (TCN._reconnecting) {
                            log('Reconnect already in-flight — skipping duplicate');
                            return;
                        }

                        // ── Retry limit — prevents the infinite loop ────────────────
                        if (TCN._reconnectAttempts >= TCN.MAX_RECONNECT_ATTEMPTS) {
                            log('Max SIP reconnect attempts (' + TCN.MAX_RECONNECT_ATTEMPTS +
                                ') reached — giving up. Refresh the page to reconnect.');
                            TCN._reconnectAttempts = 0;
                            TCN._loggedIn = false;
                            fire('tcn:error', {
                                message: 'SIP connection lost after ' + TCN.MAX_RECONNECT_ATTEMPTS +
                                    ' attempts. Please refresh the page.'
                            });
                            return;
                        }

                        TCN._reconnectAttempts++;
                        TCN._reconnecting = true;
                        TCN._loggedIn = false;

                        // Clean up the dead UA so login() gets a clean slate
                        if (TCN._ua) {
                            try { TCN._ua.stop(); } catch (_) { }
                            TCN._ua = null;
                        }

                        var attempt = TCN._reconnectAttempts;
                        var delay = TCN.RECONNECT_BASE_DELAY_MS * attempt; // 5 s, 10 s, 15 s
                        log('Auto-reconnecting (attempt ' + attempt + '/' +
                            TCN.MAX_RECONNECT_ATTEMPTS + ') in ' + (delay / 1000) + ' s…');

                        setTimeout(function () {
                            // Check again — a logout() could have fired during the delay
                            if (!TCN._reconnecting) {
                                log('Reconnect cancelled (logged out during delay)');
                                return;
                            }
                            TCN._reconnecting = false;
                            TCN.login().then(function () {
                                TCN._reconnectAttempts = 0;
                                log('Auto-reconnect successful');
                            }).catch(function (e) {
                                log('Auto-reconnect attempt ' + attempt + ' failed', e.message);
                                fire('tcn:error', { message: 'Reconnect failed: ' + e.message });
                            });
                        }, delay);
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

    // ─────────────────────────────────────────────────────────────
    // Keep-alive
    //
    // Fires IMMEDIATELY after login (critical — do NOT wait 30 s),
    // then every 30 s.
    //
    // Uses voiceSessionSid (preferred) or asmSessionSid as fallback.
    // A null sessionSid would send "null" to TCN — validated here.
    //
    // After 3 consecutive failures the session is assumed expired;
    // SIP cleanup + re-login is triggered automatically.
    // ─────────────────────────────────────────────────────────────
    TCN._doKeepAlive = async function () {

        var sid = TCN._voiceSessionSid || TCN._asmSessionSid;

        if (!sid) {
            log('Keep-alive skipped — no valid sessionSid');
            return;
        }

        sid = String(sid);

        try {

            var data = await proxy('/tcn/keepalive', { sessionSid: sid });

            var status = ((data && data.statusDesc) || '').toUpperCase();
            var kaOk = !!(data && data.keepAliveSucceeded);

            // Always sync real-time status and notify UI
            TCN._currentStatus = status;
            fire('tcn:statusUpdate', { status: status });

            // ✅ Initialize counter if not exists
            TCN._keepAliveFailCount = TCN._keepAliveFailCount || 0;

            log('Keep-alive response', {
                sessionSid: sid,
                keepAliveSucceeded: kaOk,
                statusDesc: status || '?',
                raw: data,
            });

            // ✅ SUCCESS CASE
            if (kaOk) {
                TCN._keepAliveFailCount = 0; // reset on success

                // PBX_POPUP_LOCKED = TCN has routed an inbound call to this agent
                // and is waiting for manual acceptance (PBX / manual-answer mode).
                // SIP INVITE does NOT fire in this mode — detect via keepalive status.
                if (status === 'PBX_POPUP_LOCKED' && !TCN._isIncoming && !TCN._callActive) {
                    log('Incoming call detected via PBX_POPUP_LOCKED');
                    TCN._isIncoming = true;
                    TCN._activeLogId = null;
                    TCN._callerResolutionPending = false; // reset for fresh call
                    // Extract caller ANI from TCN response — try all known field names
                    var callerPhone = (data && (data.ani || data.callerAni || data.callerPhone || data.callerNumber || data.fromNumber || data.from || data.cid)) || null;
                    // callSid is NOT available at ring time — only approve-call returns the real callSid.
                    // Store null here; acceptIncomingCall() will set TCN._activeCallSid after approve-call.
                    TCN._activePhone = callerPhone;
                    TCN._activeCallSid = null;
                    createInboundCallLog(callerPhone);
                    fire('tcn:incomingCall', { phone: callerPhone });
                    // Start fast 2s poll so we detect INCALL (accepted) or READY
                    // (rejected/timed-out) without waiting 30s for the next keep-alive.
                    TCN._startIncomingPoll();
                }

                // WRAPUP with no active call means the agent is stuck post-call.
                if (status === 'WRAPUP' && !TCN._callActive) {
                    log('Keep-alive: WRAPUP with no active call → restarting wrapup monitor');
                    TCN._startWrapupMonitor();
                }
                return;
            }

            // 🚨 IMPORTANT FIX: Ignore temporary UNAVAILABLE during active call
            if (status === 'UNAVAILABLE' && TCN._callActive) {
                log('Transient UNAVAILABLE during active call → ignoring');
                return;
            }

            // WRAPUP is a normal post-call state — not a session failure.
            // Counting it as a keep-alive failure would trigger spurious re-login.
            if (status === 'WRAPUP') {
                log('Keep-alive: agent in WRAPUP (post-call) — ignoring as transient');
                return;
            }

            // ⚠️ Count failure only for real issues
            TCN._keepAliveFailCount++;

            log('Keep-alive warning (attempt ' + TCN._keepAliveFailCount + '/3)', data);

            // ❗ Only act after multiple failures
            if (TCN._keepAliveFailCount >= 3) {

                log('Keep-alive failed multiple times — checking session status');

                // Only trigger re-login for real disconnection states
                if (status === 'DISCONNECTED' || status === 'LOGGED_OUT') {
                    log('Session expired (' + status + ') → triggering re-login');
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
                log('Keep-alive failed 3 times — triggering re-login');
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
            log('Re-initializing after session expiry…');
            TCN.login().catch(function (e) {
                log('Re-login failed', e.message);
                fire('tcn:error', { message: 'Session expired and re-login failed: ' + e.message });
            });
        }, 2000);
    };

    // ─────────────────────────────────────────────────────────────
    // Incoming call poll
    //
    // Started when PBX_POPUP_LOCKED is detected in keep-alive.
    // Polls agentgetstatus every 2s to detect:
    //   INCALL / TALKING  → agent accepted (or TCN auto-bridged after approve-call)
    //   READY             → call rejected / timed out / cancelled by caller
    // Stops automatically on either transition.
    // ─────────────────────────────────────────────────────────────
    TCN._incomingPollTimer = null;

    TCN._startIncomingPoll = function () {
        TCN._stopIncomingPoll();
        log('Incoming poll started (2s interval)');
        var MAX_POLLS = 60; // 2 min safety ceiling
        var pollCount = 0;

        TCN._incomingPollTimer = setInterval(async function () {
            pollCount++;
            if (pollCount > MAX_POLLS) {
                log('Incoming poll: max wait exceeded — clearing incoming state');
                TCN._stopIncomingPoll();
                TCN._isIncoming = false;
                TCN._activePhone = null;
                fire('tcn:incomingCallRejected');
                return;
            }

            var sid = TCN._voiceSessionSid || TCN._asmSessionSid;
            if (!sid) return;

            try {
                var data = await proxy('/tcn/status', { sessionSid: String(sid) });
                var status = ((data && data.statusDesc) || '').toUpperCase();
                var currentSid = String(data && data.currentSessionId || '0');

                // Keep session SID updated
                if (currentSid && currentSid !== '0' && TCN._voiceSessionSid !== currentSid) {
                    TCN._voiceSessionSid = currentSid;
                    TCN._fixedSessionSid = currentSid;
                }

                log('Incoming poll status: ' + status);

                // Try to get ANI from status poll data (available when INCALL/TALKING)
                var pollAni = data && (data.ani || data.callerAni || data.callerPhone || data.callerNumber || data.fromNumber || data.from || data.cid);
                var _noPhone = !TCN._activePhone || _BLANK_LABELS.includes(String(TCN._activePhone).toLowerCase());
                if (pollAni && _noPhone) {
                    TCN._activePhone = String(pollAni);
                    fire('tcn:phoneResolved', { phone: String(pollAni) });
                    if (TCN._activeLogId) {
                        patchCallLog(TCN._activeLogId, { customer_number: String(pollAni) });
                    }
                }
                // Capture TCN's real callSid from poll data if not yet set.
                // Do NOT fall back to currentSessionId — that is a sessionSid, not a callSid.
                // Do NOT call resolveCallerWithRetry here — this runs BEFORE approve-call.
                // callSid from approve-call is the authoritative value; resolution happens in acceptIncomingCall().
                var pollCallSid = data && (data.callSid || data.callId);
                if (pollCallSid && !TCN._activeCallSid) {
                    TCN._activeCallSid = String(pollCallSid);
                    if (TCN._activeLogId) {
                        patchCallLog(TCN._activeLogId, { call_sid: String(pollCallSid) });
                    }
                }

                if (status === 'INCALL' || status === 'TALKING' || status === 'PEERED') {
                    // Fallback: also resolve at INCALL if retry during ringing didn't complete
                    var _noPhoneAtIncall = !TCN._activePhone || _BLANK_LABELS.includes(String(TCN._activePhone || '').toLowerCase());
                    if (TCN._activeCallSid && _noPhoneAtIncall) {
                        resolveCallerWithRetry(TCN._activeCallSid, TCN._activeLogId);
                    }
                    TCN._stopIncomingPoll();
                    TCN._callActive = true;
                    TCN._callEstablishedAt = Date.now();
                    // createInboundCallLog is fire-and-forget — it may not have resolved yet.
                    // Always ensure a log exists before firing callAnswered so the parent
                    // gets a non-null callLogId and can call /call/end when the call ends.
                    if (!TCN._activeLogId) {
                        await createInboundCallLogFallback(
                            TCN._activePhone || null,
                            new Date(TCN._callEstablishedAt).toISOString(),
                            TCN._activeCallSid
                        );
                    } else {
                        patchCallLog(TCN._activeLogId, {
                            status: 'answered',
                            answered_at: new Date(TCN._callEstablishedAt).toISOString(),
                        });
                    }
                    log('Incoming call INCALL confirmed — call active');
                    fire('tcn:callAnswered', { phone: TCN._activePhone, callLogId: TCN._activeLogId });
                    TCN._startCallStatusPoll();

                } else if (status === 'READY' || status === 'AVAILABLE' || status === 'WRAPUP') {
                    // Caller hung up / rejected / timed out before agent accepted
                    TCN._stopIncomingPoll();
                    if (TCN._activeLogId) {
                        patchCallLog(TCN._activeLogId, { status: 'missed', ended_at: new Date().toISOString(), ended_by: 'lead' });
                        TCN._activeLogId = null;
                    } else {
                        // createInboundCallLog may still be in flight — record missed when it resolves
                        TCN._pendingIncomingDisposition = { status: 'missed', ended_at: new Date().toISOString(), ended_by: 'lead' };
                    }
                    TCN._isIncoming = false;
                    TCN._activePhone = null;
                    TCN._activeCallSid = null;
                    log('Incoming call cleared — status back to ' + status);
                    fire('tcn:incomingCallRejected');

                    if (status === 'WRAPUP') {
                        TCN._startWrapupMonitor();
                    }
                }
                // PBX_POPUP_LOCKED → still ringing, keep polling

            } catch (e) {
                log('Incoming poll error (non-fatal)', e.message);
            }
        }, 2000);
    };

    TCN._stopIncomingPoll = function () {
        if (TCN._incomingPollTimer) {
            clearInterval(TCN._incomingPollTimer);
            TCN._incomingPollTimer = null;
        }
    };

    // ─────────────────────────────────────────────────────────────
    // SIP INVITE ANI-resolution poll
    //
    // Problem: TCN sends SIP INVITE with From: <sip:incoming@…> — the SIP
    // user part ("incoming") is a generic label, not the real caller number.
    // The SIP Call-ID (invitation.request.callId) is a UUID, not the TCN
    // integer callSid needed by getclientinfodata.
    //
    // Solution: Poll agentgetstatus with the voice session SID every 2 s.
    // When the agent transitions to INCALL the response may contain:
    //   - ani / callerAni / callerPhone  → real caller number
    //   - callSid / callId               → real TCN callSid (for getclientinfodata)
    // NOTE: currentSessionId is a sessionSid, NOT the callSid — do not use it for getclientinfodata.
    // Both are used to update the UI (tcn:phoneResolved) and DB call log.
    //
    // This poll is ONLY for ANI resolution — it does NOT drive state
    // transitions (that is _startIncomingPoll's job for PBX mode).
    // It stops itself as soon as ANI is found, the call is rejected, or
    // the max wait is exceeded.
    // ─────────────────────────────────────────────────────────────
    TCN._sipAniPollTimer = null;

    TCN._startSipAniPoll = function () {
        if (TCN._sipAniPollTimer) return; // already running
        var MAX_RETRIES = 20; // 40 s ceiling (call ring + answer window)
        var retries = 0;
        log('SIP ANI-resolution poll started (2 s interval, max ' + MAX_RETRIES + ' attempts)');

        TCN._sipAniPollTimer = setInterval(async function () {
            retries++;

            // Stop if call is no longer incoming/active, or max retries reached
            if (retries > MAX_RETRIES || (!TCN._isIncoming && !TCN._callActive)) {
                log('SIP ANI poll: stopping (retries=' + retries + ', isIncoming=' + TCN._isIncoming + ')');
                TCN._stopSipAniPoll();
                return;
            }

            var sid = TCN._voiceSessionSid || TCN._asmSessionSid;
            if (!sid) return;

            try {
                var data = await proxy('/tcn/status', { sessionSid: String(sid) });
                var status = ((data && data.statusDesc) || '').toUpperCase();

                // Extract real ANI from agentgetstatus response
                var ani = data && (
                    data.ani || data.callerAni || data.callerPhone ||
                    data.callerNumber || data.fromNumber || data.from || data.cid
                );

                // Extract real callSid — do NOT use currentSessionId (it is a sessionSid).
                var realCallSid = data && (data.callSid || data.callId);
                var realCallSidStr = realCallSid ? String(realCallSid) : null;
                var isIntegerSid = realCallSidStr && /^\d+$/.test(realCallSidStr) && realCallSidStr !== '0';

                log('SIP ANI poll #' + retries + ' status=' + status, {
                    ani: ani || null,
                    callSid: realCallSidStr || null,
                });

                // Update callSid if we got a real integer one and don't have one yet
                if (isIntegerSid && (!TCN._activeCallSid || !/^\d+$/.test(TCN._activeCallSid))) {
                    TCN._activeCallSid = realCallSidStr;
                    if (TCN._activeLogId) {
                        patchCallLog(TCN._activeLogId, { call_sid: realCallSidStr });
                    }
                    log('SIP ANI poll: captured real callSid=' + realCallSidStr);
                }

                var needsPhone = !TCN._activePhone ||
                    _BLANK_LABELS.includes(String(TCN._activePhone || '').toLowerCase());

                if (ani && needsPhone) {
                    // ANI found directly in agentgetstatus response
                    TCN._activePhone = String(ani);
                    fire('tcn:phoneResolved', { phone: String(ani), name: null });
                    if (TCN._activeLogId) {
                        patchCallLog(TCN._activeLogId, { customer_number: String(ani) });
                    }
                    log('SIP ANI poll: ANI resolved from status → ' + ani);
                    TCN._stopSipAniPoll();
                    return;
                }

                // Do NOT call resolveCallerWithRetry here — this poll runs BEFORE approve-call.
                // callSid captured above is stored in TCN._activeCallSid and will be used
                // by acceptIncomingCall() AFTER approve-call confirms the real callSid.

            } catch (e) {
                log('SIP ANI poll error (non-fatal)', e.message);
            }
        }, 2000);
    };

    TCN._stopSipAniPoll = function () {
        if (TCN._sipAniPollTimer) {
            clearInterval(TCN._sipAniPollTimer);
            TCN._sipAniPollTimer = null;
            log('SIP ANI-resolution poll stopped');
        }
    };

    // ─────────────────────────────────────────────────────────────
    // Idle poll — detects PBX_POPUP_LOCKED fast (every 3s) while
    // the agent is READY and not on a call.  Stops itself as soon
    // as an incoming call is detected (handing over to _startIncomingPoll)
    // or a call becomes active.
    // ─────────────────────────────────────────────────────────────
    TCN._idlePollTimer = null;

    TCN._startIdlePoll = function () {
        if (TCN._idlePollTimer) return;
        TCN._idlePollTimer = setInterval(async function () {
            if (TCN._callActive || TCN._isIncoming) {
                TCN._stopIdlePoll();
                return;
            }
            var sid = TCN._voiceSessionSid || TCN._asmSessionSid;
            if (!sid) return;
            try {
                var data = await proxy('/tcn/keepalive', { sessionSid: String(sid) });
                var status = ((data && data.statusDesc) || '').toUpperCase();
                if (status === 'PBX_POPUP_LOCKED' && !TCN._isIncoming && !TCN._callActive) {
                    TCN._stopIdlePoll();
                    log('Idle poll: PBX_POPUP_LOCKED detected');
                    TCN._isIncoming = true;
                    TCN._activeLogId = null;
                    TCN._callerResolutionPending = false; // reset for fresh call
                    var callerPhone = (data && (data.ani || data.callerAni || data.callerPhone || data.callerNumber || data.fromNumber || data.from || data.cid)) || null;
                    // callSid is NOT available at ring time — only approve-call returns the real callSid.
                    // Store null here; acceptIncomingCall() will set TCN._activeCallSid after approve-call.
                    TCN._activePhone = callerPhone;
                    TCN._activeCallSid = null;
                    createInboundCallLog(callerPhone);
                    fire('tcn:incomingCall', { phone: callerPhone });
                    TCN._startIncomingPoll();
                }
            } catch (_) {}
        }, 3000);
    };

    TCN._stopIdlePoll = function () {
        if (TCN._idlePollTimer) {
            clearInterval(TCN._idlePollTimer);
            TCN._idlePollTimer = null;
        }
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

    // ─────────────────────────────────────────────────────────────
    // Call-session keep-alive
    //
    // Each outbound call creates a FRESH ASM session with its own
    // voiceSessionSid. That session ALSO needs keep-alive pings —
    // the login-session keep-alive uses a different SID and does NOT
    // keep the call session alive. Without this, TCN expires the call
    // session and sends BYE immediately after 200 OK.
    //
    // Fires immediately on call setup, then every 25 s until the call
    // ends (Terminated state or endCall()).
    // ─────────────────────────────────────────────────────────────
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
                log('Call keep-alive WARNING: keepAliveSucceeded=false — call session may expire', data);
            }
        } catch (e) {
            log('Call keep-alive failed (non-fatal)', e.message);
        }
    };

    TCN._startCallKeepAlive = function (voiceSid) {

        // ✅ Prevent duplicate start
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

    // ─────────────────────────────────────────────────────────────
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
    //   passes but by the time `new SIP.Inviter(TCN._ua, …)` runs,
    //   _ua is null → "Cannot read properties of null (reading
    //   'getLogger')".
    //
    // Also used when the call button is pressed during the ~3-second
    // reconnect window after a presence-session drop.
    // ─────────────────────────────────────────────────────────────
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

    // ─────────────────────────────────────────────────────────────
    // Outbound Call — per-call ASM session + SIP dial_url
    //
    // Each call creates a FRESH ASM session that includes the
    // destination phoneNumber. TCN configures the PSTN leg and
    // returns a call-specific dial_url in voiceRegistration.
    // The agent then invites sip:{dial_url}@sg-webphone.tcnp3.com —
    // TCN's gateway bridges that SIP session to the PSTN customer.
    //
    // Why not dial phone number directly?
    //   Dialling sip:+91XXXXXXXXXX@sg-webphone.tcnp3.com fails
    //   instantly — TCN's gateway only accepts dial_url tokens, not
    //   raw E.164 numbers, on the agent WebRTC transport.
    //
    // Lifecycle:
    //   new ASM session → SIP INVITE(dial_url) → Established → Terminated
    // ─────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────
    // Outbound Call — Manual Dial Operator API flow
    //
    // Architecture (v6.0 — ACD-registered Manual Dial edition):
    //   1. Get currentSessionId from agentgetstatus (ACD voice session SID)
    //   2. POST /tcn/dial → runs dialmanualprepare + processmanualdialcall
    //      + manualdialstart server-side. TCN registers the call in ACD.
    //   3. Agent transitions READY → INCALL in TCN's state machine.
    //   4. Audio flows through the EXISTING presence SIP session (no new SIP UA).
    //   5. Status poll every 5s: detects INCALL (answered) and READY (call ended).
    //
    // Why Manual Dial instead of SIP direct-dial:
    //   Direct SIP INVITE bypasses TCN's ACD. The agent stays READY in TCN's
    //   state machine, so HOLD / MUTE / DISCONNECT Operator APIs fail with
    //   "state READY does not handle event fsm.PutCallOnHold".
    // ─────────────────────────────────────────────────────────────

    TCN.startCall = async function (phone, leadId) {
        // ── Pre-checks ──────────────────────────────────────────
        if (!TCN._loggedIn) {
            throw new Error('TCN not logged in. Call TCN.login() first.');
        }
        if (TCN._callActive) {
            throw new Error('A call is already active.');
        }

        // Presence SIP must be established for audio channel
        if (!TCN._isUaReady()) {
            log('startCall: SIP not ready — waiting up to 15s for presence session…');
            try {
                await TCN._waitForReady(15000);
            } catch (waitErr) {
                throw new Error('TCN agent not READY — ' + waitErr.message);
            }
        }

        // ── Validate phone (exactly 10 local digits) ────────────
        // Normalise to 10 local digits ONLY.
        // countryCode "91" is sent separately in the TCN API payload.
        // Sending e.g. "916383702482" (12 digits) + countryCode="91" causes
        // duplicate country-code → TCN validation failure → Result: Invalid, 0 duration.
        var digits = String(phone || '').replace(/\D/g, '');
        if (digits.startsWith('91') && digits.length === 12) digits = digits.slice(2);
        if (digits.startsWith('00')) digits = digits.slice(2);
        if (digits.length !== 10) {
            throw new Error('Invalid phone number — exactly 10 digits required, got ' + digits.length + ' ("' + phone + '"). Do not include country code.');
        }
        var e164Display = '+91' + digits; // display only

        // ── Mark call active ────────────────────────────────────
        TCN._stopIdlePoll();
        TCN._callActive = true;
        TCN._callStartTime = Date.now();
        TCN._callEstablishedAt = 0;
        TCN._activePhone = phone;
        TCN._activeLeadId = leadId || null;

        // ── Create DB call-log (non-fatal) ──────────────────────
        var callLogId = null;
        try {
            var logRes = await fetch('/tcn/call-log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ lead_id: leadId || null, phone: phone }),
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

        // ── Get current ACD session SID (currentSessionId from agentgetstatus) ──
        // This SID is used for ALL Operator APIs: dial, hold, mute, disconnect.
        // It is NOT the same as the ASM session's voiceSessionSid in all cases.
        var sessionSid = TCN._voiceSessionSid || TCN._asmSessionSid;
        try {
            var statusData = await proxy('/tcn/status', {
                sessionSid: String(sessionSid || '')
            });
            var currentId = String(statusData.currentSessionId || '');
            var agentStatus = (statusData.statusDesc || '').toUpperCase();

            log('Agent status before dial', { currentSessionId: currentId, statusDesc: agentStatus });

            if (currentId && currentId !== '0') {
                sessionSid = currentId;
            }
            if (agentStatus !== 'READY') {
                log('WARNING: Agent not READY (status=' + agentStatus + ') — attempting dial anyway');
            }
        } catch (statusErr) {
            log('agentgetstatus failed (non-fatal, using cached SID)', statusErr.message);
        }

        if (!sessionSid) {
            TCN._callActive = false;
            TCN._activePhone = null; TCN._activeLogId = null; TCN._activeLeadId = null;
            if (callLogId) patchCallLog(callLogId, { status: 'failed' });
            var noSidErr = new Error('No valid TCN session SID — cannot initiate call');
            fire('tcn:error', { message: noSidErr.message });
            throw noSidErr;
        }

        // Store ACD session SID — used by hold / resume / mute / disconnect / dtmf
        TCN._callVoiceSessionSid = String(sessionSid);

        // ✅ permanent backup (never touch this anywhere else)
        TCN._fixedSessionSid = TCN._callVoiceSessionSid;

        log('Manual Dial — sessionSid=' + TCN._callVoiceSessionSid + ', phone=' + e164Display + ' (digits=' + digits + ')');

        fire('tcn:callStarted', { phone: phone, callLogId: callLogId });

        // Keep-alive on the ACD session during the call
        TCN._startCallKeepAlive(TCN._callVoiceSessionSid);

        // ── Initiate call via TCN Manual Dial Operator APIs ─────
        // Server runs: dialmanualprepare → processmanualdialcall → manualdialstart
        // TCN registers the call in ACD → agent transitions to INCALL
        // Audio is bridged to the EXISTING presence SIP session (no new SIP UA)
        try {
            log('Manual Dial payload', { sessionSid: TCN._callVoiceSessionSid, phoneNumber: digits, countryCode: '91' });
            var dialResult = await proxy('/tcn/dial', {
                sessionSid: TCN._callVoiceSessionSid,
                phone: digits,   // 10 local digits — countryCode "91" is added server-side

            });
            TCN._activeCallSid = dialResult.callSid || null;
            log('Manual Dial initiated', {
                callSid: dialResult.callSid,
                taskGroupSid: dialResult.taskGroupSid,
                tcn_status: dialResult.tcn_status,
                ok: dialResult.ok,
                isDialValidationOk: dialResult.isDialValidationOk,
                isDnclScrubOk: dialResult.isDnclScrubOk,
                isTimeZoneScrubOk: dialResult.isTimeZoneScrubOk,
            });
            if (dialResult.validationError) {
                throw new Error('TCN validation failed: ' + dialResult.validationError);
            }
            if (!dialResult.ok) {
                log('WARNING: manualdialstart returned not-ok', dialResult.tcn_body);
            }
            // Persist callSid to DB immediately — don't wait until call-end so it's
            // available even if the call drops or status patching fails later.
            if (TCN._activeCallSid && callLogId) {
                patchCallLog(callLogId, { call_sid: TCN._activeCallSid });
                console.log('[TCN] Outbound call_sid stored to DB:', TCN._activeCallSid);
            }
        } catch (dialErr) {
            TCN._stopCallKeepAlive();
            TCN._stopCallStatusPoll();
            TCN._callActive = false;
            TCN._callStartTime = 0;
            TCN._callEstablishedAt = 0;
            TCN._activePhone = null; TCN._activeLogId = null; TCN._activeLeadId = null;
            TCN._callVoiceSessionSid = null;
            if (callLogId) patchCallLog(callLogId, { status: 'failed' });
            var userMsg = dialErr.userMessage || 'Call initiation failed';
            fire('tcn:error', { message: userMsg, raw: dialErr.message });
            throw dialErr;
        }

        // ── Poll agentgetstatus every 5s to detect INCALL and call-end ──
        // TCN transitions READY → INCALL when the PSTN call is answered.
        // INCALL → READY means the customer hung up (remote hangup detection).
        TCN._startCallStatusPoll();
    };

    // ─────────────────────────────────────────────────────────────
    // End Call — agentdisconnect Operator API
    //
    // With Manual Dial, we don't have a per-call SIP session to BYE.
    // The TCN agentdisconnect API terminates the call on TCN's side.
    // The presence SIP session stays alive — no reconnect needed.
    // ─────────────────────────────────────────────────────────────

    TCN.endCall = async function (outcome) {
        if (!TCN._callActive) {
            log('endCall: no active call');
            return;
        }

        TCN._stopCallStatusPoll();
        TCN._stopCallKeepAlive();

        const endedLogId = TCN._activeLogId;
        const endedPhone = TCN._activePhone;
        // Capture BEFORE async disconnect operations clear state
        const wasAnswered = !!TCN._callEstablishedAt;

        const duration = wasAnswered
            ? Math.round((Date.now() - TCN._callEstablishedAt) / 1000)
            : 0;

        // ✅ ALWAYS USE FIXED SID
        let sid = TCN._callVoiceSessionSid || TCN._fixedSessionSid;

        // 🔥 EXTRA SAFETY: REFRESH FROM TCN
        try {
            const statusData = await proxy('/tcn/status', {
                sessionSid: String(sid || '')
            });

            if (statusData.currentSessionId && statusData.currentSessionId !== '0') {
                sid = statusData.currentSessionId;
                console.log('Updated SID from status:', sid);
            }
        } catch (e) {
            console.warn('SID refresh failed, using existing');
        }

        if (!sid) {
            console.error('CRITICAL: No session SID available for disconnect');
            return;
        }

        console.log('Ending call with SID:', sid);

        // Save outcome
        if (endedLogId && outcome) {
            try {
                await fetch('/tcn/call-log/' + endedLogId, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf()
                    },
                    body: JSON.stringify({ outcome })
                });
            } catch (_) { }
        }

        // ✅ STEP 1: Resume if on hold
        if (TCN._onHold) {
            try {
                await proxy('/tcn/resume', { sessionSid: String(sid) });
                await new Promise(r => setTimeout(r, 1000));
            } catch (e) {
                console.warn('Resume failed');
            }
        }

        // ✅ STEP 2: Disconnect
        let disconnected = false;

        for (let i = 1; i <= 3; i++) {
            try {
                const res = await proxy('/tcn/disconnect', {
                    sessionSid: String(sid),
                    callSid: TCN._activeCallSid
                });

                console.log('Disconnect success:', res);
                disconnected = true;
                break;

            } catch (e) {
                console.error('Disconnect attempt failed', i, e.message);
                await new Promise(r => setTimeout(r, 1000));
            }
        }

        if (!disconnected) {
            console.error('CRITICAL: call not disconnected');
        }

        // ✅ RESET AFTER SUCCESS
        TCN._callActive = false;
        TCN._callStartTime = 0;
        TCN._callEstablishedAt = 0;
        TCN._activePhone = null;
        TCN._activeLogId = null;
        TCN._activeLeadId = null;
        TCN._callVoiceSessionSid = null;

        TCN._onHold = false;

        // ❌ DO NOT CLEAR THIS
        // TCN._fixedSessionSid → KEEP for safety

        const endedCallSid = TCN._activeCallSid;
        TCN._activeCallSid = null;

        if (endedLogId) {
            // Use 'completed' when the call was answered; 'canceled' when agent
            // hung up before the remote party answered (avoids 422 from the
            // server which requires answered_at for status='completed').
            patchCallLog(endedLogId, {
                status: wasAnswered ? 'completed' : 'canceled',
                duration: wasAnswered ? duration : undefined,
                ended_at: new Date(Date.now()).toISOString(),
                ended_by: agentRole(),
                call_sid: endedCallSid,
            });
        }

        fire('tcn:callEnded', {
            phone: endedPhone,
            callLogId: endedLogId,
            duration: duration
        });

        log('endCall complete — duration ' + duration + 's');

        TCN._startWrapupMonitor();
    };

    // ─────────────────────────────────────────────────────────────
    // Mute / Unmute (local microphone track via presence SIP session)
    //
    // With Manual Dial, audio flows through the presence SIP session
    // (_sipSession). There is no separate per-call SIP session.
    // ─────────────────────────────────────────────────────────────

    TCN.mute = function () {
        var session = TCN._sipSession;
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
        var session = TCN._sipSession;
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

    // ─────────────────────────────────────────────────────────────
    // Hold / Resume  (TCN Operator API — agentputcallonhold / agentgetcallfromhold)
    //
    // sessionSid = TCN._callVoiceSessionSid  (set on each outbound call)
    // ─────────────────────────────────────────────────────────────

    TCN._onHold = false;

    TCN.hold = async function () {
        var sid = TCN._callVoiceSessionSid;
        if (!sid) { log('hold: no call session SID'); return; }
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

    // ─────────────────────────────────────────────────────────────
    // DTMF  (TCN Operator API — playdtmf)
    // digit: '0'-'9', '*', '#'
    // ─────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────
    // Call Status Polling
    //
    // Polls agentgetstatus every 2s during an active Manual Dial call.
    //   OUTBOUND_LOCKED         : PSTN leg being placed — wait
    //   INCALL × 2 consecutive  : customer answered → fire tcn:callAnswered
    //   TALKING (immediate)     : customer answered → fire tcn:callAnswered
    //   INCALL/TALKING → READY  : remote hangup → fire tcn:callEnded via _handleCallEnded
    //   No answer after 3 min   : assume failed → _handleCallEnded
    // ─────────────────────────────────────────────────────────────

    TCN._startCallStatusPoll = function () {
        TCN._stopCallStatusPoll();
        var pollCount = 0;
        var wrapupCount = 0;   // consecutive WRAPUP polls before INCALL
        var incallCount = 0;   // consecutive INCALL polls (used to confirm customer answered)
        var MAX_POLLS = 90;  // 3 minutes at 2s intervals
        var WRAPUP_FAIL = 10;  // 10 consecutive WRAPUP polls (20s) = call failed on TCN side
        // Require 2 consecutive INCALL polls before firing tcn:callAnswered.
        // TCN briefly shows INCALL while the PSTN outbound leg is still ringing;
        // a sustained INCALL (>= 2 polls = 4 s) means the customer answered.
        var INCALL_CONFIRM = 2;
        TCN._callStatusPollTimer = setInterval(async function () {
            if (!TCN._callActive) {
                TCN._stopCallStatusPoll();
                return;
            }
            var sid = TCN._callVoiceSessionSid;
            if (!sid) return;

            try {
                var data = await proxy('/tcn/status', { sessionSid: sid });
                var status = (data.statusDesc || '').toUpperCase();
                var currentSid = String(data.currentSessionId || '0');
                if (currentSid && currentSid !== '0') {
                    if (TCN._callVoiceSessionSid !== currentSid) {
                        console.log('Updating session SID dynamically:', currentSid);
                        TCN._callVoiceSessionSid = currentSid;
                        TCN._fixedSessionSid = currentSid; // keep backup updated
                    }
                }
                pollCount++;

                log('Call status poll #' + pollCount, {
                    statusDesc: status,
                    currentSessionId: currentSid,
                });

                // OUTBOUND_LOCKED = TCN accepted the dial and is placing the PSTN leg.
                // This is the normal intermediate state: READY → OUTBOUND_LOCKED → INCALL.
                // Reset counters and wait — it will transition to INCALL shortly.
                if (status === 'OUTBOUND_LOCKED') {
                    wrapupCount = 0;
                    incallCount = 0;
                    log('OUTBOUND_LOCKED — dial accepted by TCN, waiting for PSTN answer…');
                    return;
                }

                // TALKING = definitive answer (bidirectional audio confirmed by TCN).
                // Fire tcn:callAnswered immediately on first TALKING poll.
                //
                // INCALL = TCN placed the PSTN leg; the customer's phone is ringing.
                // Require INCALL_CONFIRM (2) consecutive INCALL polls before treating
                // it as answered. A single INCALL poll during ringing must not start
                // the call timer — that is Bug 1.
                if ((status === 'INCALL' || status === 'TALKING' || status === 'PEERED') && !TCN._callEstablishedAt) {
                    wrapupCount = 0;

                    if (status === 'TALKING') {
                        incallCount = INCALL_CONFIRM;
                    } else {
                        incallCount++;
                        log('INCALL poll ' + incallCount + '/' + INCALL_CONFIRM + ' — waiting for confirmation');
                    }

                    if (incallCount >= INCALL_CONFIRM) {
                        TCN._callEstablishedAt = Date.now();
                        // Safeguard: resolve caller number now that TCN has a full call record.
                        // getclientinfodata is safe to call at INCALL/TALKING (not before).
                        var _noPhoneConfirm = !TCN._activePhone ||
                            _BLANK_LABELS.includes(String(TCN._activePhone || '').toLowerCase());
                        if (TCN._activeCallSid && _noPhoneConfirm) {
                            resolveCallerWithRetry(TCN._activeCallSid, TCN._activeLogId);
                        }
                        const answeredTime = new Date(TCN._callEstablishedAt).toISOString();
                        fire('tcn:callAnswered', {
                            phone: TCN._activePhone,
                            callLogId: TCN._activeLogId
                        });

                        window.parent.postMessage({
                            type: 'TCN_CALL_ANSWERED',
                            phone: TCN._activePhone,
                            callLogId: TCN._activeLogId,
                        }, '*');

                        if (TCN._activeLogId) {
                            patchCallLog(TCN._activeLogId, {
                                status: 'answered',
                                answered_at: answeredTime,
                            });
                        }

                        log('Call answered — ' + status + ' confirmed after ' + incallCount + ' polls');
                    }
                } else if (status !== 'INCALL' && status !== 'TALKING') {
                    incallCount = 0;
                }

                // Remote party ended the call (agent returned to READY)
                if (status === 'READY' && TCN._callEstablishedAt) {
                    log('Remote hangup detected (status back to READY)');
                    TCN._handleCallEnded();
                    return;
                }

                // WRAPUP after an answered call = remote hangup / TCN-side disconnect.
                // TCN transitions INCALL → WRAPUP → READY; we detect WRAPUP immediately
                // rather than waiting for the full READY transition so the widget can
                // display the wrap-up state while the monitor runs.
                if (status === 'WRAPUP' && TCN._callEstablishedAt) {
                    log('WRAPUP detected — ending call + forcing READY immediately');
                    TCN._handleCallEnded();
                    return;
                }

                // WRAPUP after dial without ever reaching INCALL = call failed on TCN side.
                // TCN drops directly to WRAPUP when: duplicate country code causes validation
                // failure, session mismatch, ACD routing error, or DNCL/timezone scrub block.
                if (status === 'WRAPUP' && !TCN._callEstablishedAt) {
                    wrapupCount++;
                    log('Call stuck in WRAPUP (' + wrapupCount + '/' + WRAPUP_FAIL + ') — never reached INCALL');
                    if (wrapupCount >= WRAPUP_FAIL) {
                        log('Call failed — agent stuck in WRAPUP (likely TCN validation failure)');
                        TCN._handleCallEnded();
                        return;
                    }
                } else if (status !== 'WRAPUP') {
                    wrapupCount = 0;
                }

                // No answer timeout
                if (pollCount >= MAX_POLLS && !TCN._callEstablishedAt) {
                    log('Call timed out — no answer after ' + (pollCount * 2) + 's');
                    TCN._handleCallEnded();
                }

            } catch (e) {
                log('Call status poll error (non-fatal)', e.message);
            }
        }, 2000);
    };

    TCN._stopCallStatusPoll = function () {
        if (TCN._callStatusPollTimer) {
            clearInterval(TCN._callStatusPollTimer);
            TCN._callStatusPollTimer = null;
        }
    };

    // ─────────────────────────────────────────────────────────────
    // WRAPUP Monitor
    //
    // After every call end TCN automatically transitions the agent
    // from WRAPUP → READY.  This monitor polls keepalive every 5 s
    // and waits for that auto-transition.  It does NOT call any
    // setagentstatus API — that endpoint does not exist and caused
    // a 404 loop.
    //
    // Flow:
    //   tcn:callEnded → _startWrapupMonitor() → tcn:wrapup
    //       → poll keepalive every 5s
    //       → WRAPUP: log + wait (TCN handles it)
    //       → READY | AVAILABLE: _endWrapup() → tcn:agentReady
    //       → 2-min hard timeout: _endWrapup() → tcn:agentReady
    // ─────────────────────────────────────────────────────────────

    TCN._stopWrapupPoll = function () {
        if (TCN._wrapupPollTimer) {
            clearInterval(TCN._wrapupPollTimer);
            TCN._wrapupPollTimer = null;
        }
        if (TCN._readyTimeout) {
            clearTimeout(TCN._readyTimeout);
            TCN._readyTimeout = null;
        }
    };

    // Stop polling, reset flags.
    // Dispatches tcn:statusUpdate with READY so the UI always reflects real status.
    TCN._endWrapup = function () {
        TCN._stopWrapupPoll();
        TCN._inWrapup = false;
        TCN._currentStatus = 'READY';
        fire('tcn:statusUpdate', { status: 'READY' });
        log('WRAPUP ended — agent READY (TCN auto-transitioned)');
        // Resume idle poll so next incoming call is detected quickly
        TCN._startIdlePoll();
    };

    // Start monitoring WRAPUP state.  Idempotent — safe to call from both
    // _handleCallEnded (remote hangup) and endCall (agent-initiated).
    TCN._startWrapupMonitor = function () {
        if (TCN._inWrapup) {
            log('WRAPUP monitor already running — skipping duplicate');
            return;
        }
        TCN._inWrapup = true;

        fire('tcn:wrapup');

        // ✅ ADD THIS BLOCK
        window.parent.postMessage({
            type: 'TCN_WRAPUP'
        }, '*');

        log('WRAPUP monitor started — polling every 5 s (max 2 min)');

        var elapsed = 0;
        var INTERVAL = 5000;
        var MAX_WAIT = 120000; // 2 min hard ceiling

        TCN._wrapupPollTimer = setInterval(async function () {
            elapsed += INTERVAL;

            var sid = TCN._voiceSessionSid || TCN._asmSessionSid;
            if (!sid) {
                if (elapsed >= MAX_WAIT) {
                    log('WRAPUP monitor: no sessionSid + timeout — stopping monitor');
                    TCN._endWrapup();
                }
                return;
            }

            try {
                var data = await proxy('/tcn/keepalive', { sessionSid: String(sid) });
                var status = ((data && data.statusDesc) || '').toUpperCase();
                TCN._currentStatus = status;
                fire('tcn:statusUpdate', { status: status });
                log('WRAPUP monitor poll: status=' + status, { elapsedMs: elapsed });

                if (status === 'WRAPUP') {
                    log('WRAPUP detected — scheduling READY API');

                    if (!TCN._readyTimeout) {
                        TCN._readyTimeout = setTimeout(async function () {
                            try {
                                var readySid = TCN._voiceSessionSid || TCN._fixedSessionSid;

                                if (!readySid) {
                                    console.error('No sessionSid for READY API');
                                    return;
                                }

                                console.log('Calling agentsetready API...');

                                await proxy('/tcn/set-ready', {
                                    sessionSid: String(readySid)
                                });

                                console.log('Agent set to READY successfully');

                            } catch (e) {
                                console.error('agentsetready failed', e.message);
                            }

                            TCN._readyTimeout = null;

                        }, 3000);
                    }

                    return;
                } else if (status === 'READY' || status === 'AVAILABLE') {
                    log('WRAPUP completed automatically — status=' + status);
                    TCN._endWrapup();
                } else if (elapsed >= MAX_WAIT) {
                    log('WRAPUP monitor: 2-min timeout — stopping monitor');
                    TCN._endWrapup();
                }
            } catch (e) {
                log('WRAPUP monitor poll error (non-fatal)', e.message);
                if (elapsed >= MAX_WAIT) {
                    TCN._endWrapup();
                }
            }
        }, INTERVAL);
    };

    // Common teardown for remote-hangup and timeout (not agent-initiated endCall)
    TCN._handleCallEnded = function () {
        TCN._stopCallStatusPoll();
        TCN._stopCallKeepAlive();

        // Capture values BEFORE clearing state
        var wasAnswered = !!TCN._callEstablishedAt;
        var duration = wasAnswered
            ? Math.round((Date.now() - TCN._callEstablishedAt) / 1000) : 0;
        var endedLogId = TCN._activeLogId;
        var endedPhone = TCN._activePhone;
        var endedCallSid = TCN._activeCallSid;

        TCN._callActive = false;
        TCN._callStartTime = 0;
        TCN._callEstablishedAt = 0;
        TCN._activePhone = null;
        TCN._activeLogId = null;
        TCN._activeLeadId = null;
        TCN._callVoiceSessionSid = null;
        TCN._onHold = false;

        var agentEndedThis = TCN._agentEndedIncomingCall || TCN._agentEndedCall;
        TCN._agentEndedIncomingCall = false;

        if (endedLogId) {
            var patch = {
                // Use 'completed' only when the call was actually answered;
                // otherwise 'failed' — prevents 422 from updateCallLog which
                // requires answered_at when status='completed'.
                status: wasAnswered ? 'completed' : 'failed',
                ended_at: new Date(Date.now()).toISOString(),
                ended_by: agentEndedThis ? agentRole() : 'lead',
                call_sid: endedCallSid,
            };
            if (wasAnswered && duration > 0) {
                patch.duration = duration;
            }
            patchCallLog(endedLogId, patch);
        }

        fire('tcn:callEnded', { phone: endedPhone, callLogId: endedLogId, duration: duration });
        log('Call ended (remote/timeout) — duration ' + duration + 's');

        // Start WRAPUP monitor — agent must explicitly be set READY after WRAPUP.
        TCN._startWrapupMonitor();
    };

    // ─────────────────────────────────────────────────────────────
    // Incoming Call — Accept / Reject / End
    //
    // Accept:  agent clicks Accept → invitation.accept() → attach audio
    //          → fire tcn:callStarted then tcn:callAnswered so the parent
    //            call-bar lights up via TCN_CALL_STARTED / TCN_CALL_ANSWERED.
    //
    // Reject:  agent clicks Reject (or 15 s timeout) → invitation.reject()
    //          → fire tcn:incomingCallRejected.
    //
    // End:     agent clicks End during an accepted incoming call
    //          → invitation.bye() → SIP Terminated fires → _handleIncomingCallEnded.
    // ─────────────────────────────────────────────────────────────

    TCN.acceptIncomingCall = async function () {

        // In PBX/manual-answer mode TCN does not send a SIP INVITE, so
        // _incomingSession will be null. We still have _activePhone set by the
        // incoming poll that detected PBX_POPUP_LOCKED.
        const invitation = TCN._incomingSession;
        const pbxMode    = !invitation && TCN._isIncoming;

        if (!invitation && !pbxMode) {
            console.log('[TCN] No incoming call to accept');
            return;
        }

        console.log('[TCN] Accepting incoming call via PBX API...');

        // Stop the fast incoming poll — we'll let _startCallStatusPoll take over
        TCN._stopIncomingPoll();
        TCN._stopSipAniPoll();
        TCN._pendingIncomingDisposition = null;

        if (TCN._incomingTimeout) {
            clearTimeout(TCN._incomingTimeout);
            TCN._incomingTimeout = null;
        }

        TCN._incomingSession = null;

        // Use ANI captured by incoming poll, or extract from SIP identity
        let phone = TCN._activePhone || 'Incoming';
        if (invitation) {
            try { phone = invitation.remoteIdentity.uri.user || phone; } catch (e) { }
        }

        try {
            const sid = TCN._voiceSessionSid;
            const approveResult = await proxy('/tcn/approve-call', { sessionSid: String(sid) });
            console.log('[TCN] Incoming call approved via PBX API', approveResult);
            // Extract ANI from approve-call response if not already known
            // Capture P3 callSid from approve-call response — this is the REAL P3 callSid
            // required by getclientinfodata (different from voice currentSessionId)
            var approvedCallSid = approveResult && (approveResult.callSid || approveResult.callId
                || approveResult.p3CallSid || approveResult.taskCallSid);
            if (approvedCallSid) {
                TCN._activeCallSid = String(approvedCallSid);
                log('P3 callSid from approve-call: ' + TCN._activeCallSid);
                console.log('[TCN] ✅ CallSid after approve:', TCN._activeCallSid);
            } else {
                console.warn('[TCN] approve-call returned no callSid — caller info cannot be resolved');
            }

            var approvedAni = approveResult && (approveResult.ani || approveResult.callerAni || approveResult.callerPhone || approveResult.callerNumber || approveResult.fromNumber || approveResult.from || approveResult.cid);
            if (approvedAni) {
                phone = String(approvedAni);
                TCN._activePhone = phone;
                console.log('[TCN] Resolved number (from approve-call ANI):', phone);
                fire('tcn:phoneResolved', { phone: phone });
            } else if (TCN._activeCallSid) {
                // phone still unknown — resolve with retry now that callSid is confirmed from approve-call
                console.log('[TCN] No ANI in approve-call response — resolving via callSid:', TCN._activeCallSid);
                resolveCallerWithRetry(TCN._activeCallSid, TCN._activeLogId);
            }
        } catch (e) {
            console.error('[TCN] Approve call API failed:', e);
            TCN._isIncoming = false;
            TCN._activePhone = null;
            return;
        }

        // API succeeded — mark call active BEFORE any further setup so that
        // endCall / handleHangup guards pass correctly.
        TCN._callActive = true;
        TCN._isIncoming = true;
        TCN._callEstablishedAt = Date.now();
        TCN._activePhone = phone;

        // If the initial createInboundCallLog failed (race or DB error), create it now.
        if (!TCN._activeLogId) {
            await createInboundCallLogFallback(phone, new Date(TCN._callEstablishedAt).toISOString(), TCN._activeCallSid);
        } else {
            // Patch status + answered_at. Also write the real callSid now that approve-call
            // has returned it — this is the ONLY correct time to store call_sid for inbound.
            var acceptedPatch = {
                status: 'answered',
                answered_at: new Date(TCN._callEstablishedAt).toISOString(),
            };
            if (TCN._activeCallSid) {
                acceptedPatch.call_sid = TCN._activeCallSid;
            }
            patchCallLog(TCN._activeLogId, acceptedPatch);
        }

        if (invitation) {
            TCN._acceptedInvitation = invitation;
            TCN._attachRemoteAudio(invitation);
            // SIP mode: listen for Terminated to detect remote hang-up
            invitation.stateChange.addListener(function (state) {
                console.log('[TCN] Incoming call state:', state);
                if (state === 'Terminated') {
                    TCN._handleIncomingCallEnded();
                }
            });
        } else {
            // PBX mode: audio bridges via the presence SIP session
            if (TCN._sipSession) {
                TCN._attachRemoteAudio(TCN._sipSession, 'tcn-remote-audio');
            }
            // Start status poll so WRAPUP/READY is detected and tcn:callEnded fires
            TCN._callVoiceSessionSid = String(TCN._voiceSessionSid || TCN._asmSessionSid || '');
            TCN._fixedSessionSid     = TCN._callVoiceSessionSid;
            TCN._startCallStatusPoll();
        }

        // Post-approve ANI/callSid poll — runs for BOTH SIP and PBX modes.
        // approve-call often returns no callSid or ANI (TCN's agentpbxapprovecall
        // does not guarantee these fields). After approval, TCN transitions the agent
        // to INCALL and agentgetstatus then exposes the integer callSid + real ANI.
        // Poll up to 5 times every 2 s (10 s window) to capture them.
        (function _postApproveAniPoll(attemptsLeft) {
            var _np = !TCN._activePhone || _BLANK_LABELS.includes(String(TCN._activePhone || '').toLowerCase());
            if (!_np) return; // phone already known — nothing to do
            setTimeout(async function () {
                if (!TCN._callActive) return;
                _np = !TCN._activePhone || _BLANK_LABELS.includes(String(TCN._activePhone || '').toLowerCase());
                if (!_np) return; // resolved between polls

                var sid = TCN._voiceSessionSid || TCN._asmSessionSid;
                if (!sid) return;
                try {
                    var stData = await proxy('/tcn/status', { sessionSid: String(sid) });
                    var stAni = stData && (
                        stData.ani || stData.callerAni || stData.callerPhone ||
                        stData.callerNumber || stData.fromNumber || stData.from || stData.cid
                    );
                    var stCallSid = stData && (stData.callSid || stData.callId);
                    var stCallSidStr = stCallSid ? String(stCallSid) : null;
                    var stIntSid = stCallSidStr && /^\d+$/.test(stCallSidStr) && stCallSidStr !== '0';

                    // Capture callSid if not already set from approve-call
                    if (stIntSid && !TCN._activeCallSid) {
                        TCN._activeCallSid = stCallSidStr;
                        if (TCN._activeLogId) patchCallLog(TCN._activeLogId, { call_sid: stCallSidStr });
                        console.log('[TCN] Post-approve poll: callSid captured =', stCallSidStr);
                    }

                    if (stAni) {
                        // ANI returned directly by agentgetstatus — store + fire immediately
                        TCN._activePhone = String(stAni);
                        fire('tcn:phoneResolved', { phone: String(stAni), name: null });
                        if (TCN._activeLogId) patchCallLog(TCN._activeLogId, { customer_number: String(stAni) });
                        console.log('[TCN] Post-approve poll: ANI resolved =', String(stAni));
                    } else if (TCN._activeCallSid) {
                        // No ANI in status but callSid is confirmed — fetch via getclientinfodata
                        resolveCallerWithRetry(TCN._activeCallSid, TCN._activeLogId);
                    } else if (attemptsLeft > 1) {
                        _postApproveAniPoll(attemptsLeft - 1);
                    }
                } catch (_) {
                    if (attemptsLeft > 1) _postApproveAniPoll(attemptsLeft - 1);
                }
            }, 2000);
        })(5);

        fire('tcn:callStarted',  { phone: phone, callLogId: TCN._activeLogId });
        fire('tcn:callAnswered', { phone: phone, callLogId: TCN._activeLogId });
    };

    TCN.rejectIncomingCall = async function () {

        TCN._stopIncomingPoll();
        TCN._stopSipAniPoll();

        if (TCN._incomingTimeout) {
            clearTimeout(TCN._incomingTimeout);
            TCN._incomingTimeout = null;
        }

        TCN._incomingSession = null;
        TCN._isIncoming = false;
        TCN._activePhone = null;

        var rejectedPatch = { status: 'rejected', ended_at: new Date().toISOString(), ended_by: agentRole() };
        if (TCN._activeLogId) {
            patchCallLog(TCN._activeLogId, rejectedPatch);
            TCN._activeLogId = null;
        } else {
            // Log not yet created (race condition) — apply when createInboundCallLog resolves
            TCN._pendingIncomingDisposition = rejectedPatch;
        }

        const sid = TCN._voiceSessionSid;

        try {
            await proxy('/tcn/reject-call', {
                sessionSid: String(sid)
            });
            console.log('[TCN] Incoming call rejected via PBX API');
        } catch (e) {
            console.error('[TCN] Reject call API failed (non-fatal):', e.message);
        }

        window.dispatchEvent(new CustomEvent('tcn:incomingCallRejected'));
    };

    // Agent-initiated hangup of an accepted incoming call.
    // SIP mode: sends BYE on the invitation.
    // PBX mode: calls agentdisconnect Operator API (no SIP session to BYE).
    TCN.endIncomingCall = async function () {
        TCN._stopCallStatusPoll();
        TCN._stopCallKeepAlive();
        TCN._agentEndedIncomingCall = true;

        if (TCN._acceptedInvitation) {
            // SIP mode — stateChange Terminated fires _handleIncomingCallEnded
            // which reads _agentEndedIncomingCall to set ended_by correctly.
            try { TCN._acceptedInvitation.bye(); } catch (_) { }
            return;
        }

        // PBX mode — disconnect via Operator API then handle locally
        var sid = TCN._callVoiceSessionSid || TCN._fixedSessionSid || TCN._voiceSessionSid;
        if (sid) {
            try {
                await proxy('/tcn/disconnect', { sessionSid: String(sid), callSid: TCN._activeCallSid });
                log('PBX incoming call disconnected via Operator API');
            } catch (e) {
                log('PBX disconnect failed (non-fatal)', e.message);
            }
        }
        TCN._handleIncomingCallEnded(true);
    };

    // Triggered by invitation.stateChange → Terminated after accept(),
    // or called directly from endIncomingCall (PBX mode).
    // endedByAgent: explicit override; otherwise reads TCN._agentEndedIncomingCall.
    TCN._handleIncomingCallEnded = function (endedByAgent) {
        if (!TCN._isIncoming) return;   // guard against double-fire

        var byAgent = endedByAgent || TCN._agentEndedIncomingCall;
        TCN._agentEndedIncomingCall = false;

        var duration = TCN._callEstablishedAt
            ? Math.round((Date.now() - TCN._callEstablishedAt) / 1000) : 0;
        var phone = TCN._activePhone;
        var logId  = TCN._activeLogId;

        TCN._callActive = false;
        TCN._isIncoming = false;
        TCN._callEstablishedAt = 0;
        TCN._activePhone = null;
        TCN._activeLogId = null;
        TCN._activeCallSid = null;
        TCN._acceptedInvitation = null;

        var resolvedSid = TCN._activeCallSid || TCN._voiceSessionSid || TCN._fixedSessionSid;

        if (logId) {
            var patch = {
                status: duration > 0 ? 'completed' : 'missed',
                ended_at: new Date().toISOString(),
                ended_by: byAgent ? agentRole() : 'lead',
            };
            if (duration > 0) patch.duration = duration;
            patchCallLog(logId, patch);

            // Try to resolve caller phone post-call via TCN call detail API (non-fatal)
            var _phoneUnknown = !phone || _BLANK_LABELS.includes(String(phone).toLowerCase());
            if (_phoneUnknown && resolvedSid) {
                (async function () {
                    try {
                        var res = await fetch('/tcn/resolve-caller', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                            body: JSON.stringify({ call_log_id: logId, session_sid: resolvedSid }),
                        });
                        var result = await res.json();
                        if (result.ok && result.phone) {
                            log('Caller phone resolved post-call', result.phone);
                            fire('tcn:phoneResolved', { phone: String(result.phone) });
                        }
                    } catch (_) {}
                })();
            }
        }

        fire('tcn:callEnded', { phone: phone, callLogId: logId, duration: duration });

        window.parent.postMessage({
            type: 'TCN_CALL_ENDED',
            phone: phone,
            callLogId: logId,
            duration: duration,
            status: duration > 0 ? 'completed' : 'missed',
            ended_by: byAgent ? agentRole() : 'lead',
        }, '*');

        log('Incoming call ended — duration ' + duration + 's');

        // TCN puts the agent in WRAPUP after every call — start monitor so agentReady
        // is sent and the agent returns to READY for the next inbound or outbound call.
        TCN._startWrapupMonitor();
    };

    // ─────────────────────────────────────────────────────────────
    // loginWithToken — skip /api/tcn/config fetch (step 1) when the
    // caller already has credentials. Used by TcnService.init() to
    // avoid a redundant config request. Runs steps 2–5 identically
    // to login().
    // ─────────────────────────────────────────────────────────────

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
                // Session is alive — skip config + skills fetch.
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
                TCN._reconnectAttempts = 0;
                log('loginWithToken complete using cached session');
                fire('tcn:ready');
            TCN._startIdlePoll();
                return;
            }

            TCN._cleanupSip();
            TCN._asmSessionSid = null;
            TCN._voiceSessionSid = null;

            // Step 2 — Agent skills
            log('Step 2: Getting agent skills\u2026');
            var skillsData = await proxy('/tcn/skills', {
                huntGroupSid: parseInt(TCN._huntGroupSid) || 0,
                agentSid: parseInt(TCN._agentSid) || 0,
            });
            TCN._skills = skillsData.skills || {};
            log('Skills loaded', TCN._skills);

            // Step 3 — Create ASM session (SIP credentials)
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

            // Step 4 — Load SIP.js and establish presence SIP session
            log('Loading SIP.js\u2026');
            await loadSipJs();
            var SIP = (window.SIP && window.SIP.SIP) ? window.SIP.SIP : window.SIP;
            if (!SIP || !SIP.UserAgent) {
                throw new Error('SIP.js not loaded \u2014 /js/sip.js must export window.SIP');
            }
            await callDialUrl(SIP);

            // Step 5 — Start keep-alive only after SIP Established (agent is READY)
            TCN._startKeepAlive();

            TCN._loggedIn = true;
            TCN._loginInProgress = false;
            TCN._reconnectAttempts = 0;
            log('loginWithToken complete — agent READY');
            fire('tcn:ready');
            TCN._startIdlePoll();

        } catch (e) {
            clearCache();
            TCN._loginInProgress = false;
            log('loginWithToken failed', e.message);
            fire('tcn:error', { message: e.message });
            throw e;
        }
    };

    // ─────────────────────────────────────────────────────────────
    // Logout — explicit teardown
    // ─────────────────────────────────────────────────────────────

    TCN.logout = function () {
        TCN._stopKeepAlive();
        TCN._stopCallKeepAlive();
        TCN._stopCallStatusPoll();
        TCN._stopWrapupPoll();
        TCN._inWrapup = false;
        TCN._cleanupSip();

        TCN._loggedIn = false;
        TCN._callActive = false;
        TCN._reconnecting = false;
        TCN._reconnectAttempts = 0;
        TCN._sipEstablishedAt = 0;
        TCN._accessToken = null;
        TCN._asmSessionSid = null;
        TCN._voiceSessionSid = null;
        TCN._callStartTime = 0;
        TCN._activePhone = null;
        TCN._activeLogId = null;
        TCN._keepAliveFailCount = 0;

        log('Logged out');
        fire('tcn:loggedOut');
    };

    // ─────────────────────────────────────────────────────────────
    // Expose globally
    // ─────────────────────────────────────────────────────────────
    window.TCN = TCN;

})();
