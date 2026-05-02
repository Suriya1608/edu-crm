<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="call-provider" content="tcn">
    <meta name="user-role" content="{{ auth()->user()->role ?? '' }}">

    @php
        $siteName    = \App\Models\Setting::get('site_name', 'Admission CRM');
        $siteFavicon = \App\Models\Setting::get('site_favicon');
    @endphp

    @inertiaHead

    {{-- Tell Turbo Drive to do a full page reload when navigating here from
         a Turbo-enabled page (e.g. the login page). Without this, Turbo's
         body-swap runs before the @inertia div exists, so createInertiaApp
         finds null and React never mounts — causing a blank dashboard. --}}
    <meta name="turbo-visit-control" content="reload">

    @if ($siteFavicon)
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $siteFavicon) }}">
    @else
        <link rel="icon" type="image/png" href="{{ asset('images/default-favicon.png') }}">
    @endif

    {{-- Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    {{-- App styles --}}
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">

    {{-- WhatsApp chat widget styles (used by Telecaller/Leads/Show React page) --}}
    @include('layouts.whatsappchat')

    {{--
        Inertia SPA entry point.
        This script tag renders ONCE for the entire tab lifetime.
        React handles all navigation — no page reloads after this point.
    --}}
    @vite(['resources/js/inertia-app.jsx'])
</head>

<body>

    @include('layouts.sidebar')

    <div class="main-content" id="mainContent">

        @include('layouts.header')

        <div class="dashboard-content">
            {{-- React page components render here via Inertia --}}
            @inertia
        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────────────
         TCN Softphone — renders ONCE, never reloads.
         In a true SPA there is no page navigation that could destroy this element.
         No data-turbo-permanent or any Turbo workaround is needed.
         ───────────────────────────────────────────────────────────────────── --}}
    @if (\App\Models\Setting::get('primary_call_provider') === 'tcn' && auth()->user()->role !== 'admin')
    <div id="tcnWidget">
        <iframe id="tcnSoftphoneFrame"
            src="/softphone"
            allow="microphone"
            style="position:fixed;bottom:80px;right:20px;width:300px;height:480px;
                   border:none;z-index:1065;border-radius:14px;
                   box-shadow:0 8px 32px rgba(0,0,0,.20);display:none;
                   transition:height .2s,width .2s;">
        </iframe>

        <button id="tcnToggleBtn" title="Toggle Softphone"
            style="position:fixed;bottom:20px;right:20px;z-index:1066;
                   width:52px;height:52px;border-radius:50%;border:none;cursor:pointer;
                   background:#64748b;color:#fff;display:flex;
                   align-items:center;justify-content:center;
                   box-shadow:0 4px 20px rgba(0,0,0,.22);transition:background .25s;">
            <span class="material-icons" style="font-size:24px;pointer-events:none;" id="tcnToggleIco">phone</span>
        </button>
    </div>

    <script>
    (function () {
        var _frame    = document.getElementById('tcnSoftphoneFrame');
        var _btn      = document.getElementById('tcnToggleBtn');
        var _ico      = document.getElementById('tcnToggleIco');
        var _visible  = false;
        var _sipReady = false;

        // ── Header button helpers ──────────────────────────────────────────
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
            if (lbl) lbl.textContent = 'Connecting…';
        }

        // Restore header button state on first load
        try {
            if (localStorage.getItem('tcn_sip_active') === '1') { _rdyConnecting(); }
        } catch (_) {}

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

        // ── Header Start / Stop button ─────────────────────────────────────
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#tcnReadyBtn')) return;
            var lbl = document.getElementById('tcnReadyLabel');
            var isReady = lbl && lbl.textContent.trim() === 'Ready';
            if (isReady) {
                if (window.GC && typeof window.GC.disableCallingMode === 'function') {
                    window.GC.disableCallingMode();
                }
                _sipReady = false;
                _rdyUpdate(false);
                hide();
            } else {
                _rdyConnecting();
                if (window.GC && typeof window.GC.enableCallingMode === 'function') {
                    window.GC.enableCallingMode();
                }
                show();
            }
        }, true);

        // ── Floating toggle button ─────────────────────────────────────────
        if (_btn) {
            _btn.addEventListener('click', function () {
                if (_visible) { hide(); } else { show(); }
            });
        }

        // ── Forward [data-phone] clicks to the iframe ──────────────────────
        document.addEventListener('click', function (e) {
            var el = e.target.closest('[data-phone]');
            if (!el || !_frame) return;
            var phone = el.getAttribute('data-phone');
            if (!phone) return;
            _frame.contentWindow.postMessage({ type: 'SET_PHONE', phone: phone }, '*');
            if (!_visible) show();
        }, true);

        // ── Messages from softphone iframe ─────────────────────────────────
        window.addEventListener('message', function (ev) {
            var d = ev.data;
            if (!d || typeof d !== 'object') return;

            switch (d.type) {
                case 'TCN_READY':
                    _sipReady = true;
                    if (_btn) { _btn.style.background = '#10b981'; _btn.style.animation = ''; }
                    _rdyUpdate(true);
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
        {{-- global-call.js runs once — in SPA there is no re-evaluation risk --}}
        <script src="{{ asset('js/global-call.js?v=4') }}"></script>
        <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                if (window.GC && typeof window.GC.initDevice === 'function') {
                    window.GC.initDevice();
                }
            });
        })();
        </script>

        {{-- Notification bell polling --}}
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

            if (!badge || !missedWrap || !followupWrap || !systemWrap) return;

            let previousCount = 0;

            function getSoundEnabled() { return localStorage.getItem(soundKey) !== '0'; }
            function setSoundEnabled(v) {
                localStorage.setItem(soundKey, v ? '1' : '0');
                if (soundToggle) soundToggle.checked = !!v;
            }

            function playBeep() {
                if (!getSoundEnabled()) return;
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    gain.gain.setValueAtTime(0.001, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.start(); osc.stop(ctx.currentTime + 0.22);
                } catch (e) {}
            }

            function renderList(items, renderer, emptyText) {
                if (!items || !items.length) return `<div class="small text-muted">${emptyText}</div>`;
                return items.map(renderer).join('');
            }

            function getSeenIds(key) {
                try { const r = localStorage.getItem(key); const p = r ? JSON.parse(r) : []; return Array.isArray(p) ? p.map(Number) : []; } catch (e) { return []; }
            }
            function setSeenIds(key, ids) {
                localStorage.setItem(key, JSON.stringify(Array.from(new Set(ids.map(Number)))));
            }

            function updateBadge(count) {
                if (count > 0) { badge.style.display = 'inline-block'; badge.textContent = count > 99 ? '99+' : String(count); }
                else { badge.style.display = 'none'; }
            }

            async function fetchNotifications() {
                try {
                    const res = await fetch(snapshotUrl, { headers: { 'Accept': 'application/json' } });
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

                    const count = missedCalls.length + followupReminders.length + rawWhatsapp.length + rawSystem.length;
                    if (count > previousCount) playBeep();
                    previousCount = count;
                    updateBadge(count);

                    missedWrap.innerHTML = renderList(missedCalls, (item) => {
                        const link = item.lead_url ? `<a href="${item.lead_url}" class="small fw-semibold text-decoration-none">Open</a>` : '';
                        return `<div class="py-1 border-bottom">
                            <div class="fw-semibold">${item.lead_name}</div>
                            <div class="text-muted">${item.lead_code} | ${item.phone || '-'} ${item.time ? '| ' + item.time : ''}</div>
                            ${link}</div>`;
                    }, 'No missed calls.');

                    followupWrap.innerHTML = renderList(followupReminders, (item) =>
                        `<div class="py-1 border-bottom">
                            <div class="fw-semibold">${item.lead_name}</div>
                            <div class="text-muted">${item.lead_code} | ${item.next_followup || '-'}</div>
                            <span class="badge ${item.type === 'overdue' ? 'bg-danger' : 'bg-warning text-dark'} mt-1">${item.type}</span>
                        </div>`, 'No reminders.');

                    if (waWrap) waWrap.innerHTML = renderList(rawWhatsapp, (item) =>
                        `<div class="py-1 border-bottom">
                            <a href="${item.link || '#'}" class="fw-semibold text-decoration-none d-block">${item.title || 'WhatsApp'}</a>
                            <div class="text-muted">${item.message || ''}</div>
                            <div class="text-muted" style="font-size:11px;">${item.time || ''}</div>
                        </div>`, 'No WhatsApp messages.');

                    systemWrap.innerHTML = renderList(rawSystem, (item) =>
                        `<div class="py-1 border-bottom">
                            <div>${item.message}</div>
                            <div class="text-muted">${item.time || ''}</div>
                        </div>`, 'No system notifications.');
                } catch (e) {}
            }

            soundToggle?.addEventListener('change', function() { setSoundEnabled(!!this.checked); });

            markReadBtn?.addEventListener('click', async function() {
                try {
                    const res = await fetch(snapshotUrl, { headers: { 'Accept': 'application/json' } });
                    const snap = await res.json();
                    setSeenIds(seenMissedKey, [...getSeenIds(seenMissedKey), ...(snap.missed_calls || []).map(i => Number(i.id)).filter(Boolean)]);
                    setSeenIds(seenFollowupKey, [...getSeenIds(seenFollowupKey), ...(snap.followup_reminders || []).map(i => Number(i.id)).filter(Boolean)]);
                    await fetch(markReadUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) });
                    await fetchNotifications();
                } catch (e) {}
            });

            setSoundEnabled(getSoundEnabled());
            fetchNotifications();
            setInterval(fetchNotifications, 60000);

            // Refresh immediately when a missed call occurs (fired by global-call.js after TCN_INCOMING_REJECTED)
            window.addEventListener('gc:missedCall', function () {
                fetchNotifications();
            });
        })();
        </script>
    @endif

    {{-- Documents Quick Access Modal --}}
    @if(auth()->check() && auth()->user()->role !== 'admin')
    <div class="modal fade" id="docsModal" tabindex="-1" aria-labelledby="docsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom:1px solid #e2e8f0;">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="docsModalLabel">
                        <span class="material-icons" style="color:#6366f1;">folder_open</span>
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
    @endif

    {{-- Bootstrap JS (CDN) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Documents modal loader --}}
    @if(auth()->check() && auth()->user()->role !== 'admin')
    <script>
    (function () {
        var listUrl = @json(route('documents.list'));
        var loaded  = false;

        document.addEventListener('show.bs.modal', function (e) {
            if (e.target.id !== 'docsModal') return;
            if (loaded) return;
            fetch(listUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var container = document.getElementById('docsListContainer');
                    var loading   = document.getElementById('docsLoadingState');
                    if (!data.ok || !data.documents || !data.documents.length) {
                        loading.innerHTML = '<span class="material-icons d-block mb-1" style="font-size:32px;color:#cbd5e1;">folder_open</span>No documents available.';
                        return;
                    }
                    container.innerHTML = data.documents.map(function (d) {
                        return '<div class="d-flex align-items-center justify-content-between py-2 border-bottom gap-3">' +
                            '<div class="d-flex align-items-center gap-2">' +
                            '<span class="material-icons" style="color:#64748b;font-size:20px;flex-shrink:0;">' + d.icon + '</span>' +
                            '<div><div class="fw-semibold" style="font-size:14px;">' + d.title + '</div>' +
                            '<div class="text-muted" style="font-size:12px;">' + d.file_name + ' &middot; ' + d.file_size_formatted + ' &middot; ' + d.created_at + '</div></div></div>' +
                            '<div class="d-flex gap-2" style="flex-shrink:0;">' +
                            '<a href="' + d.view_url + '" target="_blank" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"><span class="material-icons" style="font-size:15px;">visibility</span>View</a>' +
                            '<a href="' + d.download_url + '" class="btn btn-sm btn-primary d-flex align-items-center gap-1"><span class="material-icons" style="font-size:15px;">download</span>Download</a>' +
                            '</div></div>';
                    }).join('');
                    loading.style.display = 'none';
                    container.style.display = 'block';
                    loaded = true;
                })
                .catch(function () {
                    document.getElementById('docsLoadingState').textContent = 'Failed to load documents.';
                });
        });
    })();
    </script>
    @endif

    {{-- Sidebar toggle --}}
    <script>
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.toggle('show');
        }
    </script>

    {{-- The Blade-sidebar link guard lives in inertia-app.jsx (router.on('before')).
         No custom click interceptor needed here — Inertia's global handler plus
         the before-hook covers all navigation cases correctly. --}}

    {{-- Sync document.title → header <h2> so each React page can drive the header title
         via <Head title="..."/>.  A MutationObserver on <title> fires on every Inertia
         navigation; no polling, no race conditions. --}}
    <script>
    (function () {
        function syncTitle() {
            var raw = document.title || '';
            var page = raw.replace(/\s*[—–-]\s*Admission CRM\s*$/i, '').trim();
            var h2 = document.getElementById('pageHeaderTitle');
            if (h2 && page) h2.textContent = page;
        }

        // Observe <head> directly — on non-SSR Inertia there is no <title> in the
        // initial HTML, so watching a specific titleEl would bail out immediately.
        // Watching <head> catches: new <title> inserted (childList) and any text-node
        // change inside it (characterData + subtree).
        new MutationObserver(syncTitle).observe(document.head, {
            childList: true,
            subtree: true,
            characterData: true,
        });
    })();
    </script>

    {{-- Flash toasts — reads from Inertia shared props via window.__inertiaFlash injected by React --}}
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1090;" id="toastContainer"></div>

</body>
</html>
