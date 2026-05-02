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
        _outboundSession: null,     // active outbound call SIP session
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
        _agentReadyCalled: false,
        _wrapupPollTimer: null,
        _stopWrapupPoll: null,       // assigned below
        _callAgentReady: null,       // assigned below
        _endWrapup: null,            // assigned below
        _startWrapupMonitor: null,   // assigned below

        // Lifecycle flags
        _loggedIn: false,
        _loginInProgress: false,
        _callActive: false,
        _reconnecting: false,

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
        _apiBase: 'https://api.bom.tcn.com',
    };

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
            throw new Error('[TCN] ' + path + ' failed (' + res.status + '): ' + JSON.stringify(json));
        }
        return json;
    }

    function buildClientCallSid() {
        return 'tcn-web-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

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

    function clearAnswerTimer() {
        if (TCN._callAnswerTimer) {
            clearTimeout(TCN._callAnswerTimer);
            TCN._callAnswerTimer = null;
        }
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
        clearAnswerTimer();
        TCN._stopCallKeepAlive();
        TCN._stopCallStatusPoll();
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
                // Reject unexpected inbound SIP INVITEs.
                // All outbound calls are agent-initiated (SIP Inviter).
                // No manualdial bridge invites are expected.
                delegate: {
                    onInvite: function (invitation) {
                        log('Unexpected inbound SIP INVITE — rejecting');
                        try { invitation.reject(); } catch (_) { }
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
        } catch (dialErr) {
            TCN._stopCallKeepAlive();
            TCN._stopCallStatusPoll();
            TCN._callActive = false;
            TCN._callStartTime = 0;
            TCN._callEstablishedAt = 0;
            TCN._activePhone = null; TCN._activeLogId = null; TCN._activeLeadId = null;
            TCN._callVoiceSessionSid = null;
            if (callLogId) patchCallLog(callLogId, { status: 'failed' });
            fire('tcn:error', { message: 'Call initiation failed: ' + dialErr.message });
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
        clearAnswerTimer();

        const endedLogId = TCN._activeLogId;
        const endedPhone = TCN._activePhone;

        const duration = TCN._callEstablishedAt
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
        TCN._activeCallSid = null;
        TCN._onHold = false;

        // ❌ DO NOT CLEAR THIS
        // TCN._fixedSessionSid → KEEP for safety

        if (endedLogId) {
            patchCallLog(endedLogId, {
                status: 'completed',
                duration: duration,
                ended_at: new Date(Date.now()).toISOString(),
                ended_by: 'telecaller'
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

        // Verify agent is in INCALL/TALKING state before attempting hold.
        // TCN returns 500 "state READY does not handle event fsm.PutCallOnHold"
        // if called while READY (call not registered in ACD).
        // try {
        //     var statusData = await proxy('/tcn/status', { sessionSid: sid });
        //     var agentStatus = (statusData.statusDesc || '').toUpperCase();
        //     log('Agent status before hold', { agentStatus, sessionSid: sid });

        //     if (agentStatus !== 'INCALL' && agentStatus !== 'TALKING' && agentStatus !== 'PEERED') {
        //         log('hold blocked — agent not INCALL (status=' + agentStatus + ')');
        //         fire('tcn:error', { message: 'Cannot hold — agent not in active call state (' + agentStatus + ')' });
        //         return;
        //     }
        // } catch (e) {
        //     log('Status check before hold failed (proceeding anyway)', e.message);
        // }

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

                    // ✅ Immediate READY call
                    // TCN._callAgentReady();

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
    // After every call end (agent-initiated or remote), TCN moves the
    // agent into WRAPUP before returning to READY.  Without an explicit
    // agentReady() call at WRAPUP-end the agent can stay stuck in WRAPUP
    // indefinitely, preventing any next call.
    //
    // Flow:
    //   tcn:callEnded → _startWrapupMonitor() → tcn:wrapup
    //       → poll keepalive every 5s
    //       → status READY | UNAVAILABLE or 2-min timeout
    //       → _callAgentReady() (POST /tcn/set-status READY)
    //       → tcn:agentReady
    // ─────────────────────────────────────────────────────────────

    TCN._stopWrapupPoll = function () {
        if (TCN._wrapupPollTimer) {
            clearInterval(TCN._wrapupPollTimer);
            TCN._wrapupPollTimer = null;
        }
    };

    // POST /tcn/set-status READY — idempotent guard via _agentReadyCalled.
    TCN._callAgentReady = async function () {
        if (TCN._agentReadyCalled) {
            log('agentReady already called this cycle — skipping duplicate');
            return;
        }
        TCN._agentReadyCalled = true;
        log('Calling agentReady (POST /tcn/set-status READY) after WRAPUP…');
        try {
            var res = await fetch('/tcn/set-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ status: 'READY' }),
            });
            var data = await res.json();
            log('agentReady response', data);
        } catch (e) {
            log('agentReady call failed (non-fatal)', e.message);
        }
    };

    // Stop polling, call agentReady, fire tcn:agentReady for the widget.
    TCN._endWrapup = async function () {
        TCN._stopWrapupPoll();
        TCN._inWrapup = false;
        await TCN._callAgentReady();
        fire('tcn:agentReady');
        log('WRAPUP ended — agent set to READY');
    };

    // Start monitoring WRAPUP state.  Idempotent — safe to call from both
    // _handleCallEnded (remote hangup) and endCall (agent-initiated).
    TCN._startWrapupMonitor = function () {
        if (TCN._inWrapup) {
            log('WRAPUP monitor already running — skipping duplicate');
            return;
        }
        TCN._inWrapup = true;
        TCN._agentReadyCalled = false;

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
                    log('WRAPUP monitor: no sessionSid + timeout — forcing READY');
                    await TCN._endWrapup();
                }
                return;
            }

            try {
                var data = await proxy('/tcn/keepalive', { sessionSid: String(sid) });
                var status = ((data && data.statusDesc) || '').toUpperCase();
                log('WRAPUP monitor poll: status=' + status, { elapsedMs: elapsed });

                if (status === 'WRAPUP') {
                    log('WRAPUP detected — calling READY immediately');
                    await TCN._endWrapup();
                }
                else if (elapsed >= MAX_WAIT) {
                    log('WRAPUP monitor: 2-min timeout — forcing agentReady');
                    await TCN._endWrapup();
                }
            } catch (e) {
                log('WRAPUP monitor poll error (non-fatal)', e.message);
                if (elapsed >= MAX_WAIT) {
                    await TCN._endWrapup();
                }
            }
        }, INTERVAL);
    };

    // Common teardown for remote-hangup and timeout (not agent-initiated endCall)
    TCN._handleCallEnded = function () {
        TCN._stopCallStatusPoll();
        TCN._stopCallKeepAlive();
        clearAnswerTimer();

        var duration = TCN._callEstablishedAt
            ? Math.round((Date.now() - TCN._callEstablishedAt) / 1000) : 0;
        var endedLogId = TCN._activeLogId;
        var endedPhone = TCN._activePhone;

        TCN._callActive = false;
        TCN._callStartTime = 0;
        TCN._callEstablishedAt = 0;
        TCN._activePhone = null;
        TCN._activeLogId = null;
        TCN._activeLeadId = null;
        TCN._callVoiceSessionSid = null;
        TCN._onHold = false;

        if (endedLogId) {
            patchCallLog(endedLogId, {
                status: 'completed',
                // duration: duration,
                ended_at: new Date(Date.now()).toISOString(),
                ended_by: 'lead'
            });
        }

        fire('tcn:callEnded', { phone: endedPhone, callLogId: endedLogId, duration: duration });
        log('Call ended (remote/timeout) — duration ' + duration + 's');

        // Start WRAPUP monitor — agent must explicitly be set READY after WRAPUP.
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
        TCN._agentReadyCalled = false;
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
