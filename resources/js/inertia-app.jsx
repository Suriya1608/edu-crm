/**
 * inertia-app.jsx — Telecaller SPA entry point
 *
 * This file executes ONCE when the telecaller first opens the app.
 * Inertia.js intercepts all link clicks and form submissions, fetches
 * the new page as JSON, and swaps only the React component — the browser
 * never does a full page reload after this point.
 *
 * SipProvider wraps the entire app so the TCN SIP connection is established
 * once on mount and is never touched during page navigation.
 */

import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Component, createElement, useEffect } from 'react';

// Expose router immediately at module-load time so the Blade-rendered sidebar
// and header links can call router.visit() without waiting for setup() to run.
window._inertiaRouter = router;

// ─── Error Boundary ───────────────────────────────────────────────────────────
// Catches React render errors and shows them instead of a blank page.
// Remove this once the blank-page issue is diagnosed and fixed.
class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { error: null };
        // Reset the error whenever Inertia navigates to a new page so that
        // a render error on one page does not permanently block all pages.
        this._unsubscribe = null;
    }
    componentDidMount() {
        this._unsubscribe = router.on('navigate', () => {
            if (this.state.error) this.setState({ error: null });
        });
    }
    componentWillUnmount() {
        if (this._unsubscribe) this._unsubscribe();
    }
    static getDerivedStateFromError(error) {
        return { error };
    }
    render() {
        if (this.state.error) {
            return createElement('div', {
                style: {
                    padding: '32px',
                    fontFamily: 'monospace',
                    background: '#fff3cd',
                    border: '2px solid #ffc107',
                    borderRadius: 8,
                    margin: 16,
                    maxWidth: 800,
                }
            },
                createElement('strong', { style: { color: '#856404', fontSize: 16 } },
                    'React render error (please report this):'),
                createElement('pre', {
                    style: {
                        marginTop: 12,
                        color: '#721c24',
                        whiteSpace: 'pre-wrap',
                        wordBreak: 'break-all',
                        fontSize: 13,
                    }
                }, String(this.state.error))
            );
        }
        return this.props.children;
    }
}

// ─── Flash Toast (listens to Inertia router navigate events) ─────────────────
// Runs once on app boot — attaches a permanent router listener that fires
// on every Inertia navigation and shows Bootstrap toasts for flash messages.
function initFlashToasts() {
    router.on('navigate', function (event) {
        const flash = event.detail?.page?.props?.flash;
        if (!flash) return;

        const container = document.getElementById('toastContainer');
        if (!container) return;

        ['success', 'error'].forEach(function (type) {
            const msg = flash[type];
            if (!msg) return;

            const isError = type === 'error';
            const toastEl = document.createElement('div');
            toastEl.className = `toast align-items-center text-bg-${isError ? 'danger' : 'success'} border-0`;
            toastEl.setAttribute('role', 'alert');
            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body fw-semibold">${msg}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>`;
            container.appendChild(toastEl);

            if (window.bootstrap && window.bootstrap.Toast) {
                const toast = new window.bootstrap.Toast(toastEl, { delay: 4000 });
                toast.show();
                toastEl.addEventListener('hidden.bs.toast', function () { toastEl.remove(); });
            }
        });
    });
}

// ─── SIP Provider ─────────────────────────────────────────────────────────────
// Mounts ONCE when the app loads. The useEffect with [] dependency array runs
// exactly once — equivalent to componentDidMount. React never unmounts this
// during Inertia navigation so TCN.login() is called exactly once per tab.
function SipProvider({ children, user }) {
    useEffect(() => {
        // Only telecallers need SIP
        if (!user || user.role !== 'telecaller') return;

        // GC (global-call.js) is loaded in app.blade.php and handles the iframe.
        // initDevice() checks localStorage for 'tcn_sip_active' to decide
        // whether to auto-connect SIP on page load.
        const init = () => {
            if (window.GC && typeof window.GC.initDevice === 'function') {
                window.GC.initDevice();
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init, { once: true });
        } else {
            init();
        }

        // No cleanup / logout on unmount — SipProvider never unmounts.
    }, []); // empty deps = runs once

    return children;
}

// ─── Inertia App bootstrap ────────────────────────────────────────────────────
createInertiaApp({
    // Map page name → component file.
    // e.g. Inertia::render('Telecaller/Leads/Index') loads
    //      resources/js/Pages/Telecaller/Leads/Index.jsx
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page  = pages[`./Pages/${name}.jsx`];
        if (!page) {
            throw new Error(
                `[Inertia] Page not found: ./Pages/${name}.jsx\n` +
                `Make sure the file exists in resources/js/Pages/`
            );
        }
        return page;
    },

    // Called once on initial page load.
    setup({ el, App, props }) {
        const user = props?.initialPage?.props?.auth?.user ?? null;

        // Wire up flash toasts via router events (not as App children —
        // in Inertia v3, App children must be a render function, not an element)
        initFlashToasts();

        createRoot(el).render(
            createElement(ErrorBoundary, null,
                createElement(SipProvider, { user },
                    createElement(App, props)
                )
            )
        );
    },

    // Page title: each React page can use <Head title="Leads" />
    // which Inertia renders via @inertiaHead in app.blade.php
    title: (title) => title ? `${title} — Admission CRM` : 'Admission CRM',
});
