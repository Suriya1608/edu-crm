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
            { name: 'Email', width: '640px', widthMedia: '' },
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

    const year = new Date().getFullYear();
    const fullDoc = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Email Preview</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f6f7f8;margin:0;padding:0;}
    .wrapper{max-width:640px;margin:24px auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;}
    .hdr{background:${BRAND};padding:20px 28px;}.hdr h1{color:#fff;font-size:18px;margin:0;font-family:Arial,sans-serif;font-weight:700;}
    .bdy{padding:28px 32px;color:#0f172a;font-size:15px;line-height:1.7;}
    .ftr{background:#f6f7f8;padding:14px 28px;font-size:12px;color:#64748b;text-align:center;border-top:1px solid #e2e8f0;}
    ${css}
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="hdr"><h1>${SITE_NAME}</h1></div>
    <div class="bdy">${innerHtml}</div>
    <div class="ftr">&copy; ${year} ${SITE_NAME}. All rights reserved.</div>
  </div>
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
        const rawHtml = editor.getHtml();
        const css     = editor.getCss({ avoidProtected: true });

        // Strip outer body wrapper
        const bodyMatch = rawHtml.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        const innerHtml = bodyMatch ? bodyMatch[1] : rawHtml;

        const body = css.trim()
            ? `<style>${css}</style>\n${innerHtml}`
            : innerHtml;

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
</script>
@endpush
