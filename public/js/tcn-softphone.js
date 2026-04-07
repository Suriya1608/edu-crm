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

        // Keep-alive — outbound call session (fresh SID per call)
        _callKeepAliveTimer: null,
        _callVoiceSessionSid: null,

        // Lifecycle flags
        _loggedIn: false,
        _loginInProgress: false,
        _callActive: false,
        _reconnecting: false,

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
            var raw = sessionStorage.getItem(TCN.CACHE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (_) {
            return null;
        }
    }

    function writeCache() {
        try {
            // dialUrl is single-use per TCN API — never cache it.
            // A new ASM session must be created each login to get a fresh dialUrl.
            sessionStorage.setItem(TCN.CACHE_KEY, JSON.stringify({
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
            sessionStorage.removeItem(TCN.CACHE_KEY);
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

            if (!keepAliveOk || currentSessionId === '0' || status === 'DISCONNECTED' || status === 'LOGGED_OUT') {
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
            var remoteStream = new MediaStream();
            sdh.peerConnection.getReceivers().forEach(function (rx) {
                if (rx.track) remoteStream.addTrack(rx.track);
            });
            var audio = document.getElementById(elementId);
            if (!audio) {
                audio = document.createElement('audio');
                audio.id = elementId;
                audio.autoplay = true;
                audio.style.display = 'none';
                audio.setAttribute('playsinline', '');
                document.body.appendChild(audio);
            }
            audio.srcObject = remoteStream;
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
                        TCN._attachRemoteAudio(inviter, 'tcn-presence-audio');
                        log('Presence SIP Established — agent READY');
                        resolve();

                    } else if (state === 'Terminated' && !settled) {
                        // Failed to establish
                        settled = true;
                        clearTimeout(timer);
                        reject(new Error('Presence SIP terminated before Established — check credentials/dial_url'));

                    } else if (state === 'Terminated' && settled) {
                        // Dropped after successful login — schedule reconnect
                        TCN._registered = false;
                        TCN._sipSession = null;
                        log('Presence SIP dropped — scheduling reconnect…');
                        fire('tcn:sipDropped');

                        if (!TCN._callActive && !TCN._reconnecting) {
                            TCN._reconnecting = true;
                            TCN._loggedIn = false;
                            TCN._stopKeepAlive();
                            // Clean up the dead UA so login() gets a clean slate
                            if (TCN._ua) {
                                try { TCN._ua.stop(); } catch (_) { }
                                TCN._ua = null;
                            }
                            setTimeout(function () {
                                TCN._reconnecting = false;
                                log('Auto-reconnecting…');
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

    TCN.startCall = async function (phone, leadId) {
        // ── Pre-checks ─────────────────────────────────────────
        if (!TCN._loggedIn) {
            throw new Error('TCN not logged in. Call TCN.login() first.');
        }
        if (TCN._callActive) {
            throw new Error('A call is already active.');
        }

        // Wait for SIP to be fully ready before proceeding.
        // This handles: (a) presence session still establishing at login,
        // (b) call pressed during the ~3s reconnect window after a drop,
        // (c) any other transient unready state.
        if (!TCN._isUaReady()) {
            log('startCall: SIP not ready — waiting up to 15s for presence session…');
            try {
                await TCN._waitForReady(15000);
            } catch (waitErr) {
                throw new Error('TCN agent not READY — ' + waitErr.message);
            }
        }

        // ── Validate phone (exactly 10 local digits) ───────────
        var digits = String(phone || '').replace(/\D/g, '');
        if (digits.startsWith('91') && digits.length === 12) digits = digits.slice(2);
        if (digits.startsWith('00')) digits = digits.slice(2);
        if (digits.length !== 10) {
            throw new Error('Invalid phone number — 10 digits required, got ' + digits.length + ' (' + phone + ')');
        }
        var e164 = '91' + digits;

        // ── Mark call active BEFORE creating the call ASM session ───
        // Creating the call ASM session via /tcn/session terminates the
        // existing presence SIP session on TCN's side. Without this flag
        // the presence-drop handler fires reconnect(), which creates a NEW
        // presence session that invalidates the call session's dialUrl —
        // causing the SIP INVITE to connect and immediately BYE (0s call).
        // Setting _callActive = true here suppresses that reconnect.
        TCN._callActive = true;
        TCN._callStartTime = Date.now();
        TCN._callEstablishedAt = 0;
        TCN._activePhone = phone;
        TCN._activeLeadId = leadId || null;

        // ── Create DB call-log (non-fatal) ────────────────────
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
                var errBody = await logRes.json().catch(function () { return {}; });
                log('call-log create failed (non-fatal), HTTP ' + logRes.status, errBody);
            }
        } catch (logErr) {
            log('call-log create error (non-fatal)', logErr.message);
        }
        TCN._activeLogId = callLogId;

        // ── Create call-specific ASM session ───────────────────
        // Passing phoneNumber + countryCode tells TCN to configure the
        // PSTN leg for this number and return a routing dial_url token.
        // The presence SIP session will drop during this await — that is
        // expected and handled (reconnect suppressed by _callActive = true).
        log('Creating call ASM session for ' + e164 + '…');
        console.log("DEBUG CALL →", {
            phone: e164,
            callerId: TCN._callerId
        });
        var callSession;
        try {
            callSession = await proxy('/tcn/session', {
                huntGroupSid: parseInt(TCN._huntGroupSid) || 0,
                skills: TCN._skills || {},
                subsession_type: 'VOICE',
                phoneNumber: e164,
                countryCode: '91',
                callerId: TCN._callerId || '+918634134466' // 🔥 IMPORTANT
            });
        } catch (sessErr) {
            TCN._callActive = false;
            TCN._activePhone = null; TCN._activeLogId = null; TCN._activeLeadId = null;
            if (callLogId) patchCallLog(callLogId, { status: 'failed' });
            log('startCall: call ASM session failed', sessErr.message);
            fire('tcn:error', { message: 'Failed to create call session: ' + sessErr.message });
            throw sessErr;
        }

        var vr = callSession.voiceRegistration || callSession.voice_registration;
        var callDialUrl = vr ? (vr.dialUrl || vr.dial_url) : null;
        var callSipUser = vr ? (vr.username) : null;
        var callSipPass = vr ? (vr.password) : null;
        var callVoiceSid = callSession.voiceSessionSid || callSession.voice_session_sid
            || callSession.asmSessionSid || callSession.asm_session_sid;

        if (!callDialUrl) {
            TCN._callActive = false;
            TCN._activePhone = null; TCN._activeLogId = null; TCN._activeLeadId = null;
            var missErr = new Error('Call ASM session missing dial_url. Response: ' + JSON.stringify(callSession));
            if (callLogId) patchCallLog(callLogId, { status: 'failed' });
            fire('tcn:error', { message: missErr.message });
            throw missErr;
        }

        log('Call session ready', {
            callDialUrl: callDialUrl,
            callVoiceSid: callVoiceSid,
            callSipUser: callSipUser,      // new per-call credentials
            hasSipPass: !!callSipPass,
        });

        // ── Create dedicated call UA with call-session SIP credentials ──
        //
        // ROOT CAUSE OF "Established → Terminated immediately":
        //   Each call ASM session issues NEW SIP credentials (username/password)
        //   specific to that call session. TCN's SIP gateway validates the
        //   SIP Authorization header against those credentials after the
        //   200 OK. Reusing the old presence UA (with old credentials) causes
        //   TCN to send BYE immediately after answering.
        //
        // Fix: create a fresh SIP.UserAgent with the call session's credentials.
        //   The presence UA's WebSocket may also be reset during call ASM
        //   creation, so a new UA avoids transport instability as well.
        var SIP = (window.SIP && window.SIP.SIP) ? window.SIP.SIP : window.SIP;

        var callUaOptions = {
            transportConstructor: SIP.Web.Transport,
            transportOptions: { server: 'wss://sg-webphone.tcnp3.com' },
            logLevel: 'warn',
            sessionDescriptionHandlerFactoryOptions: {
                constraints: { audio: true, video: false },
                peerConnectionConfiguration: {
                    iceServers: [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:stun1.l.google.com:19302' },
                    ],
                },
            },
            delegate: {
                onInvite: function (invitation) {
                    try { invitation.reject(); } catch (_) { }
                },
            },
        };

        var callUa;
        if (callSipUser && callSipPass) {
            // Use the call session's fresh SIP credentials
            callUaOptions.uri = SIP.UserAgent.makeURI('sip:' + callSipUser + '@sg-webphone.tcnp3.com');
            callUaOptions.authorizationUsername = callSipUser;
            callUaOptions.authorizationPassword = callSipPass;
            log('Creating dedicated call UA for user=' + callSipUser);
            callUa = new SIP.UserAgent(callUaOptions);
            TCN._callUa = callUa;
        } else {
            // No new credentials returned — fall back to existing presence UA.
            // Verify the presence UA is still alive before proceeding.
            log('Call session returned no SIP credentials — reusing presence UA');
            if (!TCN._ua || !TCN._ua.userAgentCore) {
                TCN._callActive = false;
                TCN._activePhone = null; TCN._activeLogId = null; TCN._activeLeadId = null;
                var uaErr = new Error('SIP UA was stopped during call setup. Please try again.');
                if (callLogId) patchCallLog(callLogId, { status: 'failed' });
                fire('tcn:error', { message: uaErr.message });
                throw uaErr;
            }
            callUa = TCN._ua;
        }

        // Start call UA if it is a fresh one (needs WebSocket handshake before INVITE)
        if (callUa !== TCN._ua) {
            try {
                await callUa.start();
            } catch (startErr) {
                if (TCN._callUa) { try { TCN._callUa.stop(); } catch (_) { } TCN._callUa = null; }
                TCN._callActive = false;
                TCN._activePhone = null; TCN._activeLogId = null; TCN._activeLeadId = null;
                if (callLogId) patchCallLog(callLogId, { status: 'failed' });
                fire('tcn:error', { message: 'Call UA failed to start: ' + startErr.message });
                throw startErr;
            }
        }

        fire('tcn:callStarted', { phone: phone, callLogId: callLogId });

        // Start call-session keep-alive so TCN doesn't expire the
        // call session before the PSTN leg is bridged.
        if (callVoiceSid) {
            TCN._startCallKeepAlive(String(callVoiceSid));
        }

        // ── SIP Inviter using call-specific dial_url and dedicated call UA ──
        var target = SIP.UserAgent.makeURI('sip:' + callDialUrl + '@sg-webphone.tcnp3.com');
        var inviter = new SIP.Inviter(callUa, target);
        TCN._outboundSession = inviter;

        log('SIP INVITE → sip:' + callDialUrl + '@sg-webphone.tcnp3.com');

        inviter.stateChange.addListener(function (state) {
            log('Outbound SIP state: ' + state);
            if (state === 'Established') {

                log('SIP connected (waiting for real answer)');

                // Attach audio
                TCN._attachRemoteAudio(inviter, 'tcn-remote-audio');

                // Start keep-alive
                if (callVoiceSid) {
                    TCN._startCallKeepAlive(String(callVoiceSid));
                }

                // ✅ Wait for real pickup (audio detection)
                setTimeout(function () {

                    if (!TCN._callActive) return;

                    var audio = document.getElementById('tcn-remote-audio');

                    if (audio && !audio.paused) {

                        TCN._callEstablishedAt = Date.now();

                        // Fire event
                        fire('tcn:callAnswered', {
                            phone: TCN._activePhone,
                            callLogId: TCN._activeLogId
                        });

                        // Notify parent UI
                        window.parent.postMessage({
                            type: 'TCN_CALL_ANSWERED',
                            phone: TCN._activePhone,
                            callLogId: TCN._activeLogId
                        }, '*');

                        // Update DB
                        if (TCN._activeLogId) {
                            patchCallLog(TCN._activeLogId, {
                                status: 'answered',
                                answered_at: new Date().toISOString()
                            });
                        }

                        log('✅ Real call answered');
                    }

                }, 3000);
            }
            else if (state === 'Terminated') {
                var duration = TCN._callEstablishedAt
                    ? Math.round((Date.now() - TCN._callEstablishedAt) / 1000) : 0;
                var endedLogId = TCN._activeLogId;
                var endedPhone = TCN._activePhone;

                TCN._stopCallKeepAlive();
                TCN._callActive = false;
                TCN._callStartTime = 0;
                TCN._callEstablishedAt = 0;
                TCN._activePhone = null;
                TCN._activeLogId = null;
                TCN._activeLeadId = null;
                TCN._outboundSession = null;

                // Tear down the per-call UA (it was dedicated to this call only)
                if (TCN._callUa) {
                    try { TCN._callUa.stop(); } catch (_) { }
                    TCN._callUa = null;
                }

                if (endedLogId) {
                    patchCallLog(endedLogId, {
                        status: 'completed',
                        duration: duration,
                        ended_at: new Date().toISOString(),
                    });
                }

                fire('tcn:callEnded', { phone: endedPhone, callLogId: endedLogId, duration: duration });
                log('Call terminated — duration ' + duration + 's');

                // The presence SIP dropped when the call ASM session was created.
                // Now that the call is over, re-establish the presence session so
                // the agent goes back to READY for the next call.
                if (!TCN._registered && !TCN._reconnecting) {
                    TCN._loggedIn = false;
                    TCN._reconnecting = true;
                    setTimeout(function () {
                        TCN._reconnecting = false;
                        log('Post-call reconnect — restoring presence…');
                        TCN.login().catch(function (e) {
                            log('Post-call reconnect failed', e.message);
                            fire('tcn:error', { message: 'Post-call reconnect failed: ' + e.message });
                        });
                    }, 1000);
                }
            }
        });

        try {
            await inviter.invite({
                sessionDescriptionHandlerOptions: {
                    constraints: { audio: true, video: false },
                },
            });
            log('SIP INVITE sent — awaiting TCN to bridge PSTN call');
        } catch (invErr) {
            TCN._stopCallKeepAlive();
            TCN._callActive = false;
            TCN._callStartTime = 0;
            TCN._callEstablishedAt = 0;
            TCN._activePhone = null;
            TCN._activeLogId = null;
            TCN._activeLeadId = null;
            TCN._outboundSession = null;
            if (callLogId) patchCallLog(callLogId, { status: 'failed' });
            log('startCall: SIP invite() failed', invErr.message);
            fire('tcn:error', { message: 'SIP call failed: ' + invErr.message });
            throw invErr;
        }
    };

    // ─────────────────────────────────────────────────────────────
    // End Call
    // ─────────────────────────────────────────────────────────────

    TCN.endCall = async function (outcome) {
        if (!TCN._callActive && !TCN._outboundSession) {
            log('endCall: no active call');
            return;
        }

        TCN._stopCallKeepAlive();
        clearAnswerTimer();

        var endedLogId = TCN._activeLogId;
        var endedPhone = TCN._activePhone;
        var duration = TCN._callEstablishedAt
            ? Math.round((Date.now() - TCN._callEstablishedAt) / 1000) : 0;

        // Patch outcome before SIP teardown (Terminated handler doesn't receive it)
        if (endedLogId && outcome) {
            try {
                await fetch('/tcn/call-log/' + endedLogId, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ outcome: outcome }),
                });
            } catch (_) { /* non-fatal */ }
        }

        if (TCN._outboundSession) {
            try {
                var s = TCN._outboundSession.state;
                // Inviter: cancel() pre-answer, bye() post-answer
                if (s === 'Initial' || s === 'Establishing') {
                    await TCN._outboundSession.cancel();
                } else if (s === 'Established') {
                    await TCN._outboundSession.bye();
                }
                // stateChange Terminated fires and does full cleanup + tcn:callEnded
            } catch (e) {
                log('endCall: SIP terminate error (non-fatal)', e.message);
                // Force cleanup if SIP failed
                TCN._callActive = false;
                TCN._callStartTime = 0;
                TCN._callEstablishedAt = 0;
                TCN._activePhone = null;
                TCN._activeLogId = null;
                TCN._activeLeadId = null;
                TCN._outboundSession = null;
                if (TCN._callUa) { try { TCN._callUa.stop(); } catch (_) { } TCN._callUa = null; }
                fire('tcn:callEnded', { phone: endedPhone, callLogId: endedLogId, duration: duration });
            }
        } else {
            TCN._callActive = false;
            TCN._callStartTime = 0;
            TCN._callEstablishedAt = 0;
            TCN._activePhone = null;
            TCN._activeLogId = null;
            TCN._activeLeadId = null;
            fire('tcn:callEnded', { phone: endedPhone, callLogId: endedLogId, duration: duration });
        }

        log('endCall issued');
    };

    // ─────────────────────────────────────────────────────────────
    // Mute / Unmute (local track only)
    // ─────────────────────────────────────────────────────────────

    TCN.mute = function () {
        var session = TCN._outboundSession || TCN._sipSession;
        if (!session || !session.sessionDescriptionHandler) return;
        session.sessionDescriptionHandler.peerConnection
            .getSenders().forEach(function (s) {
                if (s.track && s.track.kind === 'audio') s.track.enabled = false;
            });
        log('Muted');
    };

    TCN.unmute = function () {
        var session = TCN._outboundSession || TCN._sipSession;
        if (!session || !session.sessionDescriptionHandler) return;
        session.sessionDescriptionHandler.peerConnection
            .getSenders().forEach(function (s) {
                if (s.track && s.track.kind === 'audio') s.track.enabled = true;
            });
        log('Unmuted');
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

    // ─────────────────────────────────────────────────────────────
    // Logout — explicit teardown
    // ─────────────────────────────────────────────────────────────

    TCN.logout = function () {
        TCN._stopKeepAlive();
        TCN._stopCallKeepAlive();
        TCN._cleanupSip();

        TCN._loggedIn = false;
        TCN._callActive = false;
        TCN._reconnecting = false;
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
