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

    @if ($siteFavicon)
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $siteFavicon) }}">
    @else
        <link rel="icon" type="image/png" href="{{ asset('images/default-favicon.png') }}">
    @endif

    {{-- Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

    {{-- Intercept Blade-rendered link clicks (sidebar, header) so they use
         Inertia SPA navigation instead of a full page reload.
         window._inertiaRouter is set by inertia-app.jsx on boot. --}}
    <script>
    (function () {
        document.addEventListener('click', function (e) {
            // Only act on plain <a> tags that have not been handled by Inertia's
            // own React <Link> component (those call preventDefault themselves).
            var link = e.target.closest('a[href]');
            if (!link) return;
            if (e.defaultPrevented) return;

            var href = link.getAttribute('href');
            if (!href) return;

            // Skip external URLs, hash-only anchors, mailto:, tel:, etc.
            if (/^(https?:\/\/|mailto:|tel:|#|javascript:)/i.test(href)) return;

            // Skip links that open a new tab / trigger download
            if (link.target === '_blank' || link.hasAttribute('download')) return;

            // Skip Bootstrap modal triggers and other data- driven links
            if (link.dataset.bsToggle || link.dataset.bsDismiss) return;

            var router = window._inertiaRouter;
            if (!router) return; // Inertia not booted yet — let browser handle it

            e.preventDefault();
            router.visit(href);
        });
    })();
    </script>

    {{-- Sync document.title → header <h2> so each React page can drive the header title
         via <Head title="..."/>.  A MutationObserver on <title> fires on every Inertia
         navigation; no polling, no race conditions. --}}
    <script>
    (function () {
        var titleEl = document.querySelector('title');
        if (!titleEl) return;

        function syncTitle() {
            var raw = document.title || '';
            // Strip the app-name suffix set by createInertiaApp title callback
            var page = raw.replace(/\s*[—–-]\s*Admission CRM\s*$/i, '').trim();
            var h2 = document.getElementById('pageHeaderTitle');
            if (h2 && page) h2.textContent = page;
        }

        // Run once on first mount (React sets <title> synchronously before paint)
        syncTitle();

        // Re-run on every subsequent Inertia navigation
        new MutationObserver(syncTitle).observe(titleEl, { childList: true });
    })();
    </script>

    {{-- Flash toasts — reads from Inertia shared props via window.__inertiaFlash injected by React --}}
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1090;" id="toastContainer"></div>

</body>
</html>
