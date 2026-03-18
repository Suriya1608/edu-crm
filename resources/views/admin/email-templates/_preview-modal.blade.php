{{-- Email Preview Modal --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:740px;">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:0;box-shadow:0 20px 60px rgba(0,0,0,.18);">
            <div class="modal-header" style="background:#0f172a;border:0;padding:14px 20px;">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="modal-title text-white mb-0 d-flex align-items-center gap-2" id="previewModalLabel">
                        <span class="material-icons" style="font-size:18px;color:#93c5fd;">visibility</span>
                        Email Preview
                    </h5>
                    <span class="badge" style="background:#1e293b;color:#94a3b8;font-size:11px;font-weight:600;">
                        How recipients will see this email
                    </span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background:#f1f5f9;">
                {{-- Device switcher bar --}}
                <div class="d-flex align-items-center justify-content-center gap-2 py-2"
                     style="background:#1e293b;border-bottom:1px solid #334155;">
                    <button type="button" id="previewDesktopBtn"
                        class="btn btn-sm active"
                        style="font-size:12px;padding:4px 12px;border-radius:20px;color:#e2e8f0;background:#334155;border:0;"
                        onclick="switchPreview('desktop')">
                        <span class="material-icons me-1" style="font-size:14px;vertical-align:middle;">computer</span>Desktop
                    </button>
                    <button type="button" id="previewMobileBtn"
                        class="btn btn-sm"
                        style="font-size:12px;padding:4px 12px;border-radius:20px;color:#94a3b8;background:transparent;border:0;"
                        onclick="switchPreview('mobile')">
                        <span class="material-icons me-1" style="font-size:14px;vertical-align:middle;">phone_android</span>Mobile
                    </button>
                </div>
                {{-- Iframe container --}}
                <div id="previewIframeWrap" style="overflow:auto;max-height:78vh;padding:16px;display:flex;justify-content:center;background:#e8ecf0;">
                    <iframe id="previewIframe"
                        style="width:100%;max-width:680px;height:680px;border:0;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);background:#f6f7f8;transition:max-width .3s,height .3s;"
                        title="Email Preview"
                        srcdoc="<html><body style='font-family:Arial,sans-serif;color:#94a3b8;text-align:center;padding:60px 20px;background:#f6f7f8;'>Loading preview…</body></html>">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchPreview(mode) {
    const iframe     = document.getElementById('previewIframe');
    const desktopBtn = document.getElementById('previewDesktopBtn');
    const mobileBtn  = document.getElementById('previewMobileBtn');

    if (mode === 'mobile') {
        iframe.style.maxWidth  = '390px';
        iframe.style.height    = '760px';
        mobileBtn.style.background  = '#334155';
        mobileBtn.style.color       = '#e2e8f0';
        desktopBtn.style.background = 'transparent';
        desktopBtn.style.color      = '#94a3b8';
    } else {
        iframe.style.maxWidth  = '680px';
        iframe.style.height    = '680px';
        desktopBtn.style.background = '#334155';
        desktopBtn.style.color      = '#e2e8f0';
        mobileBtn.style.background  = 'transparent';
        mobileBtn.style.color       = '#94a3b8';
    }
}
</script>
