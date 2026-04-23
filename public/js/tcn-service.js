'use strict';

window.TcnService = (function () {
    if (window.__tcnServiceSingleton) {
        return window.__tcnServiceSingleton;
    }

    let _initialized = false;
    let _initializing = false;
    let _accessToken = null;
    let _agentId = null;
    let _huntGroupId = null;
    let _callerId = null;
    let _tokenFetchedAt = null;

    const TOKEN_TTL_MS = 55 * 60 * 1000;
    // localStorage (not sessionStorage) so the token survives page navigations
    // and iframe recreation — sessionStorage is wiped whenever the iframe is destroyed.
    const CACHE_KEY = 'tcn_service_bootstrap_v2';

    function _log(msg, data) {
        const ts = new Date().toLocaleTimeString();
        if (data !== undefined) {
            console.log('[TcnService ' + ts + '] ' + msg, data);
        } else {
            console.log('[TcnService ' + ts + '] ' + msg);
        }
    }

    function _emit(event, detail) {
        window.dispatchEvent(new CustomEvent('tcnsvc:' + event, { detail: detail || {} }));
    }

    function _isTokenExpired() {
        if (!_tokenFetchedAt) return true;
        return (Date.now() - _tokenFetchedAt) > TOKEN_TTL_MS;
    }

    function _readCache() {
        try {
            const raw = localStorage.getItem(CACHE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (_) {
            return null;
        }
    }

    function _writeCache() {
        try {
            localStorage.setItem(CACHE_KEY, JSON.stringify({
                access_token: _accessToken,
                agent_id: _agentId,
                hunt_group_id: _huntGroupId,
                caller_id: _callerId,
                token_fetched_at: _tokenFetchedAt,
            }));
        } catch (_) { }
    }

    function _clearCache() {
        try {
            localStorage.removeItem(CACHE_KEY);
            // Remove old sessionStorage key (pre-localStorage migration)
            localStorage.removeItem('tcn_service_bootstrap_v1');
            sessionStorage.removeItem('tcn_service_bootstrap_v1');
        } catch (_) { }
    }

    function _restoreFromCache() {
        const cached = _readCache();
        if (!cached || !cached.access_token || !cached.agent_id || !cached.hunt_group_id) {
            return false;
        }

        _accessToken = cached.access_token;
        _agentId = cached.agent_id;
        _huntGroupId = cached.hunt_group_id;
        _callerId = cached.caller_id || '';
        _tokenFetchedAt = Number(cached.token_fetched_at || 0) || null;

        return !_isTokenExpired();
    }

    async function _fetchConfig() {
        const response = await fetch('/api/tcn/config', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.status === 422) {
            const body = await response.json();
            _emit('not_configured', { message: body.error || 'TCN not configured.' });
            return null;
        }

        if (!response.ok) {
            throw new Error('Config fetch failed: HTTP ' + response.status);
        }

        return await response.json();
    }

    function _loadSoftphone() {
        return new Promise(function (resolve, reject) {
            if (typeof window.TCN !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = '/js/tcn-softphone.js';
            script.onload = resolve;
            script.onerror = function () { reject(new Error('Failed to load tcn-softphone.js')); };
            document.head.appendChild(script);
        });
    }

    async function init() {
        // ── Page-level singleton guard ──────────────────────────────────────
        // On a full page reload the JS module is re-evaluated, so _initialized
        // resets to false. window.__tcnSvcInitDone persists for the lifetime of
        // the current page and catches duplicate init() calls that happen before
        // loginWithToken() has finished (e.g. two components calling init() on
        // DOMContentLoaded).
        if (window.__tcnSvcInitDone && window.TCN && window.TCN._loggedIn) {
            _log('Already initialized this page (page flag), skipping.');
            _initialized = true;
            return true;
        }

        if (_initialized && !_isTokenExpired() && window.TCN && window.TCN._loggedIn) {
            _log('Already initialized, skipping.');
            return true;
        }

        if (_initializing) {
            _log('Init already in progress, skipping duplicate call.');
            return false;
        }

        // Guard against TCN softphone already mid-login (e.g. loaded by another
        // script on the same page before TcnService ran).
        if (window.TCN && window.TCN._loginInProgress) {
            _log('TCN softphone login already in progress — skipping duplicate init.');
            return false;
        }

        _initializing = true;
        _emit('initializing');

        try {
            let config = null;

            if (!_restoreFromCache()) {
                config = await _fetchConfig();

                if (!config || !config.configured) {
                    _log('TCN not configured for this user.');
                    return false;
                }

                _accessToken = config.access_token;
                _agentId = config.agent_id;
                _huntGroupId = config.hunt_group_id;
                _callerId = config.caller_id || '';
                _tokenFetchedAt = Date.now();
                _writeCache();
            }

            _log('Config loaded - agent_id=' + _agentId + ', hunt_group_id=' + _huntGroupId);

            if (typeof window.TCN === 'undefined') {
                await _loadSoftphone();
            }

            if (typeof window.TCN !== 'undefined' && typeof window.TCN.loginWithToken === 'function') {
                await window.TCN.loginWithToken(_accessToken, _agentId, _huntGroupId, _callerId);
            } else if (typeof window.TCN !== 'undefined' && typeof window.TCN.login === 'function') {
                await window.TCN.login();
            } else {
                throw new Error('TCN softphone not available (loginWithToken / login missing).');
            }

            _initialized = true;
            window.__tcnSvcInitDone = true;
            _emit('ready', { agent_id: _agentId, hunt_group_id: _huntGroupId });
            _log('Initialized successfully.');
            return true;
        } catch (err) {
            _clearCache();
            _initialized = false;
            _log('Init failed: ' + err.message);
            _emit('error', { message: err.message });
            return false;
        } finally {
            _initializing = false;
        }
    }

   async function call(phone, leadId) {

    if (!phone) {
        _log('call() - no phone number provided.');
        return;
    }

    // ✅ Wait if boot init is already in progress (prevents race condition where
    // the user clicks "Call Now" before the automatic init() triggered on page
    // load has finished — without this wait, init() would return false immediately
    // because _initializing is true, causing call_failed to be emitted silently).
    if (_initializing) {
        _log('Init in progress — waiting for completion before calling...');
        let _waited = 0;
        while (_initializing && _waited < 15000) {
            await new Promise(function (r) { setTimeout(r, 200); });
            _waited += 200;
        }
        if (_initializing) {
            _log('Init did not complete within 15s — aborting call.');
            _emit('call_failed', { phone: phone, reason: 'init_timeout' });
            return;
        }
        _log('Init completed — proceeding with call.');
    }

    // ✅ Step 1: Ensure initialized (ONLY if not initialized)
    if (!_initialized || !window.TCN || !window.TCN._loggedIn) {
        _log('Not initialized - initializing...');
        const ok = await init();
        if (!ok) {
            _emit('call_failed', { phone: phone, reason: 'not_initialized' });
            return;
        }
    }

    // ✅ Step 2: Prevent re-init during active call
    function isCallActive() {
        return window.TCN && window.TCN._callActive;
    }

    if (_isTokenExpired()) {
        if (isCallActive()) {
            _log('Token expired but call is active → skipping re-init');
        } else {
            _log('Token expired → safe to re-init');
            const ok = await init();
            if (!ok) {
                _emit('call_failed', { phone: phone, reason: 'token_refresh_failed' });
                return;
            }
        }
    }

    // ✅ Continue call normally
    _log('Starting call -> ' + phone);
    _emit('calling', { phone: phone });

    try {
        await window.TCN.startCall(phone, leadId || null);
    } catch (err) {
        _log('Call error: ' + err.message);
        _emit('call_failed', { phone: phone, reason: err.message });
        throw err;
    }
}

    function logout() {
        if (typeof window.TCN !== 'undefined' && typeof window.TCN.logout === 'function') {
            window.TCN.logout();
        }

        _initialized = false;
        _accessToken = null;
        _agentId = null;
        _huntGroupId = null;
        _tokenFetchedAt = null;
        _clearCache();
        window.__tcnSvcInitDone = false;
        _emit('logged_out');
        _log('Logged out.');
    }

    function isReady() {
        return _initialized && !_isTokenExpired();
    }

    const api = {
        init: init,
        call: call,
        logout: logout,
        isReady: isReady,
    };

    window.__tcnServiceSingleton = api;
    return api;
}());
