@php
    $siteLogoPath = \App\Models\Setting::get('site_logo');
    $siteLogoUrl  = $siteLogoPath ? asset('storage/' . $siteLogoPath) : '';
    $siteName     = \App\Models\Setting::get('site_name', config('app.name'));
@endphp

{{-- ── GrapesJS CSS ──────────────────────────────────────────────────────── --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.21.13/dist/css/grapes.min.css">

<style>
/* ═══════════════════════════════════════════════════════════
   EMAIL BUILDER — Full Light Theme Override
   ═══════════════════════════════════════════════════════════ */

/* ── GrapesJS utility-class theme reset (kills dark bg) ─── */
#gjsEbRoot .gjs-one-bg   { background-color: #ffffff !important; }
#gjsEbRoot .gjs-two-bg   { background-color: #f8fafc !important; }
#gjsEbRoot .gjs-three-bg { background-color: #f1f5f9 !important; }
#gjsEbRoot .gjs-four-bg  { background-color: #ffffff !important; }
#gjsEbRoot .gjs-one-color   { color: #374151 !important; }
#gjsEbRoot .gjs-two-color   { color: #64748b !important; }
#gjsEbRoot .gjs-three-color { color: #94a3b8 !important; }
#gjsEbRoot .gjs-four-color  { color: #0f172a !important; }
#gjsEbRoot .gjs-border       { border-color: #e2e8f0 !important; }
#gjsEbRoot .gjs-border-color { border-color: #e2e8f0 !important; }
#gjsEbRoot .gjs-border-b-color { border-bottom-color: #e2e8f0 !important; }
/* Force all GrapesJS panel text to dark */
#gjsEbRoot * { font-family: 'Manrope', Arial, sans-serif !important; }

/* ── Root container ─────────────────────────────────────── */
#gjsEbRoot {
    display: flex;
    height: calc(100vh - 230px);
    min-height: 650px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #f1f5f9;
    box-shadow: 0 2px 8px rgba(15,23,42,.06);
}

/* ── Left: Block Palette ─────────────────────────────────── */
#gjsEbBlocks {
    width: 210px;
    flex-shrink: 0;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#gjsEbBlocksHead {
    padding: 12px 14px 10px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #94a3b8;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
    background: #fafafa;
}
#gjs-blocks {
    flex: 1;
    overflow-y: auto;
    padding: 8px 6px;
    background: #ffffff;
}
#gjs-blocks::-webkit-scrollbar { width: 4px; }
#gjs-blocks::-webkit-scrollbar-track { background: transparent; }
#gjs-blocks::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

/* Block category */
#gjs-blocks .gjs-block-categories { margin: 0; padding: 0; background: #fff !important; }
#gjs-blocks .gjs-block-category   { margin-bottom: 4px; background: #fff !important; }
#gjs-blocks .gjs-block-category .gjs-title {
    font-size: 9px !important; font-weight: 700 !important;
    letter-spacing: .1em !important; text-transform: uppercase !important;
    color: #94a3b8 !important; padding: 8px 6px 5px !important;
    background: transparent !important; border: 0 !important; cursor: default !important;
}
#gjs-blocks .gjs-block-category .gjs-title::before { display: none !important; }
#gjs-blocks .gjs-caret-icon { display: none !important; }

/* Block grid */
#gjs-blocks .gjs-blocks-c {
    display: flex; flex-wrap: wrap; gap: 6px; padding: 2px 4px 10px;
}
#gjs-blocks .gjs-block {
    width: calc(50% - 3px) !important;
    flex: 0 0 calc(50% - 3px) !important;
    margin: 0 !important;
    padding: 11px 4px 9px !important;
    border: 1.5px solid #e9eef4 !important;
    border-radius: 9px !important;
    background: #ffffff !important;
    cursor: grab !important;
    text-align: center !important;
    transition: all .18s ease !important;
    color: #475569 !important;
    font-size: 10.5px !important;
    font-weight: 600 !important;
    box-shadow: 0 1px 3px rgba(15,23,42,.05) !important;
}
#gjs-blocks .gjs-block:hover {
    background: #eff6ff !important;
    border-color: #137fec !important;
    color: #137fec !important;
    box-shadow: 0 3px 8px rgba(19,127,236,.14) !important;
    transform: translateY(-1px) !important;
}
#gjs-blocks .gjs-block__media {
    display: flex !important; justify-content: center !important; margin-bottom: 5px !important;
}
#gjs-blocks .gjs-block__media svg,
#gjs-blocks .gjs-block__media img { color: #94a3b8 !important; transition: color .18s !important; }
#gjs-blocks .gjs-block:hover .gjs-block__media svg,
#gjs-blocks .gjs-block:hover .gjs-block__media img { color: #137fec !important; }
#gjs-blocks .gjs-block-label { line-height: 1.2 !important; }

