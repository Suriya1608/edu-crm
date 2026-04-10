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
    // Security hardening:
    // keep bootstrap credentials in memory only; do not persist tokens in
    // localStorage/sessionStorage.
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
        return null;
    }

    function _writeCache() {
        // Intentionally no-op: never persist tokens in browser storage.
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
        // Intentionally disabled for security hardening.
        return false;
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
        // If window.TCN is already logged in (same tab, e.g. loaded by the main layout),
        // delegate to it directly instead of creating a competing session.
        if (window.TCN && window.TCN._loggedIn) {
            _log('window.TCN already active â€” delegating to existing session (no re-init).');
            _initialized = true;
            _emit('ready', {});
            return true;
        }

        // Detect cross-tab lock: if another tab holds the TCN session,
        // refuse to start a duplicate session here.
        try {
            var existingLock = localStorage.getItem('tcn_active_tab_lock');
            if (existingLock) {
                var lockMsg = 'TCN is already active in another browser tab. Close that tab first.';
                _log(lockMsg);
                _emit('error', { message: lockMsg });
                return false;
            }
        } catch (_) { /* localStorage unavailable â€” proceed */ }

        if (_initialized && !_isTokenExpired() && window.TCN && window.TCN._loggedIn) {
            _log('Already initialized, skipping.');
            return true;
        }

        if (_initializing) {
            _log('Init already in progress, skipping duplicate call.');
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

    // âœ… Step 1: Ensure initialized (ONLY if not initialized)
    if (!_initialized || !window.TCN || !window.TCN._loggedIn) {
        _log('Not initialized - initializing...');
        const ok = await init();
        if (!ok) {
            _emit('call_failed', { phone: phone, reason: 'not_initialized' });
            return;
        }
    }

    // âœ… Step 2: Prevent re-init during active call
    function isCallActive() {
        return window.TCN && window.TCN._callActive;
    }

    if (_isTokenExpired()) {
        if (isCallActive()) {
            _log('Token expired but call is active â†’ skipping re-init');
        } else {
            _log('Token expired â†’ safe to re-init');
            const ok = await init();
            if (!ok) {
                _emit('call_failed', { phone: phone, reason: 'token_refresh_failed' });
                return;
            }
        }
    }

    // âœ… Continue call normally
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

