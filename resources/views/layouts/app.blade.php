<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

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

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link href="{{ asset('css/style.css') }}" rel="stylesheet">

    @vite(['resources/js/app.js'])
</head>

<body>

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
        <div class="site-footer-bar">
            <span>© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'Admission CRM') }}</span>
            <span class="mx-2" style="opacity:.4;">·</span>
            <a href="{{ url('/privacy-policy') }}" target="_blank">Privacy Policy</a>
            <span class="mx-2" style="opacity:.4;">·</span>
            <a href="{{ url('/terms-of-service') }}" target="_blank">Terms of Service</a>
        </div>

    </div>

    @if (auth()->check() && auth()->user()->role === 'telecaller')
        <script>
            (function() {
                const csrfToken = @json(csrf_token());
                const heartbeatUrl = @json(route('telecaller.status.heartbeat'));
                const offlineUrl = @json(route('telecaller.status.offline'));
                const availabilityStorageKey = 'telecaller_availability';

                async function post(url, payload = {}) {
                    try {
                        return await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(payload)
                        });
                    } catch (e) {
                        return null;
                    }
                }

                function isAvailable() {
                    const v = localStorage.getItem(availabilityStorageKey);
                    return v !== 'offline';
                }

                if (isAvailable()) {
                    post(heartbeatUrl);
                } else {
                    post(offlineUrl);
                }

                setInterval(function() {
                    if (isAvailable()) {
                        post(heartbeatUrl);
                    }
                }, 30000);

            })();
        </script>
        <script>
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
                setInterval(fetchNotifications, 20000);
            })();
        </script>
    @endif

    @if (auth()->check() && auth()->user()->role === 'telecaller')
        {{-- Global Active Call Bar (telecaller only) --}}
        <div id="gcCallBar" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:1060; background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; padding:10px 20px; box-shadow:0 -2px 12px rgba(0,0,0,0.25); align-items:center; gap:12px;">
            <span class="material-icons" style="font-size:24px;">call</span>
            <div>
                <div style="font-size:11px; opacity:0.85; line-height:1;">Active Call</div>
                <div id="gcCallPhone" style="font-weight:700; font-size:15px; letter-spacing:0.3px; line-height:1.3;"></div>
            </div>
            <div style="margin-left:6px;">
                <div style="font-size:11px; opacity:0.85; line-height:1;">Duration</div>
                <div id="gcCallTimer" style="font-weight:700; font-size:15px; font-variant-numeric:tabular-nums; line-height:1.3;">0:00</div>
            </div>
            <div class="ms-auto">
                <button id="gcBarEndBtn" class="btn btn-light btn-sm fw-semibold text-danger" style="min-width:110px;">
                    <span class="material-icons me-1" style="font-size:16px; vertical-align:middle;">call_end</span>End Call
                </button>
            </div>
        </div>

        {{-- Navigation Warning Modal (shown when user clicks a link during an active call) --}}
        <div class="modal fade" id="gcNavWarningModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" style="background:#dc2626; color:#fff;">
                        <h5 class="modal-title d-flex align-items-center gap-2">
                            <span class="material-icons">warning</span> Call in Progress
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">A call is currently in progress.</p>
                        <p class="text-muted small mb-0">Navigating away will <strong>end the call</strong> and save the log. Click <strong>Stay on Call</strong> to remain on this page.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Stay on Call</button>
                        <button type="button" class="btn btn-danger" id="gcNavProceedBtn">
                            <span class="material-icons me-1" style="font-size:16px; vertical-align:middle;">call_end</span>End &amp; Navigate
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script src="{{ asset('js/global-call.js') }}"></script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')

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
        document.addEventListener('DOMContentLoaded', function () {
            ['flashToast', 'flashToastError'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) {
                    new bootstrap.Toast(el, { delay: 4000 }).show();
                }
            });
        });
    </script>

    {{-- WhatsApp Real-Time Inbound Notifications (5 s polling) --}}
    @auth
    <div id="waToastStack" style="position:fixed;top:76px;right:20px;z-index:9999;width:320px;display:flex;flex-direction:column;gap:8px;pointer-events:none;"></div>
    <script>
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
                    if (data.is_first) {
                        showLoginSummary(items.length);
                    } else {
                        playWaSound();
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
        setInterval(poll, 5000);
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
    <script>
    (function () {
        const IDLE_TIMEOUT   = 15 * 60 * 1000; // 15 minutes
        const WARN_BEFORE    = 60 * 1000;        // show warning 60s before timeout
        const IDLE_LOGOUT_URL = @json(route('idle.logout'));

        let idleTimer, warnTimer, countdownInterval;
        let warningShown = false;
        let modal, modalInstance;

        function resetTimers() {
            clearTimeout(idleTimer);
            clearTimeout(warnTimer);
            if (warningShown) {
                hideWarning();
            }
            warnTimer = setTimeout(showWarning, IDLE_TIMEOUT - WARN_BEFORE);
            idleTimer = setTimeout(doLogout,    IDLE_TIMEOUT);
        }

        function showWarning() {
            warningShown = true;
            let secs = 60;
            document.getElementById('idleCountdown').textContent = secs;
            clearInterval(countdownInterval);
            countdownInterval = setInterval(function () {
                secs--;
                const el = document.getElementById('idleCountdown');
                if (el) el.textContent = secs;
            }, 1000);
            if (!modal) {
                modal = document.getElementById('idleWarningModal');
                modalInstance = new bootstrap.Modal(modal);
            }
            modalInstance.show();
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

        // Reset on any user interaction
        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
            document.addEventListener(evt, resetTimers, { passive: true });
        });

        // Stay logged in button
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('idleStayBtn');
            if (btn) btn.addEventListener('click', resetTimers);
        });

        // Start timers on page load
        resetTimers();
    })();
    </script>
    @endauth
</body>

</html>