/* ── Center: Toolbar + Canvas ────────────────────────────── */
#gjsEbCenter {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
    background: #f1f5f9;
}
#gjsEbToolbar {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 7px 12px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
    box-shadow: 0 1px 0 #f1f5f9;
}
.eb-tbtn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 11px; border: 1.5px solid #e2e8f0; border-radius: 7px;
    background: #ffffff; color: #475569; font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: inherit; transition: all .15s; line-height: 1;
    box-shadow: 0 1px 2px rgba(15,23,42,.04); white-space: nowrap;
}
.eb-tbtn .material-icons { font-size: 15px; }
.eb-tbtn:hover { background: #f8fafc; border-color: #94a3b8; color: #1e293b; box-shadow: 0 2px 4px rgba(15,23,42,.07); }
.eb-tbtn.active { background: #eff6ff; border-color: #137fec; color: #137fec; }
.eb-tbtn.eb-danger:hover { background: #fef2f2; border-color: #fca5a5; color: #ef4444; }
.eb-tbtn.eb-primary { border-color: #137fec; color: #ffffff; background: #137fec; font-weight: 700; }
.eb-tbtn.eb-primary:hover { background: #0f6fd4; border-color: #0f6fd4; }
.eb-tb-sep { width: 1px; height: 22px; background: #e2e8f0; margin: 0 4px; flex-shrink: 0; }
.eb-tb-spacer { flex: 1; }

#gjsWrap { flex: 1; overflow: hidden; position: relative; }
#gjs    { position: absolute; inset: 0; }

/* Canvas */
#gjs .gjs-editor    { height: 100% !important; }
#gjs .gjs-cv-canvas {
    top: 0 !important; height: 100% !important;
    width: 100% !important; background: #dde3ea !important;
}
#gjs .gjs-frame-wrapper { padding: 24px 20px !important; }
#gjs .gjs-selected  { outline: 2px solid #137fec !important; outline-offset: -1px !important; }
#gjs .gjs-hovered   { outline: 1px dashed #93c5fd !important; outline-offset: -1px !important; }
#gjs .gjs-toolbar   { background: #137fec !important; border-radius: 6px !important; box-shadow: 0 2px 8px rgba(19,127,236,.3) !important; }
#gjs .gjs-toolbar-item .gjs-toolbar-item__icon { fill: #fff !important; }
#gjs .gjs-badge     { background: #137fec !important; border-radius: 3px !important; font-size: 10px !important; }
#gjs .gjs-resizer-h { border-color: #137fec !important; }

/* ── Right: Properties Panel ─────────────────────────────── */
#gjsEbProps {
    width: 290px;
    flex-shrink: 0;
    background: #ffffff;
    border-left: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#gjsEbPropsTabs {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
    background: #fafafa;
}
.gjs-eb-ptab {
    flex: 1; padding: 10px 4px 8px;
    display: flex; flex-direction: column; align-items: center; gap: 3px;
    font-size: 9.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: #94a3b8; cursor: pointer; border: 0;
    border-bottom: 2px solid transparent; background: none;
    transition: all .15s;
}
.gjs-eb-ptab .material-icons { font-size: 18px; }
.gjs-eb-ptab:hover { color: #475569; background: #f1f5f9; }
.gjs-eb-ptab.active { color: #137fec; border-bottom-color: #137fec; background: #fff; }
.gjs-eb-tpane { flex: 1; overflow-y: auto; display: none; background: #fff; }
.gjs-eb-tpane.active { display: block; }
.gjs-eb-tpane::-webkit-scrollbar { width: 4px; }
.gjs-eb-tpane::-webkit-scrollbar-track { background: transparent; }
.gjs-eb-tpane::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

/* ── Style Manager ──────────────────────────────────────── */
#gjsStylesContainer,
#gjsStylesContainer .gjs-sm-sectors { background: #fff !important; margin: 0 !important; }

#gjsStylesContainer .gjs-sm-sector  { border-bottom: 1px solid #f1f5f9 !important; background: #fff !important; }
#gjsStylesContainer .gjs-sm-sector-title {
    padding: 10px 14px !important; font-size: 10.5px !important; font-weight: 700 !important;
    letter-spacing: .07em !important; text-transform: uppercase !important; color: #475569 !important;
    background: #f8fafc !important; border-bottom: 1px solid #eef2f7 !important;
    cursor: pointer !important; user-select: none !important;
    display: flex !important; align-items: center !important; justify-content: space-between !important;
}
#gjsStylesContainer .gjs-sm-sector-title:hover { background: #f1f5f9 !important; }
#gjsStylesContainer .gjs-sm-sector-caret { color: #94a3b8 !important; }
#gjsStylesContainer .gjs-sm-properties {
    padding: 12px 14px 4px !important; background: #fff !important;
}
#gjsStylesContainer .gjs-sm-property { margin-bottom: 10px !important; }
#gjsStylesContainer .gjs-sm-label,
#gjsStylesContainer .gjs-label {
    font-size: 11px !important; font-weight: 600 !important; color: #64748b !important;
    margin-bottom: 4px !important; display: block !important; text-transform: none !important;
}
/* All input fields in style manager */
#gjsStylesContainer .gjs-field,
#gjsStylesContainer .gjs-sm-field,
#gjsStylesContainer input,
#gjsStylesContainer select,
#gjsStylesContainer textarea {
    background: #f8fafc !important; border: 1.5px solid #e2e8f0 !important;
    border-radius: 6px !important; color: #0f172a !important; font-size: 12px !important;
    padding: 5px 8px !important; transition: border-color .15s !important;
}
#gjsStylesContainer .gjs-field:focus-within,
#gjsStylesContainer .gjs-sm-field:focus-within {
    border-color: #137fec !important; background: #fff !important;
    box-shadow: 0 0 0 3px rgba(19,127,236,.1) !important;
}
/* Radio (align buttons) */
#gjsStylesContainer .gjs-field-radio { border: 1.5px solid #e2e8f0 !important; border-radius: 6px !important; overflow: hidden !important; }
#gjsStylesContainer .gjs-field-radio-item { color: #64748b !important; background: #f8fafc !important; }
#gjsStylesContainer .gjs-field-radio input:checked + .gjs-field-radio-item { background: #137fec !important; color: #fff !important; }
/* Select arrow */
#gjsStylesContainer .gjs-field-select select { background: #f8fafc !important; }
/* Color swatch */
#gjsStylesContainer .gjs-color-picker-trigger { cursor: pointer !important; border-radius: 4px !important; }
/* Buttons */
#gjsStylesContainer .gjs-sm-btn { background: #137fec !important; border-radius: 5px !important; color: #fff !important; font-size: 11px !important; padding: 4px 10px !important; }

/* ── Trait Manager ──────────────────────────────────────── */
#gjsTraitsContainer,
#gjsTraitsContainer .gjs-trt-traits { background: #fff !important; }
#gjsTraitsContainer .gjs-trt-traits  { padding: 14px !important; }
#gjsTraitsContainer .gjs-trt-trait   { margin-bottom: 12px !important; }
#gjsTraitsContainer .gjs-label {
    font-size: 11px !important; font-weight: 600 !important;
    color: #64748b !important; margin-bottom: 4px !important; display: block !important;
}
#gjsTraitsContainer .gjs-field,
#gjsTraitsContainer input[type="text"],
#gjsTraitsContainer input[type="url"],
#gjsTraitsContainer input[type="number"],
#gjsTraitsContainer select {
    background: #f8fafc !important; border: 1.5px solid #e2e8f0 !important;
    border-radius: 6px !important; color: #0f172a !important; font-size: 12.5px !important;
    padding: 7px 10px !important; width: 100% !important;
    transition: border-color .15s, box-shadow .15s !important;
    outline: none !important;
}
#gjsTraitsContainer input:focus,
#gjsTraitsContainer select:focus,
#gjsTraitsContainer .gjs-field:focus-within {
    border-color: #137fec !important; background: #fff !important;
    box-shadow: 0 0 0 3px rgba(19,127,236,.1) !important;
}

/* ── Layer Manager ──────────────────────────────────────── */
#gjsLayersContainer                    { background: #fff !important; }
#gjsLayersContainer .gjs-layer         { background: #fff !important; border-bottom: 1px solid #f1f5f9 !important; padding: 6px 12px !important; transition: background .12s !important; }
#gjsLayersContainer .gjs-layer:hover   { background: #f8fafc !important; }
#gjsLayersContainer .gjs-layer.gjs-selected { background: #eff6ff !important; }
#gjsLayersContainer .gjs-layer-title   { color: #374151 !important; font-size: 12px !important; }
#gjsLayersContainer .gjs-layer-name    { color: #475569 !important; font-size: 12px !important; font-weight: 500 !important; }
#gjsLayersContainer .gjs-layer-count   { color: #94a3b8 !important; font-size: 11px !important; }
#gjsLayersContainer .gjs-layer-vis     { color: #94a3b8 !important; }
#gjsLayersContainer .gjs-layer-vis:hover { color: #137fec !important; }
#gjsLayersContainer .gjs-layers-btn    { background: #137fec !important; }

/* ── Empty-state placeholder ───────────────────────────── */
#gjsPropsEmpty {
    padding: 48px 20px; text-align: center; color: #94a3b8; font-size: 12px; line-height: 1.6;
}
#gjsPropsEmpty .material-icons { font-size: 30px; opacity: .3; display: block; margin-bottom: 8px; }
</style>

{{-- ── Builder HTML ──────────────────────────────────────────────────────── --}}
<div id="gjsEbRoot">

    {{-- Left: Block palette --}}
    <div id="gjsEbBlocks">
        <div id="gjsEbBlocksHead">Email Blocks</div>
        <div id="gjs-blocks"></div>
    </div>

    {{-- Center: Toolbar + Canvas --}}
    <div id="gjsEbCenter">
        <div id="gjsEbToolbar">
            <button type="button" class="eb-tbtn" id="ebBtnUndo" title="Undo (Ctrl+Z)">
                <span class="material-icons">undo</span>
            </button>
            <button type="button" class="eb-tbtn" id="ebBtnRedo" title="Redo (Ctrl+Y)">
                <span class="material-icons">redo</span>
            </button>
            <div class="eb-tb-sep"></div>
            <button type="button" class="eb-tbtn active" id="ebBtnEmail" title="Email view (640px)">
                <span class="material-icons">mail_outline</span><span>Email</span>
            </button>
            <button type="button" class="eb-tbtn" id="ebBtnMobile" title="Mobile view (375px)">
                <span class="material-icons">phone_android</span><span>Mobile</span>
            </button>
            <div class="eb-tb-sep"></div>
            <button type="button" class="eb-tbtn eb-danger" id="ebBtnClear" title="Clear all blocks">
                <span class="material-icons">delete_sweep</span><span>Clear</span>
            </button>
            <div class="eb-tb-spacer"></div>
            <button type="button" class="eb-tbtn" id="btnTemplates" title="Template Library">
                <span class="material-icons">collections</span><span>Templates</span>
            </button>
            <button type="button" class="eb-tbtn" id="btnSendTest" title="Send test email">
                <span class="material-icons">send</span><span>Test</span>
            </button>
            <button type="button" class="eb-tbtn eb-primary" id="btnPreview">
                <span class="material-icons">visibility</span><span>Preview</span>
            </button>
        </div>
        <div id="gjsWrap">
            <div id="gjs"></div>
        </div>
    </div>

    {{-- Right: Properties --}}
    <div id="gjsEbProps">
        <div id="gjsEbPropsTabs">
            <button type="button" class="gjs-eb-ptab active" data-ptab="styles">
                <span class="material-icons">palette</span>Styles
            </button>
            <button type="button" class="gjs-eb-ptab" data-ptab="traits">
                <span class="material-icons">tune</span>Settings
            </button>
            <button type="button" class="gjs-eb-ptab" data-ptab="layers">
                <span class="material-icons">layers</span>Layers
            </button>
        </div>
        <div class="gjs-eb-tpane active" id="gjsStylesContainer">
            <div id="gjsPropsEmpty" style="padding:40px 16px;text-align:center;color:#94a3b8;font-size:12px;">
                <span class="material-icons d-block mb-2" style="font-size:28px;opacity:.35;">palette</span>
                Select an element to<br>edit its styles
            </div>
        </div>
        <div class="gjs-eb-tpane" id="gjsTraitsContainer"></div>
        <div class="gjs-eb-tpane" id="gjsLayersContainer"></div>
    </div>
