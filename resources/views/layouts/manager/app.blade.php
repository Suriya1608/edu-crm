<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="call-provider" content="tcn">
    <meta name="user-role" content="{{ auth()->user()->role ?? '' }}">
    <title>{{ $globalSettings['site_name'] ?? 'Admission CRM' }}</title>

    @php
        $favicon = \App\Models\Setting::get('site_favicon');
    @endphp
    @if ($favicon)
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $favicon) }}">
    @else
        <link rel="icon" type="image/png" href="{{ asset('images/default-favicon.png') }}">
    @endif

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
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

    {{-- Broadcast / Echo config (injected before Vite bundle so echo.js can read it synchronously) --}}
    @php
        $bDriver = \App\Models\Setting::get('broadcast_driver', 'null');
        $bKey    = $bDriver === 'pusher'
                    ? \App\Models\Setting::getSecure('pusher_app_key', '')
                    : \App\Models\Setting::getSecure('reverb_app_key', '');
        $bConfig = [
            'driver'  => $bDriver,
            'key'     => $bKey,
            'cluster' => \App\Models\Setting::get('pusher_app_cluster', 'mt1'),
            'reverb'  => [
                'host'   => \App\Models\Setting::get('reverb_host', '127.0.0.1'),
                'port'   => (int) \App\Models\Setting::get('reverb_port', '8080'),
                'scheme' => \App\Models\Setting::get('reverb_scheme', 'http'),
            ],
        ];
    @endphp
    @if($bDriver !== 'null')
    <script>window.__BROADCAST__ = @json($bConfig);</script>
    @endif

    @vite(['resources/js/app.js'])
</head>

