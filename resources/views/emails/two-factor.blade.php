@extends('emails.layout')

@section('email-title', 'Login Verification Code')

@section('header-subtitle')
<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:500;color:rgba(255,255,255,0.80);letter-spacing:.3px;display:block;margin-top:8px;">
    Login Verification
</span>
@endsection

@section('content')
<p style="font-size:16px;color:#0f172a;font-weight:600;margin:0 0 12px 0;">Hello, {{ $userName }}</p>
<p style="font-size:14px;color:#64748b;line-height:1.6;margin:0 0 28px 0;">
    Use the verification code below to complete your login. This code is valid for <strong>10 minutes</strong>.
</p>

{{-- OTP box --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
    <tr>
        <td align="center"
            style="background-color:#fff5f5;border:2px dashed #c62828;border-radius:10px;padding:24px 20px;">
            <span style="font-family:'Courier New',monospace;font-size:40px;font-weight:800;letter-spacing:12px;color:#c62828;display:block;">
                {{ $otp }}
            </span>
            <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;display:block;margin-top:10px;">
                Expires in 10 minutes
            </span>
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="background-color:#fff7ed;border-left:3px solid #f59e0b;border-radius:4px;padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#92400e;line-height:1.5;">
            If you did not attempt to log in, please ignore this email and consider changing your password immediately.
        </td>
    </tr>
</table>
@endsection

@section('footer-note')
<tr>
    <td align="center" style="padding-bottom:10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">
        This is an automated message. Please do not reply to this email.
    </td>
</tr>
@endsection