</div>

{{-- ── Template Library Modal ─────────────────────────────────────────────── --}}
<div class="modal fade" id="templatesModal" tabindex="-1" aria-labelledby="templatesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:14px;border:1px solid #e2e8f0;overflow:hidden;">
            <div class="modal-header" style="border-bottom:1px solid #f1f5f9;padding:18px 24px;background:#fafafa;">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-icons" style="color:#137fec;font-size:22px;">collections</span>
                    <h5 class="modal-title fw-bold mb-0" id="templatesModalLabel">Template Library</h5>
                    <span class="badge ms-1" style="background:#eff6ff;color:#137fec;font-size:11px;font-weight:600;padding:3px 8px;border-radius:20px;">6 Professional Templates</span>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-3" style="font-size:13px;">Click a template to load it into the builder. <strong>Your current design will be replaced.</strong></p>
                <div class="row g-3" id="templateGrid"></div>
            </div>
        </div>
    </div>
</div>

{{-- ── Send Test Email Modal ───────────────────────────────────────────────── --}}
<div class="modal fade" id="sendTestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content" style="border-radius:14px;border:1px solid #e2e8f0;">
            <div class="modal-header" style="border-bottom:1px solid #f1f5f9;padding:16px 20px;background:#fafafa;">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-icons" style="color:#137fec;font-size:20px;">send</span>
                    <h5 class="modal-title fw-bold mb-0">Send Test Email</h5>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-3" style="font-size:13px;">Preview how the email looks in a real inbox. The current builder content will be sent.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:13px;">Recipient Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="testEmailAddress" placeholder="you@example.com">
                </div>
                <div id="testEmailMsg" class="alert d-none mb-0" style="font-size:13px;"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:14px 20px;">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="btnSendTestConfirm">
                    <span class="material-icons align-middle me-1" style="font-size:15px;">send</span>Send Test
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.tpl-card {
    border: 1.5px solid #e2e8f0; border-radius: 12px; overflow: hidden;
    cursor: pointer; transition: all .2s; background: #fff;
}
.tpl-card:hover { border-color: #137fec; box-shadow: 0 4px 16px rgba(19,127,236,.15); transform: translateY(-2px); }
.tpl-card-header { padding: 24px 20px 18px; text-align: center; }
.tpl-card-body { padding: 14px 16px 16px; border-top: 1px solid #f1f5f9; }
.tpl-card-title { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 3px; }
.tpl-card-desc  { font-size: 12px; color: #64748b; margin: 0; line-height: 1.5; }
</style>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.21.13/dist/grapes.min.js"></script>
<script>
(function () {
'use strict';

// ── PHP → JS config ──────────────────────────────────────────────────────────
const CSRF        = document.querySelector('meta[name="csrf-token"]').content;
const UPLOAD_URL  = @json(route('admin.email-templates.upload-image'));
const BRAND       = '#137fec';
const LOGO_URL    = @json($siteLogoUrl);
const SITE_NAME   = @json($siteName);
const INIT_DATA   = @json($initData ?? null);

// ── CSS → Inline Styles (email-safe: no <style> blocks in output) ─────────────
function inlineCssIntoHtml(html, css) {
    if (!css || !css.trim()) return html;
    const doc = (new DOMParser()).parseFromString('<html><body>' + html + '</body></html>', 'text/html');
    const tempStyle = document.createElement('style');
    tempStyle.textContent = css;
    document.head.appendChild(tempStyle);
    try {
        const rules = tempStyle.sheet ? Array.from(tempStyle.sheet.cssRules) : [];
        rules.forEach(function (rule) {
            if (rule.type !== 1) return; // STYLE_RULE only
            try {
                const els = doc.querySelectorAll(rule.selectorText);
                els.forEach(function (el) {
                    // Build existing inline style map (inline attrs take priority)
                    const existing = {};
                    (el.getAttribute('style') || '').split(';').forEach(function (s) {
                        const idx = s.indexOf(':');
                        if (idx > 0) {
                            const k = s.substring(0, idx).trim();
                            const v = s.substring(idx + 1).trim();
                            if (k) existing[k] = v;
                        }
                    });
                    // Merge rule props — existing inline wins
                    const merged = {};
                    for (let i = 0; i < rule.style.length; i++) {
                        const k = rule.style[i];
                        if (!Object.prototype.hasOwnProperty.call(existing, k)) {
                            merged[k] = rule.style.getPropertyValue(k).trim();
                        }
                    }
                    Object.assign(merged, existing);
                    const styleStr = Object.entries(merged)
                        .filter(function (e) { return e[0] && e[1]; })
                        .map(function (e) { return e[0] + ':' + e[1]; })
                        .join(';');
                    if (styleStr) el.setAttribute('style', styleStr);
                });
            } catch (e) { /* skip invalid selectors */ }
        });
    } finally {
        document.head.removeChild(tempStyle);
    }
    return doc.body.innerHTML;
}

// ── Block HTML helpers ────────────────────────────────────────────────────────
function logoHtml() {
    if (LOGO_URL) {
        return `<img src="${LOGO_URL}" alt="${SITE_NAME}" style="max-width:180px;max-height:65px;height:auto;display:inline-block;border:0;">`;
    }
    return `<div style="display:inline-block;padding:9px 20px;background:${BRAND};border-radius:6px;font-family:Arial,Helvetica,sans-serif;font-size:17px;font-weight:700;color:#ffffff;">${SITE_NAME}</div>`;
}

// Social icon PNG definitions — uses Flaticon CDN (PNG, renders in all email clients)
const SOCIAL_DEFS = {
    facebook:  { title: 'Facebook',    img: 'https://cdn-icons-png.flaticon.com/512/5968/5968764.png' },
    instagram: { title: 'Instagram',   img: 'https://cdn-icons-png.flaticon.com/512/2111/2111463.png' },
    twitter:   { title: 'Twitter / X', img: 'https://cdn-icons-png.flaticon.com/512/5968/5968958.png' },
    linkedin:  { title: 'LinkedIn',    img: 'https://cdn-icons-png.flaticon.com/512/2111/2111499.png' },
    youtube:   { title: 'YouTube',     img: 'https://cdn-icons-png.flaticon.com/512/1384/1384060.png' },
};

// Returns a <td> cell for one social icon (PNG image — works in all email clients)
function socialIconTd(network, url) {
    const d = SOCIAL_DEFS[network];
    return `<td width="50" align="center" valign="middle" style="padding:0 6px;"><a href="${url || '#'}" class="eb-social-link" data-network="${network}" target="_blank" title="${d.title}" style="display:block;text-decoration:none;"><img src="${d.img}" width="40" height="40" alt="${d.title}" title="${d.title}" style="display:block;border:0;width:40px;height:40px;border-radius:8px;" border="0"></a></td>`;
}

// ── Block definitions ─────────────────────────────────────────────────────────
const BLOCKS = [
    // ── Content ──────────────────────────────────────────────────────────────
    {
        id: 'eb-text',
        label: 'Text',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M2.5 4v3h5v12h3V7h5V4h-13zm19 5h-9v3h3v7h3v-7h3V9z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#374151;padding:4px 0;"><p style="margin:0;">Type your paragraph text here. Use <strong>bold</strong>, <em>italic</em>, or add <a href="#">links</a>. Use <strong>&#123;&#123;name&#125;&#125;</strong> for personalisation.</p></td></tr></table>`,
    },
    {
        id: 'eb-heading',
        label: 'Heading',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M5 4v3h5.5v12h3V7H19V4z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td style="padding:4px 0 10px;"><h2 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:700;color:#0f172a;line-height:1.3;text-align:left;">Your Heading Here</h2></td></tr></table>`,
    },
    {
        id: 'eb-image',
        label: 'Image',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td style="text-align:center;padding:4px 0;"><img class="eb-email-img" src="https://placehold.co/560x200/f1f5f9/94a3b8?text=Double-click+to+upload" alt="Image" style="max-width:100%;height:auto;display:block;margin:0 auto;border-radius:4px;border:0;"></td></tr></table>`,
    },
    {
        id: 'eb-logo',
        label: 'Logo',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td style="padding:12px 0;text-align:center;">${logoHtml()}</td></tr></table>`,
    },
    {
        id: 'eb-button',
        label: 'Button',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>`,
        content: `<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:8px 0 16px;"><tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="${BRAND}" style="border-radius:6px;"><a href="#" style="display:inline-block;padding:13px 32px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;text-decoration:none;border-radius:6px;letter-spacing:.3px;">Apply Now</a></td></tr></table></td></tr></table>`,
    },
    {
        id: 'eb-social',
        label: 'Social',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0;border-collapse:collapse;"><tr><td align="center" style="padding:8px 0;"><table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;"><tr>${socialIconTd('facebook')}${socialIconTd('instagram')}${socialIconTd('twitter')}${socialIconTd('linkedin')}${socialIconTd('youtube')}</tr></table></td></tr></table>`,
    },
    {
        id: 'eb-product',
        label: 'Product',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 16px;"><tr><td width="38%" style="padding:0 14px 0 0;vertical-align:top;"><img class="eb-email-img" src="https://placehold.co/200x150/f1f5f9/94a3b8?text=Course" alt="Course" style="max-width:100%;height:auto;display:block;border-radius:6px;border:0;"></td><td width="62%" style="vertical-align:top;"><h3 style="margin:0 0 7px;font-family:Arial,Helvetica,sans-serif;font-size:17px;font-weight:700;color:#0f172a;line-height:1.3;">Course Name</h3><p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#64748b;">Brief description of the course. Highlight key benefits and learning outcomes.</p><p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:${BRAND};">&#8377;15,000 / year</p><table cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="${BRAND}" style="border-radius:5px;"><a href="#" style="display:inline-block;padding:8px 18px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;text-decoration:none;">Learn More &#8594;</a></td></tr></table></td></tr></table>`,
    },
    // ── Layout ────────────────────────────────────────────────────────────────
    {
        id: 'eb-divider',
        label: 'Divider',
        category: 'Layout',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M19 13H5v-2h14v2z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0;"><tr><td style="border-top:1px solid #e2e8f0;font-size:0;line-height:0;">&nbsp;</td></tr></table>`,
    },
    {
        id: 'eb-spacer',
        label: 'Spacer',
        category: 'Layout',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M8 19h3v3h2v-3h3l-4-4-4 4zm8-14h-3V2h-2v3H8l4 4 4-4zM4 11v2h16v-2H4z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="28" style="font-size:0;line-height:0;background:transparent;">&nbsp;</td></tr></table>`,
    },
    {
        id: 'eb-col2',
        label: '2 Cols',
        category: 'Layout',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8 16H4V4h8v14zm8 0h-8V4h8v14z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td width="50%" style="padding:4px 10px 4px 0;vertical-align:top;"><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#374151;">Left column content.</p></td><td width="50%" style="padding:4px 0 4px 10px;vertical-align:top;"><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#374151;">Right column content.</p></td></tr></table>`,
    },
    {
        id: 'eb-col3',
        label: '3 Cols',
        category: 'Layout',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M3 5h2v14H3V5zm4 0h2v14H7V5zm4 0h2v14h-2V5zm4 0h2v14h-2V5zm4 0h2v14h-2V5z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td width="33%" style="padding:4px 10px 4px 0;vertical-align:top;text-align:center;"><div style="font-size:26px;margin-bottom:8px;">⭐</div><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;margin-bottom:6px;">Feature 1</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.65;color:#64748b;">Short description of this feature or benefit here.</p></td><td width="33%" style="padding:4px 5px;vertical-align:top;text-align:center;"><div style="font-size:26px;margin-bottom:8px;">🎯</div><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;margin-bottom:6px;">Feature 2</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.65;color:#64748b;">Short description of this feature or benefit here.</p></td><td width="34%" style="padding:4px 0 4px 10px;vertical-align:top;text-align:center;"><div style="font-size:26px;margin-bottom:8px;">🚀</div><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;margin-bottom:6px;">Feature 3</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.65;color:#64748b;">Short description of this feature or benefit here.</p></td></tr></table>`,
    },
    {
        id: 'eb-hero',
        label: 'Hero',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr><td bgcolor="${BRAND}" style="background-color:${BRAND};padding:52px 32px;text-align:center;border-radius:8px;"><h1 style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:34px;font-weight:800;color:#ffffff;line-height:1.2;letter-spacing:-.5px;">Your Headline Here</h1><p style="margin:0 0 28px;font-family:Arial,Helvetica,sans-serif;font-size:17px;color:rgba(255,255,255,0.88);line-height:1.6;">A compelling subheadline that drives action and inspires your audience.</p><table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr><td bgcolor="#ffffff" style="border-radius:6px;"><a href="#" style="display:inline-block;padding:14px 38px;color:${BRAND};font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;text-decoration:none;border-radius:6px;">Get Started &rarr;</a></td></tr></table></td></tr></table>`,
    },
    {
        id: 'eb-highlight',
        label: 'Highlight',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0;"><tr><td bgcolor="#eff6ff" style="background-color:#eff6ff;padding:20px 24px;border-radius:8px;border-left:4px solid ${BRAND};"><h3 style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#1e40af;">Key Highlight</h3><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.65;">Add your key highlight, important notice, or feature benefit here.</p></td></tr></table>`,
    },
    {
        id: 'eb-quote',
        label: 'Quote',
        category: 'Content',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:12px 0;"><tr><td bgcolor="#f8fafc" style="background-color:#f8fafc;border-left:4px solid ${BRAND};padding:16px 20px;border-radius:0 6px 6px 0;"><p style="margin:0 0 10px;font-family:Georgia,'Times New Roman',serif;font-size:16px;font-style:italic;color:#374151;line-height:1.65;">"This course completely changed my career trajectory. The curriculum is outstanding and the faculty is world-class."</p><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#64748b;">&mdash; Student Name, Course Name</p></td></tr></table>`,
    },
    {
        id: 'eb-email-header',
        label: 'Header',
        category: 'Layout',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0;"><tr><td bgcolor="${BRAND}" style="background-color:${BRAND};padding:22px 32px;text-align:center;border-radius:8px 8px 0 0;">${logoHtml()}</td></tr></table>`,
    },
    {
        id: 'eb-email-footer',
        label: 'Footer',
        category: 'Layout',
        media: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>`,
        content: `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0 0;"><tr><td bgcolor="#f8fafc" style="background-color:#f8fafc;padding:24px 32px;border-top:1px solid #e2e8f0;border-radius:0 0 8px 8px;text-align:center;"><p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;line-height:1.6;">You are receiving this email because you expressed interest in our programs.<br><a href="#" style="color:${BRAND};text-decoration:underline;">Unsubscribe</a> &nbsp;|&nbsp; <a href="#" style="color:${BRAND};text-decoration:underline;">Privacy Policy</a></p><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;">&copy; ${new Date().getFullYear()} ${SITE_NAME}. All rights reserved.</p></td></tr></table>`,
    },
];

// ── Style Manager sectors ─────────────────────────────────────────────────────
const STYLE_SECTORS = [
    {
        name: 'Typography', open: true,
        properties: [
            { type: 'select', property: 'font-family', label: 'Font', defaults: 'Arial, Helvetica, sans-serif',
              options: [
                { value: 'Arial, Helvetica, sans-serif', name: 'Arial' },
                { value: 'Georgia, "Times New Roman", serif', name: 'Georgia' },
                { value: '"Times New Roman", Times, serif', name: 'Times New Roman' },
                { value: 'Verdana, Geneva, sans-serif', name: 'Verdana' },
                { value: '"Trebuchet MS", Helvetica, sans-serif', name: 'Trebuchet MS' },
              ],
            },
            { property: 'font-size', label: 'Size', type: 'integer', units: ['px'], defaults: 15, min: 10, max: 60 },
            { property: 'font-weight', label: 'Weight', type: 'select',
              options: [{ value: '400', name: 'Normal' }, { value: '600', name: 'Semi Bold' }, { value: '700', name: 'Bold' }],
            },
            { property: 'color', label: 'Color', type: 'color' },
            { property: 'text-align', label: 'Align', type: 'radio',
              options: [{ value: 'left', name: 'L' }, { value: 'center', name: 'C' }, { value: 'right', name: 'R' }],
            },
            { property: 'line-height', label: 'Line Height', type: 'number', units: [''], defaults: 1.7, min: 1, max: 4, step: 0.1 },
        ],
    },
    {
        name: 'Spacing', open: false,
        properties: [
            { property: 'padding-top', label: 'Pad Top', type: 'integer', units: ['px'], min: 0 },
            { property: 'padding-right', label: 'Pad Right', type: 'integer', units: ['px'], min: 0 },
            { property: 'padding-bottom', label: 'Pad Bottom', type: 'integer', units: ['px'], min: 0 },
            { property: 'padding-left', label: 'Pad Left', type: 'integer', units: ['px'], min: 0 },
            { property: 'margin-top', label: 'Margin Top', type: 'integer', units: ['px'] },
            { property: 'margin-bottom', label: 'Margin Bottom', type: 'integer', units: ['px'] },
        ],
    },
    {
        name: 'Appearance', open: false,
        properties: [
            { property: 'background-color', label: 'Background', type: 'color' },
            { property: 'border-radius', label: 'Border Radius', type: 'integer', units: ['px'], min: 0 },
            { property: 'width', label: 'Width', type: 'integer', units: ['px', '%'], min: 0 },
            { property: 'max-width', label: 'Max Width', type: 'integer', units: ['px', '%'], min: 0 },
            { property: 'opacity', label: 'Opacity', type: 'number', min: 0, max: 1, step: 0.1 },
        ],
    },
    {
        name: 'Border', open: false,
        properties: [
            { property: 'border-width', label: 'Width', type: 'integer', units: ['px'], min: 0 },
            { property: 'border-style', label: 'Style', type: 'select',
              options: [{ value: 'none', name: 'None' }, { value: 'solid', name: 'Solid' }, { value: 'dashed', name: 'Dashed' }, { value: 'dotted', name: 'Dotted' }],
            },
            { property: 'border-color', label: 'Color', type: 'color' },
        ],
    },
];

// ── Initialise GrapesJS ───────────────────────────────────────────────────────
const editor = grapesjs.init({
    container: '#gjs',
    height: '100%',
    width: 'auto',
    fromElement: false,
    storageManager: false,
    undoManager: { trackSelection: false },

    assetManager: {
        upload: UPLOAD_URL,
        uploadName: 'image',
        headers: { 'X-CSRF-TOKEN': CSRF },
        autoAdd: true,
        multiUpload: false,
    },

    deviceManager: {
        devices: [
            { name: 'Email', width: '600px', widthMedia: '' },
            { name: 'Mobile', width: '375px', widthMedia: '375px' },
        ],
    },

    blockManager: {
        appendTo: '#gjs-blocks',
        blocks: BLOCKS,
    },

    styleManager: {
        appendTo: '#gjsStylesContainer',
        sectors: STYLE_SECTORS,
    },

    layerManager: {
        appendTo: '#gjsLayersContainer',
    },

    traitManager: {
        appendTo: '#gjsTraitsContainer',
    },

    panels: { defaults: [] },
});

// ── Social link component — allows editing URL via Traits panel ───────────────
editor.DomComponents.addType('social-link', {
    isComponent: el => el.tagName === 'A' && el.classList && el.classList.contains('eb-social-link'),
    model: {
        defaults: {
            tagName: 'a',
            draggable: false,
            traits: [
                {
                    type:  'text',
                    name:  'href',
                    label: 'Profile URL',
                    placeholder: 'https://...',
                },
                {
                    type:    'select',
                    name:    'target',
                    label:   'Open in',
                    options: [
                        { id: '_blank', name: 'New Tab' },
                        { id: '_self',  name: 'Same Tab' },
                    ],
                },
            ],
        },
    },
});

// ── Image upload infrastructure ───────────────────────────────────────────────
let ebUploadTarget = null;

const ebFileInput = document.createElement('input');
ebFileInput.type   = 'file';
ebFileInput.accept = 'image/jpeg,image/png,image/gif,image/webp';
ebFileInput.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
document.body.appendChild(ebFileInput);

ebFileInput.addEventListener('change', function () {
    const file = this.files[0];
    this.value = '';
    if (!file) return;

    const fd = new FormData();
    fd.append('image', file);

    fetch(UPLOAD_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
        body: fd,
    })
    .then(function (r) {
        if (!r.ok) throw new Error('Upload failed (' + r.status + ')');
        return r.json();
    })
    .then(function (data) {
        const url = data.url;
        if (url && ebUploadTarget) {
            // Use set('attributes') for a guaranteed model update + re-render
            const attrs = Object.assign({}, ebUploadTarget.get('attributes') || {});
            attrs.src = url;
            ebUploadTarget.set('attributes', attrs);
        }
        ebUploadTarget = null;
    })
    .catch(function () {
        alert('Image upload failed. Please try again.');
        ebUploadTarget = null;
    });
});

// ── Upload image command ──────────────────────────────────────────────────────
editor.Commands.add('eb-upload-img', {
    run(ed) {
        let sel = ed.getSelected();
        if (!sel) return;
        // If a wrapper element is selected, find the img inside it
        if (sel.get('type') !== 'eb-email-img') {
            const found = sel.find('.eb-email-img')[0];
            if (!found) return;
            sel = found;
        }
        ebUploadTarget = sel;
        ebFileInput.click();
    },
});

// ── Uploadable image component type ──────────────────────────────────────────
editor.DomComponents.addType('eb-email-img', {
    isComponent: el => el.tagName === 'IMG' && el.classList && el.classList.contains('eb-email-img'),
    model: {
        defaults: {
            tagName: 'img',
            traits: [
                { type: 'text', name: 'src', label: 'Image URL' },
                { type: 'text', name: 'alt', label: 'Alt Text' },
            ],
        },
        init() {
            const toolbar = [...(this.get('toolbar') || [])];
            if (!toolbar.find(t => t.command === 'eb-upload-img')) {
                toolbar.unshift({
                    attributes: { title: 'Upload Image' },
                    command: 'eb-upload-img',
                    label: '<svg viewBox="0 0 24 24" width="14" height="14" fill="white" style="display:block"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>',
                });
                this.set('toolbar', toolbar);
            }
        },
    },
    view: {
        events: { dblclick: 'onDblClick' },
        onDblClick() {
            // Explicitly select this component before running the upload command
            editor.select(this.model);
            editor.Commands.run('eb-upload-img');
        },
    },
});

// ── Canvas: set wrapper background white ──────────────────────────────────────
editor.on('load', function () {
    const wrapper = editor.getWrapper();
    if (wrapper) {
        wrapper.addStyle({
            'background-color': '#ffffff',
            'min-height': '200px',
        });
    }

    // Load existing project data
    if (INIT_DATA && typeof INIT_DATA === 'object' && INIT_DATA.pages) {
        editor.loadProjectData(INIT_DATA);
    }
});

// Hide "Select element" message when something is selected
editor.on('component:selected', function () {
    const empty = document.getElementById('gjsPropsEmpty');
    if (empty) empty.style.display = 'none';
});
editor.on('component:deselected', function () {
    const empty = document.getElementById('gjsPropsEmpty');
    if (empty && !editor.getSelected()) empty.style.display = '';
});

// ── Toolbar buttons ───────────────────────────────────────────────────────────
document.getElementById('ebBtnUndo').addEventListener('click', () => editor.UndoManager.undo());
document.getElementById('ebBtnRedo').addEventListener('click', () => editor.UndoManager.redo());

document.getElementById('ebBtnEmail').addEventListener('click', function () {
    editor.setDevice('Email');
    this.classList.add('active');
    document.getElementById('ebBtnMobile').classList.remove('active');
});
document.getElementById('ebBtnMobile').addEventListener('click', function () {
    editor.setDevice('Mobile');
    this.classList.add('active');
    document.getElementById('ebBtnEmail').classList.remove('active');
});

document.getElementById('ebBtnClear').addEventListener('click', function () {
    if (confirm('Clear all blocks from the canvas? This cannot be undone.')) {
        editor.setComponents('');
        editor.setStyle('');
    }
});

// ── Right panel tabs ──────────────────────────────────────────────────────────
document.querySelectorAll('.gjs-eb-ptab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        const target = this.dataset.ptab;
        document.querySelectorAll('.gjs-eb-ptab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.gjs-eb-tpane').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const pane = document.getElementById('gjs' + target.charAt(0).toUpperCase() + target.slice(1) + 'Container');
        if (pane) pane.classList.add('active');
    });
});

// ── Preview ───────────────────────────────────────────────────────────────────
document.getElementById('btnPreview').addEventListener('click', function () {
    const rawHtml = editor.getHtml();
    const css     = editor.getCss({ avoidProtected: true });

    // Strip outer <body> wrapper if present
    const bodyMatch = rawHtml.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
    const innerHtml = bodyMatch ? bodyMatch[1] : rawHtml;

    // Inline all CSS into element style attributes — no <style> blocks in preview
    const inlined = inlineCssIntoHtml(innerHtml, css);

    const year = new Date().getFullYear();
    // Render preview using the same table-based structure as emails/campaign.blade.php
    // so WYSIWYG matches real inbox rendering (600px, inline CSS, MSO comments)
    const fullDoc = `<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<title>Email Preview</title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<style type="text/css">
body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
table,td{mso-table-lspace:0pt;mso-table-rspace:0pt;}
img{-ms-interpolation-mode:bicubic;border:0;height:auto;line-height:100%;outline:none;text-decoration:none;}
table{border-collapse:collapse!important;}
body{height:100%!important;margin:0!important;padding:0!important;width:100%!important;background-color:#f6f7f8;}
@media screen and (max-width:600px){
  .email-container{width:100%!important;}
  .body-pad{padding:24px 16px!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f6f7f8;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f7f8;">
<tr><td align="center" style="padding:24px 16px;">
<table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0"
       style="max-width:600px;width:100%;background-color:#ffffff;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 2px 10px rgba(15,23,42,0.08);">
<tr>
  <td class="body-pad"
      style="padding:32px 36px 28px;color:#0f172a;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;mso-line-height-rule:exactly;">
    ${inlined}
  </td>
</tr>
<tr>
  <td style="padding:16px 32px 20px;background-color:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 10px 10px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.6;">
    &copy; ${year} ${SITE_NAME}. All rights reserved.
  </td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>`;

    const iframe = document.getElementById('previewIframe');
    if (iframe) iframe.srcdoc = fullDoc;

    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
});

// ── Form submit: export GrapesJS → body + blocks_json ─────────────────────────
const templateForm = document.getElementById('templateForm');
if (templateForm) {
    templateForm.addEventListener('submit', function (e) {
        if (window.TEMPLATE_EDITOR_MODE === 'simple') return; // simple mode owns the submit
        const rawHtml = editor.getHtml();
        const css     = editor.getCss({ avoidProtected: true });

        // Strip outer body wrapper
        const bodyMatch = rawHtml.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        const innerHtml = bodyMatch ? bodyMatch[1] : rawHtml;

        // Inline all CSS into element style attributes — email clients ignore <style> blocks
        const body = inlineCssIntoHtml(innerHtml, css);

        if (!innerHtml.trim()) {
            e.preventDefault();
            alert('Please add at least one block to the email template before saving.');
            return;
        }

        document.getElementById('hiddenBody').value       = body;
        document.getElementById('hiddenBlocksJson').value = JSON.stringify(editor.getProjectData());
    });
}

})();

