/**
 * tcn-host-bridge.js — DISABLED
 *
 * This file previously opened a named popup window (/softphone/host) and
 * relayed messages between the CRM page and the popup via postMessage.
 *
 * The popup architecture has been replaced by tcn-softphone.js running
 * inline in the CRM page (no popup, no iframe, no postMessage).
 *
 * This file is intentionally inert. Do NOT load it. Do NOT restore the
 * window.open() calls below — any code that needs popup behaviour should
 * instead call window.TCN directly (tcn-softphone.js) or use the
 * TCNWidget façade (tcn-widget.js).
 */
'use strict';

window.TcnHostBridge = {
    init:     function () { console.warn('[TcnHostBridge] disabled — use window.TCN directly'); return this; },
    open:     function () { console.warn('[TcnHostBridge] disabled'); },
    post:     function () { console.warn('[TcnHostBridge] disabled'); return false; },
    snapshot: function () { return null; },
};
