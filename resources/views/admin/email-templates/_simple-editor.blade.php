@php
    $siteName    = \App\Models\Setting::get('site_name', config('app.name'));
    $siteLogoRaw = \App\Models\Setting::get('site_logo');
    $logoUrl     = $siteLogoRaw
        ? rtrim(config('app.url'), '/') . '/storage/' . $siteLogoRaw
        : null;
    $fbUrl = \App\Models\Setting::get('social_facebook', '');
    $igUrl = \App\Models\Setting::get('social_instagram', '');
    $liUrl = \App\Models\Setting::get('social_linkedin', '');
@endphp

<div id="simpleEditorSection">

    {{-- ── Variable chips ───────────────────────────────────────────────────── --}}
    <div class="chart-card mb-3" style="padding:14px 18px;">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;">
                <span class="material-icons align-middle" style="font-size:14px;color:#94a3b8;">data_object</span>
                Click to insert:
            </span>
            @php
            $varChips = [
                '{{name}}'        => 'Recipient name',
                '{{email}}'       => 'Email address',
                '{{course_name}}' => 'Course / campaign name',
                '{{site_name}}'   => 'Site name',
                '{{link}}'        => 'CTA URL',
                '{{year}}'        => 'Current year',
                '{{price}}'       => 'Price',
                '{{event_name}}'  => 'Event name',
                '{{event_date}}'  => 'Event date',
                '{{event_venue}}' => 'Venue',
            ];
            @endphp
            @foreach ($varChips as $var => $label)
            <button type="button"
                class="btn btn-sm simple-var-chip"
                style="font-size:11px;font-family:monospace;padding:2px 8px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;color:#137fec;line-height:1.6;"
                title="{{ $label }}"
                onclick="simpleInsertVar({{ json_encode($var) }})">{{ $var }}</button>
            @endforeach
        </div>
        <div class="mt-2" style="font-size:11px;color:#94a3b8;">
            Example: <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;color:#374151;">Dear @{{name}}, &lt;a href="@{{link}}"&gt;View Brochure&lt;/a&gt;</code>
        </div>
    </div>

    {{-- ── Editor card ──────────────────────────────────────────────────────── --}}
    <div class="chart-card mb-3" style="padding:0;overflow:hidden;">

        {{-- Toolbar --}}
        <div class="d-flex align-items-center justify-content-between gap-2 px-4 py-2"
             style="border-bottom:1px solid #e2e8f0;background:#fafafa;">
            <div class="d-flex align-items-center gap-2">
                <span class="material-icons" style="font-size:16px;color:#94a3b8;">mail</span>
                <span style="font-size:12px;font-weight:600;color:#64748b;">Email Body Editor</span>
                <span class="badge rounded-pill" style="background:#f1f5f9;color:#64748b;font-size:10px;font-weight:600;">
                    Use &#123;&#123;variables&#125;&#125; for personalisation
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="btnSimplePreview"
                        class="btn btn-sm btn-outline-secondary"
                        style="font-size:12px;padding:4px 12px;"
                        data-bs-toggle="modal" data-bs-target="#previewModal">
                    <span class="material-icons me-1" style="font-size:14px;vertical-align:middle;">visibility</span>Preview
                </button>
            </div>
        </div>

        {{-- Textarea --}}
        <div style="padding:0;">
            <textarea id="simpleBodyTextarea"
                      placeholder="Write your email here. Example:&#10;&#10;Dear @{{name}},&#10;&#10;Thank you for your interest in @{{course_name}}.&#10;&#10;Click here to learn more: &lt;a href=&quot;@{{link}}&quot;&gt;View Brochure&lt;/a&gt;&#10;&#10;Regards,&#10;The Team"
                      spellcheck="true"
                      style="width:100%;min-height:420px;resize:vertical;border:0;outline:0;
                             padding:20px 24px;font-size:14px;line-height:1.8;
                             font-family:'Manrope',Arial,sans-serif;
                             color:#0f172a;background:#ffffff;display:block;
                             border-bottom:1px solid #e2e8f0;box-sizing:border-box;"></textarea>
        </div>

        {{-- Footer hint --}}
        <div class="px-4 py-2" style="background:#fafafa;border-top:1px solid #f1f5f9;">
            <span style="font-size:11px;color:#94a3b8;">
                <span class="material-icons align-middle" style="font-size:13px;">info</span>
                Write only the email body. The site header, footer and logo are added automatically when sent.
                You may use <code style="font-size:10px;">&lt;a href="@{{link}}"&gt;text&lt;/a&gt;</code> for links.
            </span>
        </div>
    </div>

