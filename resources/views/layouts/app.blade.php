<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="call-provider" content="tcn">
    <meta name="user-role" content="{{ auth()->user()->role ?? '' }}">

    {{-- Dynamic Title --}}
    <title>{{ $globalSettings['site_name'] ?? 'Admission CRM' }}</title>
    {{-- Dynamic Favicon --}}
    @php
        $favicon = \App\Models\Setting::get('site_favicon');
    @endphp

    @if ($favicon)
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $favicon) }}">
    @else
        <link rel="icon" type="image/png" href="{{ asset('images/default-favicon.png') }}">
    @endif

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link href="{{ asset('css/style.css') }}" rel="stylesheet">

    {{-- Global 419 handler: intercept all fetch() calls and redirect to login on session expiry --}}
    <script>
    (function () {
        var _origFetch = window.fetch;
        window.fetch = function (input, init) {
            init = Object.assign({}, init);
            // Ensure the server can detect AJAX requests (enables JSON 419 response)
            init.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, init.headers);
            return _origFetch.call(window, input, init).then(function (response) {
                if (response.status === 419) {
                    window.location.href = @json(route('login'));
                }
                return response;
            });
        };
    })();
    </script>

    @vite(['resources/js/app.js'])
</head>

<body>

    {{-- Sidebar backdrop (mobile/tablet) --}}
    <div id="sidebarBackdrop" onclick="closeSidebar()"></div>

    @include('layouts.sidebar')
    <div class="main-content" id="mainContent">
        @include('layouts.header')

        <div class="dashboard-content">

            {{-- Inline error banner (kept for validation errors) --}}
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>

        {{-- Site Footer --}}
        {{-- <div class="site-footer-bar">
            <div class="site-footer-top">
                <span class="site-footer-brand">
                    <span class="material-icons">school</span>
                    {{ \App\Models\Setting::get('site_name', 'Admission CRM') }}
                </span>
                <div class="site-footer-divider"></div>
                <span>&copy; {{ date('Y') }} All rights reserved.</span>
            </div>
            <div class="site-footer-bottom">
                <a href="{{ url('/privacy-policy') }}" target="_blank">Privacy Policy</a>
                <span class="site-footer-dot">&bull;</span>
                <a href="{{ url('/terms-of-service') }}" target="_blank">Terms of Service</a>
            </div>
        </div> --}}


    {{-- Documents Quick Access Modal --}}
    @if(auth()->check() && auth()->user()->role !== 'admin')
    <div class="modal fade" id="docsModal" tabindex="-1" aria-labelledby="docsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom:1px solid #e2e8f0;">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="docsModalLabel">
                        <span class="material-icons" style="color:#137fec;">folder_open</span>
                        Documents
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="docsModalBody" style="min-height:160px;">
                    <div class="text-center text-muted py-4" id="docsLoadingState">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading documents...
                    </div>
                    <div id="docsListContainer" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const modal = document.getElementById('docsModal');
        if (!modal) return;
        const listUrl = @json(route('documents.list'));
        let loaded = false;

        modal.addEventListener('show.bs.modal', async function () {
            if (loaded) return;
            try {
                const res = await fetch(listUrl, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const container = document.getElementById('docsListContainer');
                const loading   = document.getElementById('docsLoadingState');
                if (!data.ok || !data.documents.length) {
                    loading.innerHTML = '<span class="material-icons d-block mb-1" style="font-size:32px;color:#cbd5e1;">folder_open</span>No documents available.';
                    return;
                }
                const rows = data.documents.map(function(d) {
                    return '<div class="d-flex align-items-center justify-content-between py-2 border-bottom gap-3">' +
                        '<div class="d-flex align-items-center gap-2">' +
                        '<span class="material-icons" style="color:#64748b;font-size:20px;flex-shrink:0;">' + d.icon + '</span>' +
                        '<div>' +
                        '<div class="fw-semibold" style="font-size:14px;">' + d.title + '</div>' +
                        '<div class="text-muted" style="font-size:12px;">' + d.file_name + ' &middot; ' + d.file_size_formatted + ' &middot; ' + d.created_at + '</div>' +
                        '</div></div>' +
                        '<div class="d-flex gap-2" style="flex-shrink:0;">' +
                        '<a href="' + d.view_url + '" target="_blank" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">' +
                        '<span class="material-icons" style="font-size:15px;">visibility</span>View</a>' +
                        '<a href="' + d.download_url + '" class="btn btn-sm btn-primary d-flex align-items-center gap-1">' +
                        '<span class="material-icons" style="font-size:15px;">download</span>Download</a>' +
                        '</div>' +
                        '</div>';
                });
                container.innerHTML = rows.join('');
                loading.style.display = 'none';
                container.style.display = 'block';
                loaded = true;
            } catch (e) {
                document.getElementById('docsLoadingState').textContent = 'Failed to load documents.';
            }
        });
    })();
    </script>
    @endif

    </div>

    @if (auth()->check() && auth()->user()->role === 'telecaller')
        {{-- data-turbo-eval="false": run once on hard load; Turbo navigation does NOT restart this interval --}}
        <script data-turbo-eval="false">
            (function() {
                const csrfToken = @json(csrf_token());
                const heartbeatUrl = @json(route('telecaller.status.heartbeat'));

                async function post(url) {
                    try {
                        return await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({})
                        });
                    } catch (e) {
                        return null;
                    }
                }

                post(heartbeatUrl);
                setInterval(function() { post(heartbeatUrl); }, 30000);

            })();
        </script>
        <script data-turbo-eval="false">
            (function() {
                const snapshotUrl = @json(route('telecaller.notifications.snapshot'));
                const markReadUrl = @json(route('telecaller.notifications.read-all'));
                const csrfToken = @json(csrf_token());
                const soundKey = 'telecaller_notify_sound_enabled';
                const seenMissedKey = 'telecaller_seen_missed_call_ids';
                const seenFollowupKey = 'telecaller_seen_followup_ids';

                const badge = document.getElementById('teleNotifBadge');
                const missedWrap = document.getElementById('teleNotifMissedCalls');
                const followupWrap = document.getElementById('teleNotifFollowups');
                const waWrap = document.getElementById('teleNotifWhatsapp');
                const systemWrap = document.getElementById('teleNotifSystem');
                const soundToggle = document.getElementById('teleNotifSoundToggle');
                const markReadBtn = document.getElementById('teleNotifMarkRead');

                if (!badge || !missedWrap || !followupWrap || !systemWrap) {
                    return;
                }

                let previousCount = 0;

                function getSoundEnabled() {
                    const v = localStorage.getItem(soundKey);
                    return v !== '0';
                }

                function setSoundEnabled(v) {
                    localStorage.setItem(soundKey, v ? '1' : '0');
                    if (soundToggle) soundToggle.checked = !!v;
                }

                function playBeep() {
                    if (!getSoundEnabled()) return;
                    try {
                        const audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                        const oscillator = audioCtx.createOscillator();
                        const gainNode = audioCtx.createGain();

                        oscillator.type = 'sine';
                        oscillator.frequency.setValueAtTime(880, audioCtx.currentTime);
                        gainNode.gain.setValueAtTime(0.001, audioCtx.currentTime);
                        gainNode.gain.exponentialRampToValueAtTime(0.2, audioCtx.currentTime + 0.01);
                        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);

                        oscillator.connect(gainNode);
                        gainNode.connect(audioCtx.destination);
                        oscillator.start();
                        oscillator.stop(audioCtx.currentTime + 0.22);
                    } catch (e) {}
                }

                function renderList(items, renderer, emptyText) {
                    if (!items || !items.length) {
                        return `<div class="small text-muted">${emptyText}</div>`;
                    }
                    return items.map(renderer).join('');
                }

                function getSeenIds(key) {
                    try {
                        const raw = localStorage.getItem(key);
                        const parsed = raw ? JSON.parse(raw) : [];
                        return Array.isArray(parsed) ? parsed.map(Number) : [];
                    } catch (e) {
                        return [];
                    }
                }

                function setSeenIds(key, ids) {
                    localStorage.setItem(key, JSON.stringify(Array.from(new Set(ids.map(Number)))));
                }

                function updateBadge(count) {
                    if (count > 0) {
                        badge.style.display = 'inline-block';
                        badge.textContent = count > 99 ? '99+' : String(count);
                    } else {
                        badge.style.display = 'none';
                    }
                }

                async function fetchNotifications() {
                    try {
                        const res = await fetch(snapshotUrl, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        if (!data || !data.ok) return;

                        const seenMissed = getSeenIds(seenMissedKey);
                        const seenFollowups = getSeenIds(seenFollowupKey);

                        const rawMissed = Array.isArray(data.missed_calls) ? data.missed_calls : [];
                        const rawFollowups = Array.isArray(data.followup_reminders) ? data.followup_reminders : [];
                        const rawWhatsapp = Array.isArray(data.whatsapp_notifications) ? data.whatsapp_notifications : [];
                        const rawSystem = Array.isArray(data.system_notifications) ? data.system_notifications : [];

                        const missedCalls = rawMissed.filter(item => !seenMissed.includes(Number(item.id)));
                        const followupReminders = rawFollowups.filter(item => !seenFollowups.includes(Number(item.id)));
                        const whatsappNotifications = rawWhatsapp;
                        const systemNotifications = rawSystem;

                        const count = missedCalls.length + followupReminders.length + whatsappNotifications.length + systemNotifications.length;
                        if (count > previousCount) {
                            playBeep();
                        }
                        previousCount = count;
                        updateBadge(count);

                        missedWrap.innerHTML = renderList(
                            missedCalls,
                            (item) => {
                                const link = item.lead_url ?
                                    `<a href="${item.lead_url}" class="small fw-semibold text-decoration-none">Open</a>` :
                                    '';
                                return `<div class="py-1 border-bottom">
                                    <div class="fw-semibold">${item.lead_name}</div>
                                    <div class="text-muted">${item.lead_code} | ${item.phone || '-'} ${item.time ? '| ' + item.time : ''}</div>
                                    ${link}
                                </div>`;
                            },
                            'No missed calls.'
                        );

                        followupWrap.innerHTML = renderList(
                            followupReminders,
                            (item) => `<div class="py-1 border-bottom">
                                <div class="fw-semibold">${item.lead_name}</div>
                                <div class="text-muted">${item.lead_code} | ${item.next_followup || '-'}</div>
                                <span class="badge ${item.type === 'overdue' ? 'bg-danger' : 'bg-warning text-dark'} mt-1">${item.type}</span>
                            </div>`,
                            'No reminders.'
                        );

                        if (waWrap) {
                            waWrap.innerHTML = renderList(
                                whatsappNotifications,
                                (item) => `<div class="py-1 border-bottom">
                                    <a href="${item.link || '#'}" class="fw-semibold text-decoration-none d-block">${item.title || 'WhatsApp'}</a>
                                    <div class="text-muted">${item.message || ''}</div>
                                    <div class="text-muted" style="font-size:11px;">${item.time || ''}</div>
                                </div>`,
                                'No WhatsApp messages.'
                            );
                        }

                        systemWrap.innerHTML = renderList(
                            systemNotifications,
                            (item) => `<div class="py-1 border-bottom">
                                <div>${item.message}</div>
                                <div class="text-muted">${item.time || ''}</div>
                            </div>`,
                            'No system notifications.'
                        );
                    } catch (e) {}
                }

                soundToggle?.addEventListener('change', function() {
                    setSoundEnabled(!!this.checked);
                });

                markReadBtn?.addEventListener('click', async function() {
                    try {
                        const res = await fetch(snapshotUrl, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const snap = await res.json();
                        const missedIds = (snap.missed_calls || []).map(item => Number(item.id)).filter(Boolean);
                        const followupIds = (snap.followup_reminders || []).map(item => Number(item.id)).filter(Boolean);
                        setSeenIds(seenMissedKey, [...getSeenIds(seenMissedKey), ...missedIds]);
                        setSeenIds(seenFollowupKey, [...getSeenIds(seenFollowupKey), ...followupIds]);

                        await fetch(markReadUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({})
                        });
                        await fetchNotifications();
                    } catch (e) {}
                });

                setSoundEnabled(getSoundEnabled());
                fetchNotifications();
                setInterval(fetchNotifications, 60000);

                // Immediately refresh when a missed call occurs (fired by global-call.js)
                window.addEventListener('gc:missedCall', function () {
                    fetchNotifications();
                });
            })();
        </script>
    @endif


    {{--
        TCN Softphone — persistent iframe widget (bottom-right corner).
        NO role check here — the widget is always rendered when provider=tcn.
        Visibility is controlled entirely by JavaScript (postMessage events from
        the iframe). This guarantees data-turbo-permanent can always match the
        element by id across every Turbo navigation and NEVER recreates the iframe.

        Persistence strategy:
          • data-turbo-permanent: Turbo Drive matches #tcnWidget by id and keeps
            the existing DOM node (including the live iframe) on every navigation.
          • The iframe loads once; SIP connects once; calls never drop on nav.
          • data-turbo-eval="false" on the script prevents duplicate event listeners.
    --}}
    @if(\App\Models\Setting::get('primary_call_provider') === 'tcn' && auth()->user()->role !== 'admin')
    <div id="tcnWidget" data-turbo-permanent>
        <iframe id="tcnSoftphoneFrame"
            src="/softphone"
            allow="microphone"
            style="position:fixed;bottom:80px;right:20px;width:300px;height:480px;
                   border:none;z-index:1065;border-radius:14px;
                   box-shadow:0 8px 32px rgba(0,0,0,.20);display:none;
                   transition:height .2s,width .2s;">
        </iframe>
        {{--
            Toggle button — always in DOM with a neutral gray color.
            JS turns it green on TCN_READY, shows/hides the iframe on click.
            Using inline display:flex (not Bootstrap d-flex) so JS display:none works.
        --}}
        <button id="tcnToggleBtn" title="Toggle Softphone"
            style="position:fixed;bottom:20px;right:20px;z-index:1066;
                   width:52px;height:52px;border-radius:50%;border:none;cursor:pointer;
                   background:#64748b;color:#fff;display:flex;
                   align-items:center;justify-content:center;
                   box-shadow:0 4px 20px rgba(0,0,0,.22);transition:background .25s;">
            <span class="material-icons" style="font-size:24px;pointer-events:none;" id="tcnToggleIco">phone</span>
        </button>
    </div>
    {{--
        data-turbo-eval="false" — runs ONCE on the first hard page load.
        Turbo navigations do NOT re-execute it, so event listeners are never
        duplicated. DOM refs are captured immediately (elements are above this script).
    --}}
    <script data-turbo-eval="false">
    (function () {
        var _frame    = document.getElementById('tcnSoftphoneFrame');
        var _btn      = document.getElementById('tcnToggleBtn');
        var _ico      = document.getElementById('tcnToggleIco');
        var _visible  = false;
        var _sipReady = false;   // true once TCN_READY received this tab lifetime

        // ── Header button helpers — always look up fresh DOM refs so they
        //    work after Turbo navigation replaces the header HTML. ────────
        function _rdyUpdate(active) {
            var btn = document.getElementById('tcnReadyBtn');
            var ico = document.getElementById('tcnReadyIco');
            var lbl = document.getElementById('tcnReadyLabel');
            if (!btn) return;
            if (active) {
                btn.style.background = '#10b981';
                if (ico) ico.textContent = 'phone';
                if (lbl) lbl.textContent = 'Ready';
            } else {
                btn.style.background = '#64748b';
                if (ico) ico.textContent = 'phone_disabled';
                if (lbl) lbl.textContent = 'Not Ready';
            }
        }

        function _rdyConnecting() {
            var btn = document.getElementById('tcnReadyBtn');
            var ico = document.getElementById('tcnReadyIco');
            var lbl = document.getElementById('tcnReadyLabel');
            if (!btn) return;
            btn.style.background = '#f59e0b';
            if (ico) ico.textContent = 'hourglass_empty';
            if (lbl) lbl.textContent = 'Connecting\u2026';
        }

        // ── Set initial header button state on hard page load ─────────────
        try {
            if (localStorage.getItem('tcn_sip_active') === '1') { _rdyConnecting(); }
        } catch (_) {}

        // ── Restore header button state after every Turbo navigation ──────
        // (also fires on the first hard load, after DOMContentLoaded)
        document.addEventListener('turbo:load', function () {
            if (_sipReady) { _rdyUpdate(true); return; }
            try {
                if (localStorage.getItem('tcn_sip_active') === '1') { _rdyConnecting(); }
            } catch (_) {}
        });

        // ── show / hide iframe ────────────────────────────────────────────
        function show() {
            if (!_frame) return;
            _frame.style.display      = 'block';
            _frame.style.bottom       = '80px';
            _frame.style.height       = '480px';
            _frame.style.width        = '300px';
            _frame.style.borderRadius = '14px';
            _visible = true;
            if (_ico) _ico.textContent = 'close';
        }

        function hide() {
            if (!_frame) return;
            _frame.style.display = 'none';
            _visible = false;
            if (_ico) _ico.textContent = 'phone';
        }

        // ── Header Start / Stop button (event delegation — survives Turbo nav) ──
        // Using capture phase so it fires before any other click handlers.
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#tcnReadyBtn')) return;
            var lbl = document.getElementById('tcnReadyLabel');
            var isReady = lbl && lbl.textContent.trim() === 'Ready';
            if (isReady) {
                // STOP — send silent logout (no browser confirm needed; user just clicked Stop)
                if (window.GC && typeof window.GC.disableCallingMode === 'function') {
                    window.GC.disableCallingMode();
                }
                _sipReady = false;
                _rdyUpdate(false);
                hide();
            } else {
                // START — persist flag + boot SIP in iframe
                _rdyConnecting();
                if (window.GC && typeof window.GC.enableCallingMode === 'function') {
                    window.GC.enableCallingMode();
                }
                show();
            }
        }, true);

        // ── Floating toggle button — show/hide iframe ONLY (no SIP side-effects) ──
        if (_btn) {
            _btn.addEventListener('click', function () {
                if (_visible) { hide(); } else { show(); }
            });
        }

        // ── Forward [data-phone] attribute clicks to the iframe ───────────
        document.addEventListener('click', function (e) {
            var el = e.target.closest('[data-phone]');
            if (!el || !_frame) return;
            var phone = el.getAttribute('data-phone');
            if (!phone) return;
            _frame.contentWindow.postMessage({ type: 'SET_PHONE', phone: phone }, '*');
            if (!_visible) show();
        }, true);

        // ── Messages from softphone iframe ────────────────────────────────
        // Registered once; persists across Turbo navigations for the tab lifetime.
        window.addEventListener('message', function (ev) {
            var d = ev.data;
            if (!d || typeof d !== 'object') return;

            switch (d.type) {
                case 'TCN_READY':
                    _sipReady = true;
                    if (_btn) { _btn.style.background = '#10b981'; _btn.style.animation = ''; }
                    _rdyUpdate(true);
                    break;

                case 'TCN_INCOMING_CALL':
                    // Show iframe so the incoming banner is visible
                    if (!_visible) show();
                    // Pulse the toggle button green to draw attention
                    if (_btn) { _btn.style.background = '#10b981'; _btn.style.animation = 'tcnBtnPulse .6s ease-in-out infinite'; }
                    break;

                case 'TCN_INCOMING_REJECTED':
                    if (_btn) { _btn.style.animation = ''; }
                    break;

                case 'TCN_CALL_STARTED':
                    if (_btn) { _btn.style.background = '#6366f1'; _btn.style.animation = 'tcnBtnPulse 1s ease-in-out infinite'; }
                    if (!_visible) show();
                    break;

                case 'TCN_CALL_ANSWERED':
                    if (_btn) { _btn.style.background = '#ef4444'; _btn.style.animation = ''; }
                    break;

                case 'TCN_CALL_ENDED':
                    if (_btn) { _btn.style.background = '#10b981'; _btn.style.animation = ''; }
                    break;

                case 'TCN_LOGGED_OUT':
                    _sipReady = false;
                    if (_btn) { _btn.style.background = '#64748b'; _btn.style.animation = ''; }
                    _rdyUpdate(false);
                    break;

                case 'TCN_SIP_DROPPED':
                    // Reconnect is in-flight — show amber on both buttons
                    _sipReady = false;
                    if (_btn) { _btn.style.background = '#f59e0b'; _btn.style.animation = ''; }
                    _rdyConnecting();
                    break;

                case 'SP_MINIMIZE':
                    if (_frame) { _frame.style.height = '44px'; _frame.style.width = '170px'; _frame.style.borderRadius = '22px'; }
                    break;

                case 'SP_EXPAND':
                    if (_frame) { _frame.style.height = '480px'; _frame.style.width = '300px'; _frame.style.borderRadius = '14px'; }
                    break;
            }
        });
    })();
    </script>
    <style>@keyframes tcnBtnPulse{0%,100%{opacity:1}50%{opacity:.55}}</style>
    @endif

    @if (auth()->check() && auth()->user()->role === 'telecaller')
        {{--
            data-turbo-eval="false" prevents Turbo Drive from re-evaluating this
            script on every navigation — avoids duplicate GC instances and double
            /call/end requests for a single call.
        --}}
        <script src="{{ asset('js/global-call.js?v=4') }}" data-turbo-eval="false"></script>

        {{-- GC lifecycle helpers — runs once on first hard load, persists via data-turbo-eval --}}
        <script data-turbo-eval="false">
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var metaProvider = document.querySelector('meta[name="call-provider"]');
                if (metaProvider && metaProvider.getAttribute('content') === 'tcn') {
                    if (window.GC && typeof window.GC.initDevice === 'function') {
                        window.GC.initDevice();
                    }
                }
            });

            document.addEventListener('turbo:load', function () {
                var m = document.querySelector('meta[name="csrf-token"]');
                if (m && window.GC) {
                    window.GC._csrf = m.getAttribute('content');
                }
            });
        })();
        </script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function openSidebar() {
            const sidebar  = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar)  sidebar.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
        }

        function closeSidebar() {
            const sidebar  = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar)  sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        // Close sidebar on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });
    </script>

    @stack('scripts')

    {{-- Chart.js global defaults — applied after page scripts load Chart.js --}}
    <script>
        function _applyChartDefaults() {
            if (typeof Chart !== 'undefined') {
                Chart.defaults.font.family    = "'Plus Jakarta Sans', sans-serif";
                Chart.defaults.font.size      = 12;
                Chart.defaults.color          = '#64748b';
                Chart.defaults.plugins.legend.labels.usePointStyle = true;
                Chart.defaults.plugins.legend.labels.padding        = 20;
                Chart.defaults.plugins.tooltip.backgroundColor      = '#0f172a';
                Chart.defaults.plugins.tooltip.titleColor           = '#f8fafc';
                Chart.defaults.plugins.tooltip.bodyColor            = '#cbd5e1';
                Chart.defaults.plugins.tooltip.padding              = 10;
                Chart.defaults.plugins.tooltip.cornerRadius         = 8;
                Chart.defaults.plugins.tooltip.displayColors        = true;
                Chart.defaults.scale.grid.color                     = 'rgba(0,0,0,.04)';
                Chart.defaults.scale.grid.drawBorder                = false;
            }
        }
        document.addEventListener('DOMContentLoaded', _applyChartDefaults);
        document.addEventListener('turbo:load',       _applyChartDefaults);
    </script>

    {{-- Global Toast Container --}}
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1090;">
        @if(session('success'))
            <div id="flashToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-semibold">{{ session('success') }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div id="flashToastError" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-semibold">{{ session('error') }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
    </div>

    <script>
        // Show Bootstrap flash toasts on both hard page loads AND Turbo navigations.
        function _showFlashToasts() {
            ['flashToast', 'flashToastError'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el && !el.dataset.bsToastShown) {
                    el.dataset.bsToastShown = '1';
                    new bootstrap.Toast(el, { delay: 4000 }).show();
                }
            });
        }
        document.addEventListener('DOMContentLoaded', _showFlashToasts);
        document.addEventListener('turbo:load',       _showFlashToasts);
    </script>

    {{-- WhatsApp Real-Time Inbound Notifications (5 s polling) --}}
    @auth
    <div id="waToastStack" style="position:fixed;top:76px;right:20px;z-index:9999;width:320px;display:flex;flex-direction:column;gap:8px;pointer-events:none;"></div>
    <script data-turbo-eval="false">
    (function () {
        @if(auth()->user()->role === 'telecaller')
        const pollUrl = @json(route('telecaller.whatsapp.inbox-poll'));
        @elseif(auth()->user()->role === 'manager')
        const pollUrl = @json(route('manager.whatsapp.inbox-poll'));
        @else
        return; // admin or other roles — no WA toasts
        @endif

        const LS_KEY = 'wa_notif_ts_{{ auth()->id() }}';
        let   lastTs   = localStorage.getItem(LS_KEY) || null;
        let   audioCtx = null;
        const shownIds = new Set();

        function playWaSound() {
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                [[1100, 0], [880, 0.18]].forEach(function(pair) {
                    const freq = pair[0], delay = pair[1];
                    const osc  = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.connect(gain); gain.connect(audioCtx.destination);
                    osc.type = 'sine'; osc.frequency.value = freq;
                    const t = audioCtx.currentTime + delay;
                    gain.gain.setValueAtTime(0, t);
                    gain.gain.linearRampToValueAtTime(0.35, t + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.22);
                    osc.start(t); osc.stop(t + 0.22);
                });
            } catch (e) {}
        }

        function esc(s) {
            return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function showToast(title, message, link) {
            const stack = document.getElementById('waToastStack');
            if (!stack) return;
            const div = document.createElement('div');
            div.style.cssText = 'background:#fff;border:1px solid #e2e8f0;border-left:4px solid #25D366;border-radius:10px;padding:12px 14px;box-shadow:0 4px 16px rgba(0,0,0,0.13);pointer-events:auto;animation:waSlideIn .25s ease;';
            div.innerHTML =
                '<div style="display:flex;align-items:flex-start;gap:10px;">' +
                  '<span class="material-icons" style="color:#25D366;font-size:22px;flex-shrink:0;margin-top:1px;">chat</span>' +
                  '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:700;font-size:13px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(title) + '</div>' +
                    '<div style="font-size:12px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(message) + '</div>' +
                    (link ? '<a href="' + esc(link) + '" style="display:inline-block;margin-top:6px;font-size:12px;font-weight:600;color:#137fec;text-decoration:none;">Open Chat &rarr;</a>' : '') +
                  '</div>' +
                  '<button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:20px;line-height:1;padding:0;flex-shrink:0;">&times;</button>' +
                '</div>';
            stack.appendChild(div);
            setTimeout(function() { try { div.remove(); } catch(e){} }, 9000);
        }

        function showLoginSummary(count) {
            showToast(
                count + ' unread WhatsApp message' + (count > 1 ? 's' : ''),
                'You have messages that arrived while you were away.',
                null
            );
        }

        async function poll() {
            try {
                const url = pollUrl + (lastTs ? '?after=' + encodeURIComponent(lastTs) : '');
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.ok) return;

                const items = (data.items || []).filter(function(item) {
                    return !item.id || !shownIds.has(item.id);
                });
                items.forEach(function(item) { if (item.id) shownIds.add(item.id); });

                if (items.length > 0) {
                    playWaSound();
                    if (data.is_first) {
                        showLoginSummary(items.length);
                    } else {
                        items.forEach(function(item) { showToast(item.title, item.message, item.link); });
                    }
                }

                if (data.ts) {
                    lastTs = data.ts;
                    localStorage.setItem(LS_KEY, data.ts);
                }
            } catch (e) {}
        }

        if (!document.getElementById('waToastStyle')) {
            const s = document.createElement('style');
            s.id = 'waToastStyle';
            s.textContent = '@keyframes waSlideIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}';
            document.head.appendChild(s);
        }

        poll();
        setInterval(poll, 30000);
    })();
    </script>
    @endauth

    @auth
    {{-- Idle Session Timeout (15 minutes) --}}
    <div class="modal fade" id="idleWarningModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border-radius:12px; border:none; box-shadow:0 8px 32px rgba(0,0,0,.15);">
                <div class="modal-body text-center p-4">
                    <span class="material-icons mb-2" style="font-size:40px; color:#f59e0b;">timer</span>
                    <h6 class="fw-bold mb-1">Session Expiring Soon</h6>
                    <p class="text-muted small mb-3">Your session will expire in <strong id="idleCountdown">60</strong> seconds due to inactivity.</p>
                    <button id="idleStayBtn" class="btn btn-primary btn-sm px-4">Stay Logged In</button>
                </div>
            </div>
        </div>
    </div>
    {{-- data-turbo-eval="false": idle timer runs once; Turbo navigation resets it (counts as activity) --}}
    <script data-turbo-eval="false">
    (function () {
        const IDLE_TIMEOUT    = 15 * 60 * 1000; // 15 minutes
        const WARN_BEFORE     = 60 * 1000;       // show warning 60s before timeout
        const IDLE_LOGOUT_URL = @json(route('idle.logout'));

        let idleTimer, warnTimer, countdownInterval;
        let warningShown = false;
        let modal, modalInstance;

        function resetTimers() {
            clearTimeout(idleTimer);
            clearTimeout(warnTimer);
            if (warningShown) hideWarning();
            warnTimer = setTimeout(showWarning, IDLE_TIMEOUT - WARN_BEFORE);
            idleTimer = setTimeout(doLogout,    IDLE_TIMEOUT);
        }

        function showWarning() {
            warningShown = true;
            let secs = 60;
            const cdEl = document.getElementById('idleCountdown');
            if (cdEl) cdEl.textContent = secs;
            clearInterval(countdownInterval);
            countdownInterval = setInterval(function () {
                secs--;
                const el = document.getElementById('idleCountdown');
                if (el) el.textContent = secs;
            }, 1000);
            if (!modal) {
                modal = document.getElementById('idleWarningModal');
                if (modal) modalInstance = new bootstrap.Modal(modal);
            }
            if (modalInstance) modalInstance.show();
        }

        function hideWarning() {
            warningShown = false;
            clearInterval(countdownInterval);
            if (modalInstance) modalInstance.hide();
        }

        function doLogout() {
            clearInterval(countdownInterval);
            window.location.href = IDLE_LOGOUT_URL;
        }

        // Any user interaction resets the idle timer
        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
            document.addEventListener(evt, resetTimers, { passive: true });
        });

        // Turbo navigation counts as activity — always reset idle timer on page change
        document.addEventListener('turbo:load', resetTimers);

        // Stay logged in button — bind once; button is inside idleWarningModal which
        // is not a permanent element, so re-bind after each Turbo page swap.
        function _bindStayBtn() {
            var btn = document.getElementById('idleStayBtn');
            if (btn && !btn.dataset.idleBound) {
                btn.dataset.idleBound = '1';
                btn.addEventListener('click', resetTimers);
            }
        }
        document.addEventListener('DOMContentLoaded', _bindStayBtn);
        document.addEventListener('turbo:load',       _bindStayBtn);

        // Start timers immediately
        resetTimers();
    })();
    </script>
    @endauth
</body>

</html>
