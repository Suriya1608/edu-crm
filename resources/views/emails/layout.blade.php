@php
    if (!isset($siteName))    { $siteName = \App\Models\Setting::get('site_name', config('app.name')); }
    if (!isset($logoUrl))     { $_raw = \App\Models\Setting::get('site_logo'); $logoUrl = $_raw ? rtrim(config('app.url'), '/') . '/storage/' . $_raw : null; }
    if (!isset($fbUrl))       { $fbUrl = \App\Models\Setting::get('social_facebook', ''); }
    if (!isset($igUrl))       { $igUrl = \App\Models\Setting::get('social_instagram', ''); }
    if (!isset($liUrl))       { $liUrl = \App\Models\Setting::get('social_linkedin', ''); }
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('email-title', 'Email Notification')</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #eeece8; }

        .email-body p    { margin: 0 0 16px 0 !important; }
        .email-body a    { color: #b8860b !important; text-decoration: underline; }
        .email-body h1   { font-size: 24px !important; font-weight: 800; color: #0f172a; margin: 0 0 14px 0; line-height: 1.3; }
        .email-body h2   { font-size: 20px !important; font-weight: 700; color: #0f172a; margin: 0 0 14px 0; line-height: 1.3; }
        .email-body h3   { font-size: 17px !important; font-weight: 700; color: #0f172a; margin: 0 0 12px 0; line-height: 1.3; }
        .email-body h4   { font-size: 15px !important; font-weight: 700; color: #0f172a; margin: 0 0 10px 0; }
        .email-body ul, .email-body ol { margin: 0 0 16px 0; padding-left: 22px; }
        .email-body li   { margin-bottom: 6px; }
        .email-body blockquote { border-left: 3px solid #b8860b; margin: 0 0 16px 0; padding: 10px 16px; color: #475569; background-color: #fdf8ee; }
        .email-body strong { font-weight: 700; }
        .email-body img  { max-width: 100% !important; height: auto !important; border-radius: 6px; }
        .email-body hr   { border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0; }
        .email-body table { width: 100%; border-collapse: collapse; }

        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; }
            .body-pad        { padding: 28px 20px 24px !important; }
            .hdr-inner       { padding: 22px 20px 20px !important; }
            .ftr-pad         { padding: 22px 20px 26px !important; }
            .hdr-logo-cell   { width: 50px !important; }
            .hdr-logo-cell img { max-width: 44px !important; max-height: 38px !important; }
        }
        @yield('extra-styles')
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eeece8;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eeece8;">
    <tr>
        <td align="center" style="padding:40px 16px 32px;">

            <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,0.18);">

                {{-- ── HEADER: Premium charcoal ── --}}
                <tr>
                    <td bgcolor="#1c1917"
                        style="background-color:#1c1917;background:linear-gradient(135deg,#1c1917 0%,#292524 60%,#1c1917 100%);padding:0;border-radius:16px 16px 0 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td class="hdr-inner" style="padding:26px 36px 22px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            {{-- LEFT: Logo --}}
                                            <td class="hdr-logo-cell" width="80" valign="middle" style="width:80px;">
                                                @if ($logoUrl)
                                                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}"
                                                         width="72"
                                                         style="max-width:72px;max-height:52px;height:auto;display:block;border:0;border-radius:6px;">
                                                @else
                                                    {{-- Monogram fallback --}}
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="48" height="48" align="center" valign="middle"
                                                                style="width:48px;height:48px;background-color:#c9a227;border-radius:10px;">
                                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:800;color:#1c1917;line-height:48px;display:block;">
                                                                    {{ strtoupper(substr($siteName, 0, 1)) }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                @endif
                                            </td>

                                            {{-- CENTER: Site name --}}
                                            <td valign="middle" align="center" style="padding:0 8px;">
                                                <span style="font-family:Georgia,'Times New Roman',serif;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:0.5px;display:block;line-height:1.2;">
                                                    {{ $siteName }}
                                                </span>
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:400;color:#c9a227;letter-spacing:2.5px;text-transform:uppercase;display:block;margin-top:4px;">
                                                    @yield('header-tagline', 'Education &amp; Career Guidance')
                                                </span>
                                                @yield('header-subtitle')
                                            </td>

                                            {{-- RIGHT: spacer to balance logo (keeps site name truly centered) --}}
                                            <td class="hdr-logo-cell" width="80" valign="middle" style="width:80px;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Gold accent stripe --}}
                <tr>
                    <td height="3" style="background:linear-gradient(90deg,#c9a227 0%,#e8c84a 50%,#c9a227 100%);font-size:0;line-height:0;">&nbsp;</td>
                </tr>

                {{-- ── BODY ── --}}
                <tr>
                    <td class="body-pad email-body"
                        style="padding:36px 40px 32px;color:#374151;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.75;mso-line-height-rule:exactly;">
                        @yield('content')
                    </td>
                </tr>

                {{-- ── FOOTER ── --}}
                <tr>
                    <td bgcolor="#1c1917"
                        class="ftr-pad"
                        style="background-color:#1c1917;padding:26px 40px 30px;border-radius:0 0 16px 16px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

                            {{-- Logo / brand --}}
                            <tr>
                                <td align="center" style="padding-bottom:14px;">
                                    @if ($logoUrl)
                                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}"
                                             style="max-width:88px;max-height:32px;height:auto;display:inline-block;border:0;opacity:0.60;border-radius:3px;">
                                    @else
                                        <span style="font-family:Georgia,'Times New Roman',serif;font-size:13px;font-weight:700;color:rgba(255,255,255,0.50);letter-spacing:.5px;">
                                            {{ $siteName }}
                                        </span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Gold divider --}}
                            <tr>
                                <td align="center" style="padding-bottom:14px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td width="40" height="1" style="background-color:#c9a227;font-size:0;line-height:0;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            @if ($fbUrl || $igUrl || $liUrl)
                            {{-- Social icons --}}
                            <tr>
                                <td align="center" style="padding-bottom:14px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            @if ($fbUrl)
                                            <td width="44" align="center" style="padding:0 5px;">
                                                <a href="{{ $fbUrl }}" target="_blank" style="text-decoration:none;display:block;">
                                                    <img src="https://cdn-icons-png.flaticon.com/512/5968/5968764.png"
                                                         width="24" height="24" alt="Facebook"
                                                         style="display:block;border:0;border-radius:5px;opacity:0.50;">
                                                </a>
                                            </td>
                                            @endif
                                            @if ($igUrl)
                                            <td width="44" align="center" style="padding:0 5px;">
                                                <a href="{{ $igUrl }}" target="_blank" style="text-decoration:none;display:block;">
                                                    <img src="https://cdn-icons-png.flaticon.com/512/2111/2111463.png"
                                                         width="24" height="24" alt="Instagram"
                                                         style="display:block;border:0;border-radius:5px;opacity:0.50;">
                                                </a>
                                            </td>
                                            @endif
                                            @if ($liUrl)
                                            <td width="44" align="center" style="padding:0 5px;">
                                                <a href="{{ $liUrl }}" target="_blank" style="text-decoration:none;display:block;">
                                                    <img src="https://cdn-icons-png.flaticon.com/512/2111/2111499.png"
                                                         width="24" height="24" alt="LinkedIn"
                                                         style="display:block;border:0;border-radius:5px;opacity:0.50;">
                                                </a>
                                            </td>
                                            @endif
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            @endif

                            @yield('footer-note')

                            {{-- Copyright --}}
                            <tr>
                                <td align="center"
                                    style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,255,255,0.38);line-height:1.7;padding-bottom:8px;">
                                    &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                                </td>
                            </tr>

                            {{-- Footer links --}}
                            <tr>
                                <td align="center"
                                    style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,255,255,0.38);">
                                    <a href="#" style="color:rgba(255,255,255,0.45);text-decoration:none;border-bottom:1px solid rgba(201,162,39,0.35);padding-bottom:1px;margin:0 8px;">Unsubscribe</a>
                                    <span style="color:rgba(255,255,255,0.25);">&#8226;</span>
                                    <a href="#" style="color:rgba(255,255,255,0.45);text-decoration:none;border-bottom:1px solid rgba(201,162,39,0.35);padding-bottom:1px;margin:0 8px;">Privacy Policy</a>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
