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
    const CACHE_KEY = 'tcn_service_bootstrap_v1';

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
            const raw = sessionStorage.getItem(CACHE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (_) {
            return null;
        }
    }

    function _writeCache() {
        try {
            sessionStorage.setItem(CACHE_KEY, JSON.stringify({
                access_token: _accessToken,
                agent_id: _agentId,
                hunt_group_id: _huntGroupId,
                caller_id: _callerId,
                token_fetched_at: _tokenFetchedAt,
            }));
        } catch (_) {}
    }

    function _clearCache() {
        try {
            sessionStorage.removeItem(CACHE_KEY);
        } catch (_) {}
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

        if (!_initialized || _isTokenExpired() || !window.TCN || !window.TCN._loggedIn) {
            _log('Token expired or not initialized - re-initializing before call.');
            const ok = await init();
            if (!ok) {
                _emit('call_failed', { phone: phone, reason: 'not_initialized' });
                return;
            }
        }

        if (typeof window.TCN === 'undefined') {
            _emit('call_failed', { phone: phone, reason: 'softphone_missing' });
            return;
        }

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
