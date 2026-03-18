@php
    $siteName    = \App\Models\Setting::get('site_name', config('app.name'));
    $siteLogoRaw = \App\Models\Setting::get('site_logo');
    $logoUrl     = $siteLogoRaw
        ? rtrim(config('app.url'), '/') . '/storage/' . $siteLogoRaw
        : null;

    $lead           = $followup->lead;
    $leadName       = $lead?->name       ?? 'N/A';
    $leadCode       = $lead?->lead_code  ?? 'N/A';
    $telecallerName = $lead?->assignedUser?->name ?? ($followup->user?->name ?? 'Unassigned');
    $followupDate   = optional($followup->next_followup)->format('d M Y') ?? 'N/A';
    $actionUrl      = $actionUrl ?? route('manager.followups.missed');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Missed Follow-up Escalation</title>
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
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f6f7f8;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f7f8;">
    <tr>
        <td align="center" style="padding:32px 16px 24px;">

            <table role="presentation" class="email-container" width="560" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:560px;width:100%;background-color:#ffffff;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 2px 10px rgba(15,23,42,0.08);">

                {{-- ── Header ── --}}
                <tr>
                    <td bgcolor="#137fec"
                        style="background-color:#137fec;padding:22px 32px;text-align:center;border-radius:10px 10px 0 0;">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}"
                                 style="max-width:140px;max-height:50px;height:auto;display:inline-block;border:0;">
                        @else
                            <span style="font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;color:#ffffff;">
                                {{ $siteName }}
                            </span>
                        @endif
                    </td>
                </tr>

                {{-- ── Alert bar ── --}}
                <tr>
                    <td bgcolor="#fff7ed"
                        style="background-color:#fff7ed;padding:12px 32px;border-bottom:1px solid #fed7aa;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td width="24" valign="middle" style="padding-right:10px;">
                                    <img src="https://cdn-icons-png.flaticon.com/512/2797/2797387.png"
                                         width="22" height="22" alt="!" style="display:block;border:0;">
                                </td>
                                <td valign="middle"
                                    style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#c2410c;">
                                    Follow-up Missed &amp; Escalated
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ── Body ── --}}
                <tr>
                    <td class="body-pad" style="padding:28px 32px 24px;">

                        <p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#0f172a;">
                            Hello,
                        </p>
                        <p style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.7;">
                            A follow-up has been missed and escalated to manager.
                            Please review the details below and take appropriate action.
                        </p>

                        {{-- Detail card --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:28px;">
                            <tr>
                                <td style="padding:20px 24px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Lead</span><br>
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;font-weight:700;">{{ $leadName }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px 0 6px;border-bottom:1px solid #f1f5f9;">
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Lead Code</span><br>
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;font-weight:600;">{{ $leadCode }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px 0 6px;border-bottom:1px solid #f1f5f9;">
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Telecaller</span><br>
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;font-weight:600;">{{ $telecallerName }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px 0 4px;">
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Follow-up Date</span><br>
                                                <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#ef4444;font-weight:700;">{{ $followupDate }}</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- CTA button --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td align="center" style="padding-bottom:8px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td bgcolor="#137fec" style="border-radius:7px;">
                                                <a href="{{ $actionUrl }}"
                                                   style="display:inline-block;padding:13px 36px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:7px;letter-spacing:.2px;">
                                                    View Missed Follow-ups
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                {{-- ── Footer ── --}}
                <tr>
                    <td bgcolor="#f8fafc"
                        style="background-color:#f8fafc;padding:18px 32px 22px;border-top:1px solid #e2e8f0;border-radius:0 0 10px 10px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="padding-bottom:6px;">
                                    @if ($logoUrl)
                                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}"
                                             style="max-width:80px;max-height:28px;height:auto;display:inline-block;border:0;opacity:0.7;">
                                    @else
                                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#64748b;">{{ $siteName }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td align="center"
                                    style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;line-height:1.6;">
                                    &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-top:10px;">
                                    <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#cbd5e1;line-height:1.5;">
                                        If you're having trouble clicking the button, copy and paste the URL below into your browser:<br>
                                        <a href="{{ $actionUrl }}" style="color:#93c5fd;word-break:break-all;">{{ $actionUrl }}</a>
                                    </p>
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
