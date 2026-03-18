@php
    $siteName    = \App\Models\Setting::get('site_name', config('app.name'));
    $siteLogoRaw = \App\Models\Setting::get('site_logo');
    $logoUrl     = $siteLogoRaw
        ? rtrim(config('app.url'), '/') . '/storage/' . $siteLogoRaw
        : null;

    $fbUrl  = \App\Models\Setting::get('social_facebook', '');
    $igUrl  = \App\Models\Setting::get('social_instagram', '');
    $liUrl  = \App\Models\Setting::get('social_linkedin', '');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $emailSubject }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f6f7f8; }
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; }
            .body-pad { padding: 24px 16px !important; }
            .hdr-pad { padding: 18px 16px !important; }
            .ftr-pad { padding: 16px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f6f7f8;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f7f8;">
    <tr>
        <td align="center" style="padding:32px 16px 24px;">

            <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:600px;width:100%;background-color:#ffffff;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 2px 10px rgba(15,23,42,0.08);">

                {{-- ── Header ── --}}
                <tr>
                    <td class="hdr-pad" bgcolor="#2563EB"
                        style="background-color:#2563EB;padding:0;border-radius:10px 10px 0 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:24px 32px 20px;text-align:center;background-color:#2563EB;border-radius:10px 10px 0 0;">
                                    @if ($logoUrl)
                                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}"
                                             width="150" style="max-width:150px;max-height:52px;height:auto;display:block;margin:0 auto 10px;border:0;">
                                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;color:#bfdbfe;letter-spacing:.4px;text-transform:uppercase;display:block;">
                                            {{ $siteName }}
                                        </span>
                                    @else
                                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:26px;font-weight:800;color:#ffffff;letter-spacing:-.5px;display:block;">
                                            {{ $siteName }}
                                        </span>
                                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:400;color:#bfdbfe;letter-spacing:1px;text-transform:uppercase;display:block;margin-top:4px;">
                                            Education CRM
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            {{-- Header accent bar --}}
                            <tr>
                                <td height="4" style="background-color:#1d4ed8;font-size:0;line-height:0;">&nbsp;</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ── Body ── --}}
                <tr>
                    <td class="body-pad"
                        style="padding:32px 36px 28px;color:#0f172a;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;mso-line-height-rule:exactly;">
                        {!! $emailBody !!}
                    </td>
                </tr>

                {{-- ── Footer ── --}}
                <tr>
                    <td class="ftr-pad" bgcolor="#f8fafc"
                        style="background-color:#f8fafc;padding:20px 32px 24px;border-top:1px solid #e2e8f0;border-radius:0 0 10px 10px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

                            {{-- Footer logo --}}
                            <tr>
                                <td align="center" style="padding-bottom:12px;">
                                    @if ($logoUrl)
                                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}"
                                             style="max-width:90px;max-height:32px;height:auto;display:inline-block;border:0;opacity:0.75;">
                                    @else
                                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#64748b;">
                                            {{ $siteName }}
                                        </span>
                                    @endif
                                </td>
                            </tr>

                            @if ($fbUrl || $igUrl || $liUrl)
                            {{-- Social icons --}}
                            <tr>
                                <td align="center" style="padding-bottom:14px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            @if ($fbUrl)
                                            <td width="40" align="center" style="padding:0 4px;">
                                                <a href="{{ $fbUrl }}" target="_blank" style="text-decoration:none;display:block;">
                                                    <img src="https://cdn-icons-png.flaticon.com/512/5968/5968764.png"
                                                         width="28" height="28" alt="Facebook"
                                                         style="display:block;border:0;border-radius:6px;">
                                                </a>
                                            </td>
                                            @endif
                                            @if ($igUrl)
                                            <td width="40" align="center" style="padding:0 4px;">
                                                <a href="{{ $igUrl }}" target="_blank" style="text-decoration:none;display:block;">
                                                    <img src="https://cdn-icons-png.flaticon.com/512/2111/2111463.png"
                                                         width="28" height="28" alt="Instagram"
                                                         style="display:block;border:0;border-radius:6px;">
                                                </a>
                                            </td>
                                            @endif
                                            @if ($liUrl)
                                            <td width="40" align="center" style="padding:0 4px;">
                                                <a href="{{ $liUrl }}" target="_blank" style="text-decoration:none;display:block;">
                                                    <img src="https://cdn-icons-png.flaticon.com/512/2111/2111499.png"
                                                         width="28" height="28" alt="LinkedIn"
                                                         style="display:block;border:0;border-radius:6px;">
                                                </a>
                                            </td>
                                            @endif
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            @endif

                            {{-- Copyright --}}
                            <tr>
                                <td align="center"
                                    style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.6;">
                                    &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
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