</div>

@push('scripts')
<script>
(function () {
'use strict';

// ── Variable insertion ────────────────────────────────────────────────────────
window.simpleInsertVar = function (variable) {
    const ta = document.getElementById('simpleBodyTextarea');
    if (!ta) return;
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + variable + ta.value.substring(end);
    ta.selectionStart = ta.selectionEnd = start + variable.length;
    ta.focus();
};

// ── Preview ───────────────────────────────────────────────────────────────────
const btnSimplePreview = document.getElementById('btnSimplePreview');
if (btnSimplePreview) {
    btnSimplePreview.addEventListener('click', function () {
        const content  = document.getElementById('simpleBodyTextarea').value.trim();
        const siteName = @json($siteName);
        const year     = new Date().getFullYear();
        const logoUrl  = @json($logoUrl ?? null);
        const fbUrl    = @json($fbUrl);
        const igUrl    = @json($igUrl);
        const liUrl    = @json($liUrl);

        // ── Replace template variables with sample values ─────────────────────
        const preview = content
            .replace(/\{\{name\}\}/g,        'John Doe')
            .replace(/\{\{email\}\}/g,        'john@example.com')
            .replace(/\{\{course_name\}\}/g,  'Sample Course')
            .replace(/\{\{site_name\}\}/g,    siteName)
            .replace(/\{\{link\}\}/g,         '#')
            .replace(/\{\{cta_link\}\}/g,     '#')
            .replace(/\{\{year\}\}/g,         year)
            .replace(/\{\{price\}\}/g,        '5,000')
            .replace(/\{\{event_name\}\}/g,   'Annual Event')
            .replace(/\{\{event_date\}\}/g,   '1 April 2026')
            .replace(/\{\{event_venue\}\}/g,  'Main Hall')
            .replace(/\n/g, '<br>');

        // ── Header HTML ───────────────────────────────────────────────────────
        const headerHtml = logoUrl
            ? `<img src="${logoUrl}" alt="${siteName}" width="160"
                    style="max-width:160px;max-height:56px;height:auto;display:block;margin:0 auto 10px;border:0;border-radius:4px;">
               <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:600;color:rgba(255,255,255,0.65);letter-spacing:1.5px;text-transform:uppercase;display:block;">
                   ${siteName}
               </span>`
            : `<span style="font-family:Arial,Helvetica,sans-serif;font-size:28px;font-weight:800;color:#ffffff;letter-spacing:-.5px;display:block;margin-bottom:4px;">
                   ${siteName}
               </span>
               <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:500;color:rgba(255,255,255,0.65);letter-spacing:2px;text-transform:uppercase;display:block;">
                   Education &amp; Career Guidance
               </span>`;

        // ── Footer logo HTML ──────────────────────────────────────────────────
        const footerLogoHtml = logoUrl
            ? `<img src="${logoUrl}" alt="${siteName}"
                    style="max-width:100px;max-height:36px;height:auto;display:inline-block;border:0;opacity:0.55;border-radius:3px;">`
            : `<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#94a3b8;letter-spacing:.5px;">
                   ${siteName}
               </span>`;

        // ── Social icons HTML ─────────────────────────────────────────────────
        let socialHtml = '';
        if (fbUrl || igUrl || liUrl) {
            const icons = [];
            if (fbUrl) icons.push(`<td width="44" align="center" style="padding:0 5px;">
                <a href="${fbUrl}" target="_blank" style="text-decoration:none;display:block;">
                    <img src="https://cdn-icons-png.flaticon.com/512/5968/5968764.png" width="26" height="26" alt="Facebook"
                         style="display:block;border:0;border-radius:6px;opacity:0.65;">
                </a></td>`);
            if (igUrl) icons.push(`<td width="44" align="center" style="padding:0 5px;">
                <a href="${igUrl}" target="_blank" style="text-decoration:none;display:block;">
                    <img src="https://cdn-icons-png.flaticon.com/512/2111/2111463.png" width="26" height="26" alt="Instagram"
                         style="display:block;border:0;border-radius:6px;opacity:0.65;">
                </a></td>`);
            if (liUrl) icons.push(`<td width="44" align="center" style="padding:0 5px;">
                <a href="${liUrl}" target="_blank" style="text-decoration:none;display:block;">
                    <img src="https://cdn-icons-png.flaticon.com/512/2111/2111499.png" width="26" height="26" alt="LinkedIn"
                         style="display:block;border:0;border-radius:6px;opacity:0.65;">
                </a></td>`);
            socialHtml = `<tr>
                <td align="center" style="padding-bottom:14px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>${icons.join('')}</tr>
                    </table>
                </td>
            </tr>`;
        }

        // ── Full email document (identical structure to emails/campaign.blade.php) ──
        const fullDoc = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Email Preview</title>
<style>
body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
table,td{mso-table-lspace:0pt;mso-table-rspace:0pt;}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;}
table{border-collapse:collapse!important;}
body{height:100%!important;margin:0!important;padding:0!important;width:100%!important;background-color:#eef2f7;}
.email-body p{margin:0 0 16px 0!important;}
.email-body a{color:#137fec!important;text-decoration:underline;}
.email-body h1{font-size:24px!important;font-weight:800;color:#0f172a;margin:0 0 14px;line-height:1.3;}
.email-body h2{font-size:20px!important;font-weight:700;color:#0f172a;margin:0 0 14px;line-height:1.3;}
.email-body h3{font-size:17px!important;font-weight:700;color:#0f172a;margin:0 0 12px;line-height:1.3;}
.email-body h4{font-size:15px!important;font-weight:700;color:#0f172a;margin:0 0 10px;}
.email-body ul,.email-body ol{margin:0 0 16px;padding-left:22px;}
.email-body li{margin-bottom:6px;}
.email-body blockquote{border-left:3px solid #137fec;margin:0 0 16px;padding:10px 16px;color:#475569;background-color:#f0f9ff;}
.email-body strong{font-weight:700;}
.email-body img{max-width:100%!important;height:auto!important;border-radius:6px;}
.email-body hr{border:0;border-top:1px solid #e2e8f0;margin:20px 0;}
</style>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef2f7;">
<tr><td align="center" style="padding:40px 16px 32px;">

  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
         style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 32px rgba(15,23,42,0.12);">

    <!-- HEADER -->
    <tr>
      <td style="background-color:#c62828;background:linear-gradient(135deg,#c62828 0%,#e6a817 100%);padding:0;border-radius:16px 16px 0 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:34px 40px 28px;text-align:center;">
              ${headerHtml}
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Amber accent stripe -->
    <tr>
      <td height="4" style="background-color:#f59e0b;font-size:0;line-height:0;">&nbsp;</td>
    </tr>

    <!-- BODY -->
    <tr>
      <td class="email-body"
          style="padding:36px 40px 32px;color:#374151;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.75;">
        ${preview || '<span style="color:#94a3b8;font-style:italic;">No content yet — type something in the editor.</span>'}
      </td>
    </tr>

    <!-- Footer hairline -->
    <tr>
      <td height="1" style="background-color:#e2e8f0;font-size:0;line-height:0;">&nbsp;</td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="background-color:#f8fafc;padding:24px 40px 28px;border-radius:0 0 16px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

          <!-- Logo -->
          <tr>
            <td align="center" style="padding-bottom:14px;">${footerLogoHtml}</td>
          </tr>

          ${socialHtml}

          <!-- Copyright -->
          <tr>
            <td align="center"
                style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.7;padding-bottom:8px;">
              &copy; ${year} ${siteName}. All rights reserved.
            </td>
          </tr>

          <!-- Footer links -->
          <tr>
            <td align="center"
                style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;">
              <a href="#" style="color:#94a3b8;text-decoration:none;border-bottom:1px solid #e2e8f0;padding-bottom:1px;margin:0 8px;">Unsubscribe</a>
              <span style="color:#cbd5e1;">&#8226;</span>
              <a href="#" style="color:#94a3b8;text-decoration:none;border-bottom:1px solid #e2e8f0;padding-bottom:1px;margin:0 8px;">Privacy Policy</a>
            </td>
          </tr>

        </table>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>`;

        const iframe = document.getElementById('previewIframe');
        if (iframe) iframe.srcdoc = fullDoc;
    });
}

// ── Form submit — populate hidden body input ──────────────────────────────────
const templateForm = document.getElementById('templateForm');
if (templateForm) {
    templateForm.addEventListener('submit', function (e) {
        const content = document.getElementById('simpleBodyTextarea').value.trim();
        if (!content) {
            e.preventDefault();
            alert('Please write some content before saving.');
            document.getElementById('simpleBodyTextarea').focus();
            return;
        }
        // Convert bare newlines to <br> so the sent email renders line breaks correctly
        document.getElementById('hiddenBody').value = content.replace(/\n/g, '<br>');
    });
}

})();
</script>
@endpush
