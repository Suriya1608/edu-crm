@php
    $lead           = $followup->lead;
    $leadName       = $lead?->name       ?? 'N/A';
    $leadCode       = $lead?->lead_code  ?? 'N/A';
    $telecallerName = $lead?->assignedUser?->name ?? ($followup->user?->name ?? 'Unassigned');
    $followupDate   = optional($followup->next_followup)->format('d M Y') ?? 'N/A';
    $actionUrl      = $actionUrl ?? route('manager.followups.missed');
@endphp

@extends('emails.layout')

@section('email-title', 'Missed Follow-up Escalation')

@section('header-subtitle')
<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:500;color:rgba(255,255,255,0.80);letter-spacing:.3px;display:block;margin-top:8px;">
    Follow-up Escalation Alert
</span>
@endsection

@section('content')
{{-- Alert bar --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 16px;">
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
</table>

<p style="margin:0 0 20px 0;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#0f172a;">Hello,</p>
<p style="margin:0 0 24px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.7;">
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
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td bgcolor="#c62828" style="border-radius:7px;">
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
@endsection

@section('footer-note')
<tr>
    <td align="center" style="padding-bottom:10px;">
        <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#cbd5e1;line-height:1.5;">
            If you're having trouble clicking the button, copy and paste the URL below:<br>
            <a href="{{ $actionUrl }}" style="color:#c62828;word-break:break-all;text-decoration:none;">{{ $actionUrl }}</a>
        </p>
    </td>
</tr>
@endsection