<body>
    <div id="sidebarBackdrop" onclick="closeSidebar()"></div>
    @include('layouts.manager.sidebar')
    <div class="main-content" id="mainContent">
        @include('layouts.manager.header')

        <div class="dashboard-content">
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
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
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

    </div>


    {{-- Active Call Bar (shown by global-call.js during calls, hidden by default) --}}
    <div id="gcCallBar" data-turbo-permanent
        style="display:none;position:fixed;top:0;left:0;right:0;z-index:2000;
               background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;
               padding:8px 20px;align-items:center;gap:16px;box-shadow:0 2px 12px rgba(0,0,0,.25);">
        <span class="material-icons" style="font-size:20px;">call</span>
        <div style="flex:1;min-width:0;">
            <div id="gcCallStatus" style="font-size:10px;opacity:.85;text-transform:uppercase;letter-spacing:.6px;line-height:1;">Connecting…</div>
            <div id="gcCallPhone" style="font-weight:700;font-size:14px;letter-spacing:.3px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;">
            <span class="material-icons" style="font-size:16px;opacity:.8;">timer</span>
            <span id="gcCallTimer">0:00</span>
        </div>
        <div style="display:flex;gap:8px;margin-left:auto;">
            <button id="gcMuteBtn" title="Mute"
                style="background:rgba(255,255,255,.18);border:none;border-radius:7px;color:#fff;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;">
                <span class="material-icons" id="gcMuteIcon" style="font-size:18px;">mic</span>
            </button>
            <button id="gcHoldBtn" title="Hold"
                style="background:rgba(255,255,255,.18);border:none;border-radius:7px;color:#fff;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;">
                <span class="material-icons" id="gcHoldIcon" style="font-size:18px;">pause_circle</span>
            </button>
            <button id="gcEndCallBarBtn"
                style="background:rgba(0,0,0,.25);border:none;border-radius:7px;color:#fff;padding:0 12px;height:34px;cursor:pointer;font-weight:700;font-size:12px;display:flex;align-items:center;gap:5px;transition:background .15s;">
                <span class="material-icons" style="font-size:16px;">call_end</span>End
            </button>
        </div>
        <a id="gcCallLeadLink" style="display:none;color:#fff;opacity:.8;font-size:12px;" target="_blank">View Lead</a>
    </div>

    {{-- Documents Quick Access Modal --}}
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
    @if(\App\Models\Setting::get('primary_call_provider') === 'tcn')
    {{--
        Softphone iframe for managers — persistent iframe architecture.
        data-turbo-permanent on the wrapper div ensures Turbo Drive NEVER
        destroys/recreates this element during navigation — the SIP session
        and WebRTC audio survive across all page transitions.
        The iframe loads once; TcnService.init() runs once inside it.
    --}}
    <div id="tcnMgrWidget" data-turbo-permanent>
        <iframe id="tcnSoftphoneFrame"
            src="/softphone"
            allow="microphone"
            style="position:fixed;bottom:80px;right:20px;width:300px;height:480px;border:none;z-index:1065;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.20);display:none;transition:height .2s,width .2s;">
        </iframe>
        {{-- Toggle button — always visible once layout renders. Lets managers
             open the softphone even when TCN_READY hasn't fired yet. --}}
        <button id="tcnMgrToggleBtn" title="TCN Softphone"
            style="position:fixed;bottom:20px;right:20px;z-index:1066;
                   width:52px;height:52px;border-radius:50%;border:none;cursor:pointer;
                   background:#64748b;color:#fff;display:flex;
                   align-items:center;justify-content:center;
                   box-shadow:0 4px 20px rgba(0,0,0,.22);transition:background .25s;">
            <span class="material-icons" style="font-size:24px;pointer-events:none;" id="tcnMgrToggleIco">phone</span>
        </button>
    </div>
    {{--
        data-turbo-eval="false" — this script runs ONCE on the first hard page load.
        Turbo navigations do NOT re-execute it, preventing duplicate window message
        handlers from accumulating across page visits.
    --}}
    <script data-turbo-eval="false">
    (function () {
        var _frame   = null;
        var _btn     = document.getElementById('tcnMgrToggleBtn');
        var _ico     = document.getElementById('tcnMgrToggleIco');
        var _visible = false;

        function frame() {
            if (!_frame) _frame = document.getElementById('tcnSoftphoneFrame');
            return _frame;
        }

        function showFrame() {
            var f = frame(); if (!f) return;
            f.style.display = 'block'; f.style.height = '480px'; f.style.width = '300px';
            f.style.bottom = '80px'; f.style.borderRadius = '14px';
            _visible = true;
            if (_ico) _ico.textContent = 'close';
        }

        function hideFrame() {
            var f = frame(); if (!f) return;
            f.style.display = 'none';
            _visible = false;
            if (_ico) _ico.textContent = 'phone';
        }

        // Toggle button click.
        if (_btn) {
            _btn.addEventListener('click', function () {
                if (_visible) hideFrame(); else showFrame();
            });
        }

        // Forward [data-phone] clicks to the softphone iframe.
        document.addEventListener('click', function (e) {
            var el = e.target.closest('[data-phone]');
            if (!el) return;
            var f = frame(); if (!f) return;
            var phone = el.getAttribute('data-phone');
            if (phone) {
                showFrame();
                f.contentWindow.postMessage({ type: 'SET_PHONE', phone: phone }, '*');
            }
        }, true);

        // Receive status messages from the softphone iframe.
        // Runs for the lifetime of the tab; persists across Turbo navigations.
        window.addEventListener('message', function (ev) {
            var d = ev.data; if (!d || typeof d !== 'object') return;
            var f = frame(); if (!f) return;
            if (d.type === 'SP_MINIMIZE')       { f.style.height = '44px';  f.style.width = '170px'; f.style.borderRadius = '22px'; }
            else if (d.type === 'SP_EXPAND')    { f.style.height = '480px'; f.style.width = '300px'; f.style.borderRadius = '14px'; }
            else if (d.type === 'TCN_READY') {
                if (_btn) { _btn.style.background = '#10b981'; }
            }
            else if (d.type === 'TCN_CALL_STARTED' || d.type === 'TCN_CALL_ANSWERED') {
                showFrame(); f.style.bottom = '80px';
                if (_btn) { _btn.style.background = d.type === 'TCN_CALL_ANSWERED' ? '#ef4444' : '#6366f1'; }
            }
            else if (d.type === 'TCN_CALL_ENDED') {
                // Auto-close the softphone panel 3s after call ends
                if (_btn) { _btn.style.background = '#10b981'; }
                setTimeout(function () { if (_visible) hideFrame(); }, 3000);
            }
            else if (d.type === 'TCN_LOGGED_OUT') {
                if (_btn) { _btn.style.background = '#64748b'; }
            }
            else if (d.type === 'TCN_SIP_DROPPED') {
                if (_btn) { _btn.style.background = '#f59e0b'; }
            }
        });
    })();
    </script>
    <style>@keyframes tcnMgrBtnPulse{0%,100%{opacity:1}50%{opacity:.55}}</style>
    @endif

    {{-- Navigation warning modal — shown when manager clicks a link during an active call --}}
    <div class="modal fade" id="gcNavWarningModal" tabindex="-1" aria-hidden="true" data-turbo-permanent>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:#dc2626;color:#fff;">
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
                        <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;">call_end</span>End &amp; Navigate
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- AI Assistant is rendered by the React ChatWidget in inertia-app.jsx --}}
    @if(false)
    <button id="aiToggleBtn" title="AI Assistant" data-turbo-permanent
        onclick="toggleAiDrawer()"
        style="position:fixed;bottom:84px;right:20px;z-index:1070;
               width:52px;height:52px;border-radius:50%;border:none;cursor:pointer;
               background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;
               display:flex;align-items:center;justify-content:center;
               box-shadow:0 4px 20px rgba(99,102,241,.45);
               transition:transform .2s,box-shadow .2s;">
        <span class="material-icons" style="font-size:24px;pointer-events:none;" id="aiToggleIco">smart_toy</span>
    </button>

    {{-- Chat drawer panel --}}
    <div id="aiChatDrawer" data-turbo-permanent
        style="position:fixed;right:20px;top:74px;bottom:148px;width:370px;max-width:calc(100vw - 40px);
               background:#fff;border-radius:16px;border:1px solid #e2e8f0;
               box-shadow:0 8px 40px rgba(0,0,0,.18);
               display:flex;flex-direction:column;z-index:1069;overflow:hidden;
               transform:scale(.92) translateY(20px);opacity:0;pointer-events:none;
               transition:transform .22s cubic-bezier(.34,1.36,.64,1),opacity .18s ease;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;
                    background:linear-gradient(135deg,#6366f1,#8b5cf6);flex-shrink:0;">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.2);
                        display:flex;align-items:center;justify-content:center;">
                <span class="material-icons" style="color:#fff;font-size:18px;">smart_toy</span>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:14px;color:#fff;line-height:1.2;">AI Assistant</div>
                <div style="font-size:11px;color:rgba(255,255,255,.75);">Manager mode · Claude</div>
            </div>
            <button onclick="clearAiDrawer()" title="New chat"
                style="background:rgba(255,255,255,.15);border:none;border-radius:8px;color:#fff;
                       width:30px;height:30px;cursor:pointer;display:flex;align-items:center;
                       justify-content:center;margin-right:4px;">
                <span class="material-icons" style="font-size:16px;">refresh</span>
            </button>
            <button onclick="toggleAiDrawer()" title="Close"
                style="background:rgba(255,255,255,.15);border:none;border-radius:8px;color:#fff;
                       width:30px;height:30px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                <span class="material-icons" style="font-size:18px;">close</span>
            </button>
        </div>

        {{-- Quick chips --}}
        <div style="display:flex;flex-wrap:nowrap;gap:6px;padding:8px 12px;overflow-x:auto;
                    flex-shrink:0;border-bottom:1px solid #f1f5f9;">
            <button onclick="aiChip('Show unassigned leads')"
                style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:16px;
                       border:1px solid #e2e8f0;background:#fff;font-size:11.5px;color:#334155;
                       cursor:pointer;white-space:nowrap;font-family:inherit;">
                <span class="material-icons" style="font-size:13px;">person_off</span>Unassigned
            </button>
            <button onclick="aiChip('Show today\'s new leads')"
                style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:16px;
                       border:1px solid #e2e8f0;background:#fff;font-size:11.5px;color:#334155;
                       cursor:pointer;white-space:nowrap;font-family:inherit;">
                <span class="material-icons" style="font-size:13px;">today</span>Today
            </button>
            <button onclick="aiChip('Show overdue follow-ups')"
                style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:16px;
                       border:1px solid #e2e8f0;background:#fff;font-size:11.5px;color:#334155;
                       cursor:pointer;white-space:nowrap;font-family:inherit;">
                <span class="material-icons" style="font-size:13px;">schedule</span>Overdue
            </button>
            <button onclick="aiChip('Telecaller performance summary')"
                style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:16px;
                       border:1px solid #e2e8f0;background:#fff;font-size:11.5px;color:#334155;
                       cursor:pointer;white-space:nowrap;font-family:inherit;">
                <span class="material-icons" style="font-size:13px;">leaderboard</span>Performance
            </button>
            <button onclick="aiChip('Lead pipeline overview this month')"
                style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:16px;
                       border:1px solid #e2e8f0;background:#fff;font-size:11.5px;color:#334155;
                       cursor:pointer;white-space:nowrap;font-family:inherit;">
                <span class="material-icons" style="font-size:13px;">bar_chart</span>Insights
            </button>
        </div>

        {{-- Messages --}}
        <div id="aiDrawerMessages"
            style="flex:1;overflow-y:auto;padding:12px;display:flex;
                   flex-direction:column;gap:10px;background:#f8fafc;">
            <div id="aiWelcomeMsg" style="display:flex;align-items:flex-start;gap:8px;">
                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);
                            display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span class="material-icons" style="font-size:14px;color:#fff;">smart_toy</span>
                </div>
                <div style="max-width:82%;padding:9px 12px;border-radius:12px;border-top-left-radius:3px;
                            background:#fff;border:1px solid #e2e8f0;font-size:13px;line-height:1.55;color:#0f172a;">
                    <strong>Hi, {{ auth()->user()->name }}!</strong><br>
                    Ask me about leads, telecallers, or insights. Try the chips above!
                </div>
            </div>
        </div>

        {{-- Input --}}
        <div style="padding:10px;border-top:1px solid #f1f5f9;display:flex;gap:7px;align-items:flex-end;flex-shrink:0;">
            <textarea id="aiDrawerInput" rows="1" placeholder="Ask anything…"
                style="resize:none;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;
                       font-size:13px;font-family:inherit;line-height:1.4;height:38px;max-height:90px;
                       outline:none;flex:1;background:#fff;"></textarea>
            <button id="aiDrawerSend" onclick="aiDrawerSend()" title="Send"
                style="width:38px;height:38px;border-radius:10px;border:none;background:#6366f1;
                       color:#fff;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                <span class="material-icons" style="font-size:17px;">send</span>
            </button>
        </div>
    </div>

    <style>
    #aiChatDrawer.ai-open {
        transform: scale(1) translateY(0) !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }
    #aiToggleBtn:hover { transform: scale(1.1) !important; }
    .ai-dmsg-row { display:flex;align-items:flex-start;gap:8px; }
    .ai-dmsg-row.ai-u-row { flex-direction:row-reverse; }
    .ai-av { width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .ai-av-bot { background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff; }
    .ai-av-usr { background:#0f172a;color:#fff;font-size:11px;font-weight:700; }
    .ai-bbl { max-width:82%;padding:9px 12px;border-radius:12px;font-size:13px;line-height:1.55;word-break:break-word; }
    .ai-bbl-bot { background:#fff;border:1px solid #e2e8f0;border-top-left-radius:3px;color:#0f172a; }
    .ai-bbl-usr { background:#6366f1;color:#fff;border-top-right-radius:3px; }
    .ai-bbl-err { background:#fef2f2;border:1px solid #fecaca;border-top-left-radius:3px;color:#dc2626; }
    .ai-bbl-bot p { margin:0 0 6px; }
    .ai-bbl-bot p:last-child { margin-bottom:0; }
    .ai-bbl-bot ul,.ai-bbl-bot ol { margin:3px 0 6px 16px;padding:0; }
    .ai-bbl-bot li { margin-bottom:2px; }
    .ai-bbl-bot strong { color:#0f172a; }
    .ai-bbl-bot code { background:#f1f5f9;border-radius:3px;padding:1px 4px;font-size:12px;color:#6366f1; }
    .ai-bbl-bot table { border-collapse:collapse;width:100%;margin:6px 0;font-size:12px; }
    .ai-bbl-bot th { background:#f8fafc;font-weight:600; }
    .ai-bbl-bot td,.ai-bbl-bot th { border:1px solid #e2e8f0;padding:4px 8px; }
    .ai-bbl-bot h1,.ai-bbl-bot h2,.ai-bbl-bot h3 { font-size:14px;font-weight:700;margin:8px 0 3px; }
    .ai-think-dot { display:inline-block;width:6px;height:6px;background:#94a3b8;border-radius:50%;margin:0 2px;animation:aiDot 1.2s infinite ease-in-out; }
    .ai-think-dot:nth-child(2){ animation-delay:.2s; }
    .ai-think-dot:nth-child(3){ animation-delay:.4s; }
    @@keyframes aiDot { 0%,80%,100%{transform:translateY(0);opacity:.4} 40%{transform:translateY(-5px);opacity:1} }
    #aiDrawerInput:focus { border-color:#6366f1 !important;box-shadow:0 0 0 2px rgba(99,102,241,.1); }
    #aiDrawerInput:disabled { background:#f8fafc !important;color:#94a3b8; }
    #aiDrawerSend:hover { background:#4f46e5 !important; }
    #aiDrawerSend:disabled { background:#c7d2fe !important;cursor:not-allowed; }
    </style>

    <script data-turbo-eval="false">
    (function () {
        var CHAT_URL  = @json(route('manager.agent.chat'));
        var USER_INIT = @json(strtoupper(substr(auth()->user()->name, 0, 1)));
        var HIST_KEY  = 'mgr_ai_hist_{{ auth()->id() }}';

        var _open = false, _busy = false, _history = [];
        try { _history = JSON.parse(sessionStorage.getItem(HIST_KEY) || '[]'); } catch(e){}

        // Restore previous session messages
        if (_history.length) {
            var wm = document.getElementById('aiWelcomeMsg');
            if (wm) wm.style.display = 'none';
            _history.forEach(function(h) {
                if (h.role === 'user') _addUser(h.content);
                else if (h.role === 'ai') _addBot(h.content, false);
            });
        }

        function _save() { try { sessionStorage.setItem(HIST_KEY, JSON.stringify(_history)); } catch(e){} }
        function _scroll() { var el=document.getElementById('aiDrawerMessages'); if(el) el.scrollTop=el.scrollHeight; }
        function _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        function _av(cls, icon) {
            return '<div class="ai-av '+cls+'">' + (icon ? '<span class="material-icons" style="font-size:14px;">'+icon+'</span>' : _esc(USER_INIT)) + '</div>';
        }

        function _addUser(txt) {
            var box=document.getElementById('aiDrawerMessages');
            var row=document.createElement('div');
            row.className='ai-dmsg-row ai-u-row';
            row.innerHTML=_av('ai-av-usr',null)+'<div class="ai-bbl ai-bbl-usr">'+_esc(txt)+'</div>';
            box.appendChild(row); _scroll();
        }

        function _addBot(md, isErr) {
            var box=document.getElementById('aiDrawerMessages');
            var row=document.createElement('div');
            row.className='ai-dmsg-row';
            var cls=isErr?'ai-bbl-err':'ai-bbl-bot';
            var ico=isErr?'error_outline':'smart_toy';
            var html=isErr?_esc(md):(typeof marked!=='undefined'?marked.parse(md):_esc(md));
            row.innerHTML=_av('ai-av-bot',ico)+'<div class="ai-bbl '+cls+'">'+html+'</div>';
            box.appendChild(row); _scroll();
        }

        function _addThink() {
            var box=document.getElementById('aiDrawerMessages');
            var row=document.createElement('div');
            row.className='ai-dmsg-row'; row.id='aiThinkRow';
            row.innerHTML=_av('ai-av-bot','smart_toy')+
                '<div class="ai-bbl ai-bbl-bot" style="padding:10px 14px;">'+
                '<span class="ai-think-dot"></span><span class="ai-think-dot"></span><span class="ai-think-dot"></span></div>';
            box.appendChild(row); _scroll();
        }

        function _rmThink() { var el=document.getElementById('aiThinkRow'); if(el) el.remove(); }

        function _setLoading(on) {
            _busy=on;
            var inp=document.getElementById('aiDrawerInput');
            var btn=document.getElementById('aiDrawerSend');
            if(inp) inp.disabled=on;
            if(btn) btn.disabled=on;
        }

        // ── Position drawer below actual header height ─────────────────────────
        function _syncDrawerTop() {
            var header = document.querySelector('.top-header');
            var drawer = document.getElementById('aiChatDrawer');
            if (header && drawer) {
                drawer.style.top = (header.getBoundingClientRect().height + 4) + 'px';
            }
        }
        _syncDrawerTop();
        var _headerObs = new ResizeObserver(_syncDrawerTop);
        var _hEl = document.querySelector('.top-header');
        if (_hEl) _headerObs.observe(_hEl);

        // ── Toggle drawer ──────────────────────────────────────────────────────
        window.toggleAiDrawer = function() {
            _syncDrawerTop();
            _open=!_open;
            var drawer=document.getElementById('aiChatDrawer');
            var ico=document.getElementById('aiToggleIco');
            if(drawer) drawer.classList.toggle('ai-open',_open);
            if(ico) ico.textContent=_open?'close':'smart_toy';
            if(_open) { setTimeout(function(){ var i=document.getElementById('aiDrawerInput'); if(i) i.focus(); _scroll(); },230); }
        };

        window.clearAiDrawer = function() {
            _history=[]; _save();
            var box=document.getElementById('aiDrawerMessages');
            if(!box) return;
            box.innerHTML='<div id="aiWelcomeMsg" style="display:flex;align-items:flex-start;gap:8px;">'+
                _av('ai-av-bot','smart_toy')+
                '<div class="ai-bbl ai-bbl-bot">Chat cleared. How can I help you?</div></div>';
            var i=document.getElementById('aiDrawerInput'); if(i) i.focus();
        };

        // ── Textarea auto-resize + Enter to send ───────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            var ta=document.getElementById('aiDrawerInput');
            if(!ta) return;
            ta.addEventListener('input', function(){ this.style.height='auto'; this.style.height=Math.min(this.scrollHeight,90)+'px'; });
            ta.addEventListener('keydown', function(e){ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); aiDrawerSend(); } });
        });

        // ── Send message ───────────────────────────────────────────────────────
        window.aiDrawerSend = async function() {
            if(_busy) return;
            var inp=document.getElementById('aiDrawerInput');
            if(!inp) return;
            var msg=inp.value.trim(); if(!msg) return;
            inp.value=''; inp.style.height='auto';
            _addUser(msg); _addThink(); _setLoading(true);

            var apiHist=_history
                .filter(function(h){ return h.role==='user'||h.role==='ai'; })
                .map(function(h){ return {role:h.role==='ai'?'assistant':h.role,content:h.content}; });

            var csrf='';
            var csrfEl=document.querySelector('meta[name="csrf-token"]');
            if(csrfEl) csrf=csrfEl.getAttribute('content');

            try {
                var res=await fetch(CHAT_URL,{
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                    body:JSON.stringify({message:msg,history:apiHist})
                });
                var data=await res.json();
                _rmThink();
                var isErr=data.type==='error';
                _addBot(data.message||'No response.',isErr);
                _history.push({role:'user',content:msg});
                _history.push({role:isErr?'error':'ai',content:data.message||''});
                if(_history.length>20) _history=_history.slice(_history.length-20);
                _save();
            } catch(err) {
                _rmThink();
                _addBot('Network error. Please try again.',true);
            } finally {
                _setLoading(false);
                var i=document.getElementById('aiDrawerInput'); if(i) i.focus();
            }
        };

        window.aiChip = function(text) {
            if(!_open) toggleAiDrawer();
            setTimeout(function(){ var i=document.getElementById('aiDrawerInput'); if(i) i.value=text; aiDrawerSend(); }, _open?0:260);
        };
    })();
    </script>

    @endif

    {{-- data-turbo-eval="false" keeps GC as a true singleton across Turbo navigations --}}
    <script src="{{ asset('js/global-call.js') }}" data-turbo-eval="false"></script>

    {{-- GC lifecycle helpers — once per session --}}
    <script data-turbo-eval="false">
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            if (window.GC && typeof window.GC.initDevice === 'function') {
                window.GC.initDevice();
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ── Sidebar helpers ──────────────────────────────────────────────────────
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
            const sidebar     = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            if (!sidebar) return;

            if (window.innerWidth > 991) {
                const collapsed = sidebar.classList.toggle('desktop-collapsed');
                mainContent && mainContent.classList.toggle('desktop-expanded', collapsed);
                // Persist across Turbo navigations
                try { localStorage.setItem('mgrSidebarCollapsed', collapsed ? '1' : '0'); } catch(e) {}
            } else {
                if (sidebar.classList.contains('show')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
        }

        // Restore sidebar collapse state after every Turbo Drive navigation
        function _restoreSidebarState() {
            if (window.innerWidth <= 991) return;
            const sidebar     = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            if (!sidebar) return;
            try {
                const collapsed = localStorage.getItem('mgrSidebarCollapsed') === '1';
                sidebar.classList.toggle('desktop-collapsed', collapsed);
                mainContent && mainContent.classList.toggle('desktop-expanded', collapsed);
            } catch(e) {}
        }

        document.addEventListener('turbo:load', _restoreSidebarState);
        document.addEventListener('DOMContentLoaded', _restoreSidebarState);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (window.innerWidth > 991) {
                    const sidebar     = document.getElementById('sidebar');
                    const mainContent = document.getElementById('mainContent');
                    if (sidebar && sidebar.classList.contains('desktop-collapsed')) {
                        sidebar.classList.remove('desktop-collapsed');
                        mainContent && mainContent.classList.remove('desktop-expanded');
                        try { localStorage.setItem('mgrSidebarCollapsed', '0'); } catch(e) {}
                    }
                } else {
                    closeSidebar();
                }
            }
        });
    </script>

    @if (auth()->check() && auth()->user()->role === 'manager')
        <script>
            (function() {
                const snapshotUrl  = @json(route('manager.notifications.snapshot'));
                const markReadUrl  = @json(route('manager.notifications.read-all'));
                const csrfToken    = @json(csrf_token());

                const badgeEl    = document.getElementById('managerNotifBadge');
                const waWrap     = document.getElementById('managerNotifWhatsapp');
                const systemWrap = document.getElementById('managerNotifSystem');
                const markReadBtn = document.getElementById('managerNotifMarkRead');

                if (!badgeEl || !waWrap || !systemWrap) return;

                let previousCount = 0;

                function renderList(items, renderer, emptyText) {
                    if (!items || !items.length) {
                        return '<div class="small text-muted">' + emptyText + '</div>';
                    }
                    return items.map(renderer).join('');
                }

                function updateBadge(count) {
                    if (count > 0) {
                        badgeEl.style.display = 'inline-block';
                        badgeEl.textContent = count > 99 ? '99+' : String(count);
                    } else {
                        badgeEl.style.display = 'none';
                    }
                }

                async function fetchNotifications() {
                    try {
                        const res = await fetch(snapshotUrl, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (!data || !data.ok) return;

                        const waItems     = Array.isArray(data.whatsapp_notifications) ? data.whatsapp_notifications : [];
                        const sysItems    = Array.isArray(data.system_notifications)   ? data.system_notifications   : [];
                        const count       = Number(data.badge_count || 0);

                        if (count > previousCount) {
                            // light beep for new notifications
                            try {
                                const ac = new (window.AudioContext || window.webkitAudioContext)();
                                const osc = ac.createOscillator();
                                const g = ac.createGain();
                                osc.type = 'sine';
                                osc.frequency.setValueAtTime(880, ac.currentTime);
                                g.gain.setValueAtTime(0.001, ac.currentTime);
                                g.gain.exponentialRampToValueAtTime(0.15, ac.currentTime + 0.01);
                                g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.2);
                                osc.connect(g); g.connect(ac.destination);
                                osc.start(); osc.stop(ac.currentTime + 0.22);
                            } catch(e) {}
                        }
                        previousCount = count;
                        updateBadge(count);

                        waWrap.innerHTML = renderList(
                            waItems,
                            function(item) {
                                return '<div class="py-1 border-bottom">' +
                                    '<a href="' + (item.link || '#') + '" class="fw-semibold text-decoration-none d-block">' + (item.title || 'WhatsApp') + '</a>' +
                                    '<div class="text-muted">' + (item.message || '') + '</div>' +
                                    '<div class="text-muted" style="font-size:11px;">' + (item.time || '') + '</div>' +
                                    '</div>';
                            },
                            'No WhatsApp messages.'
                        );

                        systemWrap.innerHTML = renderList(
                            sysItems,
                            function(item) {
                                return '<div class="py-1 border-bottom">' +
                                    '<div class="fw-semibold">' + (item.title || 'Notification') + '</div>' +
                                    '<div class="text-muted">' + (item.message || '') + '</div>' +
                                    '<div class="text-muted" style="font-size:11px;">' + (item.time || '') + '</div>' +
                                    '</div>';
                            },
                            'No system notifications.'
                        );
                    } catch (e) {}
                }

                markReadBtn?.addEventListener('click', async function() {
                    try {
                        await fetch(markReadUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                        });
                        await fetchNotifications();
                    } catch (e) {}
                });

                fetchNotifications();
                setInterval(fetchNotifications, 60000);
            })();
        </script>

        <script>
            // Manager presence heartbeat — keep is_online = true while logged in
            (function () {
                const heartbeatUrl = @json(route('manager.status.heartbeat'));
                const csrfToken    = '{{ csrf_token() }}';

                async function sendHeartbeat() {
                    try {
                        await fetch(heartbeatUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                        });
                    } catch (_) {}
                }

                sendHeartbeat();
                setInterval(sendHeartbeat, 30000);
            })();
        </script>
    @endif
    @stack('scripts')

    {{-- Chart.js global defaults — applied after page scripts load Chart.js --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart !== 'undefined') {
                Chart.defaults.font.family    = "'Manrope', sans-serif";
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
        });
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
        const pollUrl = @json(route('manager.whatsapp.inbox-poll'));
        const LS_KEY   = 'wa_notif_ts_{{ auth()->id() }}';
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
                'Messages received while you were away.',
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

        // ── Pusher real-time: fire poll immediately on new inbound message ───
        // app.js loads echo.js; DOMContentLoaded fires after module scripts.
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.Echo) return;
            try {
                window.Echo.private('whatsapp.inbox.{{ auth()->id() }}')
                    .listen('.message.new', function (data) {
                        poll();
                        window.dispatchEvent(new CustomEvent('wa:message.new', { detail: data }));
                    });
            } catch (e) {}
        });
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
        const IDLE_TIMEOUT   = 15 * 60 * 1000;
        const WARN_BEFORE    = 60 * 1000;
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

        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
            document.addEventListener(evt, resetTimers, { passive: true });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('idleStayBtn');
            if (btn) btn.addEventListener('click', resetTimers);
        });

        resetTimers();
    })();
    </script>
    @endauth
</body>

</html>
