@extends('emails.layout')

@section('email-title', 'Your Account Credentials')

@section('header-subtitle')
<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:500;color:rgba(255,255,255,0.80);letter-spacing:.3px;display:block;margin-top:8px;">
    Your account has been created
</span>
@endsection

@section('content')
<p style="font-size:16px;color:#0f172a;font-weight:600;margin:0 0 10px 0;">Hello, {{ $userName }}</p>
<p style="font-size:14px;color:#64748b;line-height:1.6;margin:0 0 28px 0;">
    Your <span style="display:inline-block;background:#c62828;color:#fff;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding:3px 10px;border-radius:20px;">{{ ucfirst($role) }}</span>
    account is ready. Use the credentials below to log in.
</p>

{{-- Credentials box --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:#fff5f5;border:1px solid #fecaca;border-radius:10px;margin-bottom:28px;">
    <tr>
        <td style="padding:24px 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding-bottom:16px;border-bottom:1px solid #fde8e8;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:4px;">Login URL</span>
                        <a href="{{ $loginUrl }}" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#c62828;font-weight:600;word-break:break-all;">{{ $loginUrl }}</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 0;border-bottom:1px solid #fde8e8;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:4px;">Email / Username</span>
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;font-weight:600;">{{ $userEmail }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top:16px;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:4px;">Password</span>
                        <span style="font-family:'Courier New',monospace;font-size:17px;color:#c62828;letter-spacing:2px;font-weight:700;">{{ $plainPassword }}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Login button --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td bgcolor="#c62828" style="border-radius:8px;">
                        <a href="{{ $loginUrl }}"
                           style="display:inline-block;padding:13px 36px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;">
                            Log In to Your Account
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:#fff7ed;border-left:3px solid #f59e0b;border-radius:4px;">
    <tr>
        <td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#92400e;line-height:1.5;">
            For security, please change your password after your first login. Do not share your credentials with anyone.
        </td>
    </tr>
</table>
@endsection

@section('footer-note')
<tr>
    <td align="center" style="padding-bottom:10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">
        This is an automated message from <a href="{{ $siteUrl }}" style="color:#c62828;text-decoration:none;">{{ $siteName }}</a>. Please do not reply to this email.
    </td>
</tr>
@endsection