// ── Template Library ──────────────────────────────────────────────────────────
(function () {
'use strict';

const CSRF2     = document.querySelector('meta[name="csrf-token"]').content;
const BRAND2    = '#137fec';
const SITENAME2 = @json($siteName);
const YR        = new Date().getFullYear();

// Helper: branded button html
function btn(label, href) {
    return `<table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr><td bgcolor="${BRAND2}" style="border-radius:6px;"><a href="${href||'#'}" style="display:inline-block;padding:13px 32px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;text-decoration:none;border-radius:6px;">${label}</a></td></tr></table>`;
}
function divider() {
    return `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0;"><tr><td style="padding:0 0;height:1px;background:#e2e8f0;font-size:0;line-height:0;">&nbsp;</td></tr></table>`;
}
function spacer(h) {
    return `<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="${h||20}" style="font-size:0;line-height:0;">&nbsp;</td></tr></table>`;
}

@verbatim
const EMAIL_TEMPLATES = [
    // ── 1: Course Promotion ───────────────────────────────────────────────────
    {
        id: 'course-promotion',
        name: 'Course Promotion',
        desc: 'Hero banner, features grid, price & enroll CTA',
        icon: 'school', color: BRAND2,
        html: `
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="${BRAND2}" style="background-color:${BRAND2};padding:48px 32px;text-align:center;border-radius:8px 8px 0 0;"><h1 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:30px;font-weight:800;color:#ffffff;line-height:1.25;">Advance Your Career with<br>{{course_name}}</h1><p style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:16px;color:rgba(255,255,255,0.9);line-height:1.6;">Join 10,000+ students who transformed their careers with our programs.</p>${btn('Enroll Now &rarr;', '{{cta_link}}')}</td></tr></table>
${spacer(8)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:28px 32px 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.75;"><p style="margin:0 0 12px;"><strong>Dear {{name}},</strong></p><p style="margin:0;">We're excited to introduce our newest program designed to help you achieve your professional goals. Our expert-led curriculum combines practical skills with industry knowledge to ensure you're job-ready from day one.</p></td></tr></table>
${divider()}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:20px 32px 24px;"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td width="33%" style="padding:0 10px 0 0;vertical-align:top;text-align:center;"><div style="font-size:28px;margin-bottom:8px;">🎓</div><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin-bottom:5px;">Expert Faculty</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;line-height:1.6;">Industry professionals with 10+ years experience.</p></td><td width="33%" style="padding:0 5px;vertical-align:top;text-align:center;"><div style="font-size:28px;margin-bottom:8px;">📅</div><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin-bottom:5px;">Flexible Learning</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;line-height:1.6;">Study at your own pace with lifetime access.</p></td><td width="34%" style="padding:0 0 0 10px;vertical-align:top;text-align:center;"><div style="font-size:28px;margin-bottom:8px;">💼</div><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin-bottom:5px;">Job Placement</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;line-height:1.6;">Our career team connects you with top employers.</p></td></tr></table></td></tr></table>
${divider()}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#f8fafc" style="background-color:#f8fafc;padding:28px 32px;text-align:center;"><p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Course Fee</p><p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:36px;font-weight:800;color:${BRAND2};">{{price}}</p>${btn('Secure Your Seat Today &rarr;', '{{cta_link}}')}</td></tr></table>
${spacer(8)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:18px 32px;text-align:center;"><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.7;">&copy; ${YR} ${SITENAME2}. All rights reserved.<br><a href="#" style="color:${BRAND2};text-decoration:underline;">Unsubscribe</a>&nbsp;&middot;&nbsp;<a href="#" style="color:${BRAND2};text-decoration:underline;">Privacy Policy</a></p></td></tr></table>`,
    },

    // ── 2: Welcome Email ──────────────────────────────────────────────────────
    {
        id: 'welcome-email',
        name: 'Welcome Email',
        desc: 'Personalized welcome with onboarding steps',
        icon: 'waving_hand', color: '#10b981',
        html: `
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#10b981" style="background-color:#10b981;padding:48px 32px;text-align:center;border-radius:8px 8px 0 0;"><div style="font-size:48px;margin-bottom:12px;">🎉</div><h1 style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:28px;font-weight:800;color:#ffffff;line-height:1.3;">Welcome, {{name}}!</h1><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:16px;color:rgba(255,255,255,0.9);line-height:1.6;">You're officially part of the ${SITENAME2} family.</p></td></tr></table>
${spacer(8)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:28px 32px 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.75;"><p style="margin:0 0 16px;">Thank you for joining us! We're thrilled to have you on board. Here's how to get started and make the most of your experience with us.</p></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:0 32px 24px;"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:14px 16px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px;"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td width="44" style="vertical-align:top;padding-right:14px;"><div style="width:36px;height:36px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif;font-size:16px;font-weight:700;color:#fff;text-align:center;line-height:36px;">1</div></td><td style="vertical-align:top;"><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin-bottom:4px;">Complete Your Profile</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#64748b;line-height:1.5;">Update your information so we can personalise your experience.</p></td></tr></table></td></tr><tr><td height="8"></td></tr><tr><td style="padding:14px 16px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px;"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td width="44" style="vertical-align:top;padding-right:14px;"><div style="width:36px;height:36px;background:#10b981;border-radius:50%;font-family:Arial,sans-serif;font-size:16px;font-weight:700;color:#fff;text-align:center;line-height:36px;">2</div></td><td style="vertical-align:top;"><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin-bottom:4px;">Browse Our Courses</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#64748b;line-height:1.5;">Explore 100+ programs across technology, business, and design.</p></td></tr></table></td></tr><tr><td height="8"></td></tr><tr><td style="padding:14px 16px;border:1px solid #e2e8f0;border-radius:8px;"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td width="44" style="vertical-align:top;padding-right:14px;"><div style="width:36px;height:36px;background:#10b981;border-radius:50%;font-family:Arial,sans-serif;font-size:16px;font-weight:700;color:#fff;text-align:center;line-height:36px;">3</div></td><td style="vertical-align:top;"><strong style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin-bottom:4px;">Talk to an Advisor</strong><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#64748b;line-height:1.5;">Our admission team is here to guide you on the right path.</p></td></tr></table></td></tr></table></td></tr></table>
${spacer(4)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:0 32px 28px;text-align:center;"><table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr><td bgcolor="#10b981" style="border-radius:6px;"><a href="{{cta_link}}" style="display:inline-block;padding:13px 32px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;text-decoration:none;">Explore Courses &rarr;</a></td></tr></table></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;"><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.7;">&copy; ${YR} ${SITENAME2}. All rights reserved.<br><a href="#" style="color:#10b981;text-decoration:underline;">Unsubscribe</a></p></td></tr></table>`,
    },

    // ── 3: Newsletter ─────────────────────────────────────────────────────────
    {
        id: 'newsletter',
        name: 'Newsletter',
        desc: '3-article newsletter with "Read More" links',
        icon: 'newspaper', color: '#8b5cf6',
        html: `
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#8b5cf6" style="background-color:#8b5cf6;padding:22px 32px;text-align:center;border-radius:8px 8px 0 0;"><span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;color:#ffffff;">${SITENAME2} Newsletter</span><p style="margin:8px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.8);">Your monthly digest of education insights</p></td></tr></table>
${spacer(8)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:24px 32px 8px;"><h2 style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;color:#0f172a;">In This Issue</h2><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#64748b;">Hello {{name}}, here's what's new this month.</p></td></tr></table>
${divider()}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:20px 32px;"><img src="https://placehold.co/540x200/eff6ff/8b5cf6?text=Article+1+Image" alt="Article" style="max-width:100%;height:auto;display:block;border-radius:6px;margin-bottom:14px;border:0;"><h3 style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:#0f172a;line-height:1.3;">Top 5 Skills Employers Look for in 2025</h3><p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#64748b;line-height:1.65;">The job market is evolving rapidly. We asked 200+ hiring managers what skills they prioritise when reviewing candidates...</p><a href="#" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#8b5cf6;text-decoration:none;">Read More &rarr;</a></td></tr></table>
${divider()}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:20px 32px;"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td width="50%" style="padding-right:12px;vertical-align:top;"><h3 style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#0f172a;line-height:1.3;">New Course: Data Science Fundamentals</h3><p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#64748b;line-height:1.6;">Master Python, statistics, and machine learning in just 12 weeks.</p><a href="#" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#8b5cf6;text-decoration:none;">Learn More &rarr;</a></td><td width="50%" style="padding-left:12px;vertical-align:top;"><h3 style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#0f172a;line-height:1.3;">Student Success Story: From Teacher to Tech Lead</h3><p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#64748b;line-height:1.6;">How Priya used our program to land a role at a Fortune 500 company.</p><a href="#" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#8b5cf6;text-decoration:none;">Read Story &rarr;</a></td></tr></table></td></tr></table>
${spacer(4)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#f8fafc" style="background-color:#f8fafc;padding:20px 32px 24px;border-top:1px solid #e2e8f0;border-radius:0 0 8px 8px;text-align:center;"><p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;line-height:1.6;">You received this because you subscribed to ${SITENAME2} Newsletter.<br><a href="#" style="color:#8b5cf6;text-decoration:underline;">Unsubscribe</a>&nbsp;&middot;&nbsp;<a href="#" style="color:#8b5cf6;text-decoration:underline;">Manage Preferences</a></p><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;">&copy; ${YR} ${SITENAME2}. All rights reserved.</p></td></tr></table>`,
    },

    // ── 4: Event Invitation ───────────────────────────────────────────────────
    {
        id: 'event-invitation',
        name: 'Event Invitation',
        desc: 'Webinar or open day invitation with RSVP button',
        icon: 'event', color: '#f59e0b',
        html: `
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#f59e0b" style="background-color:#f59e0b;padding:48px 32px;text-align:center;border-radius:8px 8px 0 0;"><div style="font-size:48px;margin-bottom:12px;">🎟️</div><h1 style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:28px;font-weight:800;color:#ffffff;line-height:1.3;">You're Invited!</h1><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:16px;color:rgba(255,255,255,0.92);line-height:1.6;">Join us for an exclusive event</p></td></tr></table>
${spacer(8)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:28px 32px 20px;"><h2 style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;color:#0f172a;">{{event_name}}</h2><p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.7;">Dear {{name}},<br><br>We are delighted to invite you to an exclusive event where you will have the opportunity to connect with our faculty, explore our programs, and get your questions answered directly by industry experts.</p><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#fffbeb" style="background-color:#fffbeb;padding:20px 24px;border-radius:8px;border:1px solid #fde68a;"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td width="50%" style="padding-right:12px;"><p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;">Date &amp; Time</p><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#0f172a;">{{event_date}}<br>{{event_time}}</p></td><td width="50%" style="padding-left:12px;border-left:1px solid #fde68a;"><p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;">Venue</p><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#0f172a;">{{event_venue}}</p></td></tr></table></td></tr></table></td></tr></table>
${spacer(4)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:0 32px 28px;text-align:center;"><table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr><td bgcolor="#f59e0b" style="border-radius:6px;"><a href="{{cta_link}}" style="display:inline-block;padding:13px 36px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;text-decoration:none;">RSVP Now &rarr;</a></td></tr></table><p style="margin:12px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#94a3b8;">Limited seats available. Register early to secure your spot.</p></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;"><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.7;">&copy; ${YR} ${SITENAME2}. All rights reserved.<br><a href="#" style="color:#f59e0b;text-decoration:underline;">Unsubscribe</a></p></td></tr></table>`,
    },

    // ── 5: Discount / Offer ───────────────────────────────────────────────────
    {
        id: 'discount-offer',
        name: 'Discount / Offer',
        desc: 'Limited time discount with coupon code',
        icon: 'local_offer', color: '#ef4444',
        html: `
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#ef4444" style="background-color:#ef4444;padding:48px 32px;text-align:center;border-radius:8px 8px 0 0;"><p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:rgba(255,255,255,0.85);text-transform:uppercase;letter-spacing:.1em;">Limited Time Offer</p><h1 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:56px;font-weight:800;color:#ffffff;line-height:1;">{{discount}}% OFF</h1><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:16px;color:rgba(255,255,255,0.9);line-height:1.6;">On {{course_name}} — today only!</p></td></tr></table>
${spacer(8)}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:24px 32px 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.75;"><p style="margin:0 0 16px;"><strong>Hi {{name}},</strong></p><p style="margin:0;">Don't miss this exclusive offer! For a limited time, we're offering a massive discount on our most popular course. Use the coupon code below at checkout to claim your savings.</p></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:0 32px 24px;text-align:center;"><table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;border:2px dashed #ef4444;border-radius:8px;overflow:hidden;"><tr><td style="padding:16px 40px;"><p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;">Your Coupon Code</p><p style="margin:0;font-family:'Courier New',monospace;font-size:26px;font-weight:700;color:#ef4444;letter-spacing:.15em;">{{coupon_code}}</p></td></tr></table></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#fff5f5" style="background-color:#fff5f5;padding:24px 32px;border-top:1px solid #fecaca;text-align:center;"><p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#64748b;">Original Price: <s style="color:#94a3b8;">{{original_price}}</s></p><p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:30px;font-weight:800;color:#ef4444;">{{price}}</p><table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr><td bgcolor="#ef4444" style="border-radius:6px;"><a href="{{cta_link}}" style="display:inline-block;padding:13px 36px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;text-decoration:none;">Claim My Discount &rarr;</a></td></tr></table><p style="margin:14px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#ef4444;font-weight:600;">⚠️ Offer expires {{expiry_date}}. Don't wait!</p></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;"><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.7;">&copy; ${YR} ${SITENAME2}. All rights reserved.<br><a href="#" style="color:#ef4444;text-decoration:underline;">Unsubscribe</a>&nbsp;&middot;&nbsp;<a href="#" style="color:#ef4444;text-decoration:underline;">Privacy Policy</a></p></td></tr></table>`,
    },

    // ── 6: Product / Program Promotion ───────────────────────────────────────
    {
        id: 'product-promotion',
        name: 'Product Promotion',
        desc: 'Showcase a product/program with image and features',
        icon: 'campaign', color: '#0f172a',
        html: `
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#0f172a" style="background-color:#0f172a;padding:22px 32px;text-align:center;border-radius:8px 8px 0 0;"><span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;color:#ffffff;">${SITENAME2}</span></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td><img src="https://placehold.co/600x250/f1f5f9/64748b?text=Program+Banner+Image" alt="Program" style="width:100%;max-width:600px;height:auto;display:block;border:0;"></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:28px 32px 20px;"><h1 style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:26px;font-weight:800;color:#0f172a;line-height:1.3;">{{course_name}}</h1><p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.75;">Dear {{name}},<br><br>We're proud to introduce our flagship program tailored for ambitious professionals. Gain in-demand skills, earn an industry-recognised certification, and join a community of 50,000+ alumni.</p><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:6px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;">✅&nbsp; 100% online — learn from anywhere</td></tr><tr><td style="padding:6px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;">✅&nbsp; Live Q&amp;A sessions with instructors</td></tr><tr><td style="padding:6px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;">✅&nbsp; Certificate recognised by 500+ companies</td></tr><tr><td style="padding:6px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;">✅&nbsp; Dedicated career support &amp; placement</td></tr></table></td></tr></table>
${divider()}
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:24px 32px;"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td width="50%" style="padding-right:16px;vertical-align:middle;"><p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.06em;">Starting from</p><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:30px;font-weight:800;color:${BRAND2};">{{price}}</p><p style="margin:4px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">EMI options available</p></td><td width="50%" style="text-align:right;vertical-align:middle;"><table cellpadding="0" cellspacing="0" border="0" style="margin-left:auto;"><tr><td bgcolor="${BRAND2}" style="border-radius:6px;"><a href="{{cta_link}}" style="display:inline-block;padding:13px 28px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;text-decoration:none;">Apply Now &rarr;</a></td></tr></table></td></tr></table></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#f8fafc" style="background-color:#f8fafc;padding:16px 32px 20px;border-top:1px solid #e2e8f0;border-radius:0 0 8px 8px;text-align:center;"><p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.7;">&copy; ${YR} ${SITENAME2}. All rights reserved.<br><a href="#" style="color:${BRAND2};text-decoration:underline;">Unsubscribe</a>&nbsp;&middot;&nbsp;<a href="#" style="color:${BRAND2};text-decoration:underline;">Privacy Policy</a></p></td></tr></table>`,
    },
];
@endverbatim

// ── Render template cards ─────────────────────────────────────────────────────
function renderTemplateCards() {
    const grid = document.getElementById('templateGrid');
    if (!grid) return;
    grid.innerHTML = EMAIL_TEMPLATES.map(function (tpl) {
        return `<div class="col-lg-4 col-md-6">
            <div class="tpl-card" onclick="loadEmailTemplate('${tpl.id}')">
                <div class="tpl-card-header" style="background:${tpl.color}15;">
                    <span class="material-icons" style="font-size:36px;color:${tpl.color};">${tpl.icon}</span>
                </div>
                <div class="tpl-card-body">
                    <p class="tpl-card-title">${tpl.name}</p>
                    <p class="tpl-card-desc">${tpl.desc}</p>
                </div>
            </div>
        </div>`;
    }).join('');
}

// ── Load template into GrapesJS ───────────────────────────────────────────────
window.loadEmailTemplate = function (id) {
    var tpl = EMAIL_TEMPLATES.find(function (t) { return t.id === id; });
    if (!tpl) return;

    var currentHtml = (typeof editor !== 'undefined') ? editor.getHtml() : '';
    var hasContent = currentHtml.replace(/<[^>]+>/g, '').trim().length > 0;

    if (hasContent && !confirm('This will replace your current design. Continue?')) return;

    if (typeof editor !== 'undefined') {
        editor.setComponents(tpl.html.trim());
        editor.setStyle('');
    }
};

// ── Templates button ──────────────────────────────────────────────────────────
var btnTemplates = document.getElementById('btnTemplates');
if (btnTemplates) {
    btnTemplates.addEventListener('click', function () {
        renderTemplateCards();
        var modal = new bootstrap.Modal(document.getElementById('templatesModal'));
        modal.show();
    });
}

// ── Send Test Email ───────────────────────────────────────────────────────────
var btnSendTest = document.getElementById('btnSendTest');
if (btnSendTest) {
    btnSendTest.addEventListener('click', function () {
        document.getElementById('testEmailAddress').value = '';
        var msg = document.getElementById('testEmailMsg');
        msg.className = 'alert d-none mb-0';
        msg.textContent = '';
        new bootstrap.Modal(document.getElementById('sendTestModal')).show();
    });
}

var btnSendTestConfirm = document.getElementById('btnSendTestConfirm');
if (btnSendTestConfirm) {
    btnSendTestConfirm.addEventListener('click', async function () {
        var email = document.getElementById('testEmailAddress').value.trim();
        var msg   = document.getElementById('testEmailMsg');

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            msg.className = 'alert alert-danger mb-0';
            msg.textContent = 'Please enter a valid email address.';
            return;
        }

        var rawHtml = (typeof editor !== 'undefined') ? editor.getHtml() : '';
        var css     = (typeof editor !== 'undefined') ? editor.getCss({ avoidProtected: true }) : '';
        var bm = rawHtml.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        var body = bm ? bm[1] : rawHtml;
        body = inlineCssIntoHtml(body, css);

        btnSendTestConfirm.disabled = true;
        btnSendTestConfirm.textContent = 'Sending...';

        try {
            var res = await fetch(@json(route('admin.email-templates.send-test')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF2 },
                body: JSON.stringify({ email: email, subject: 'Test Email — ' + (document.getElementById('name') ? document.getElementById('name').value : 'Template'), body: body }),
            });
            var data = await res.json();
            if (data.ok) {
                msg.className = 'alert alert-success mb-0';
                msg.textContent = 'Test email sent to ' + email + ' successfully!';
            } else {
                msg.className = 'alert alert-danger mb-0';
                msg.textContent = data.error || 'Failed to send test email.';
            }
        } catch (e) {
            msg.className = 'alert alert-danger mb-0';
            msg.textContent = 'Network error. Please try again.';
        }

        btnSendTestConfirm.disabled = false;
        btnSendTestConfirm.innerHTML = '<span class="material-icons align-middle me-1" style="font-size:15px;">send</span>Send Test';
    });
}

})();
</script>
@endpush
