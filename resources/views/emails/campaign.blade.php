<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailSubject }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f6f7f8;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;background-color:#f6f7f8;">
    <tr>
        <td align="center" style="padding:30px 16px;">

            {{-- Outer card --}}
            <table width="600" cellpadding="0" cellspacing="0" border="0" role="presentation"
                   style="border-collapse:collapse;max-width:600px;width:100%;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:8px;">

                {{-- Header --}}
                <tr>
                    <td bgcolor="#137fec" style="background-color:#137fec;padding:24px 32px;border-radius:8px 8px 0 0;">
                        <h1 style="margin:0;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;line-height:1.3;mso-line-height-rule:exactly;">
                            {{ \App\Models\Setting::get('site_name', config('app.name')) }}
                        </h1>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:32px;color:#0f172a;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;mso-line-height-rule:exactly;">
                        {!! $emailBody !!}
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td bgcolor="#f6f7f8" style="background-color:#f6f7f8;padding:16px 32px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;text-align:center;border-top:1px solid #e2e8f0;border-radius:0 0 8px 8px;">
                        &copy; {{ date('Y') }} {{ \App\Models\Setting::get('site_name', config('app.name')) }}. All rights reserved.
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>
</body>
</html>
