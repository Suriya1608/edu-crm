@php
    $isCritical = ($level ?? 1) >= 2;
    $accentColor = $isCritical ? '#dc2626' : '#ea580c';
    $bgColor     = $isCritical ? '#fef2f2' : '#fff7ed';
    $borderColor = $isCritical ? '#fecaca' : '#fed7aa';
    $badge       = $isCritical ? 'CRITICAL — Manager SLA Breach' : 'WARNING — Telecaller SLA Breach';
    $siteUrl     = rtrim(\App\Models\Setting::get('site_url', config('app.url')), '/');
@endphp

@extends('emails.layout')

@section('email-title', $title ?? 'SLA Escalation Alert')

@section('header-subtitle')
<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:500;color:rgba(255,255,255,0.80);letter-spacing:.3px;display:block;margin-top:8px;">
    SLA Escalation Alert
</span>
@endsection

@section('content')

{{-- Alert badge --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:22px;">
    <tr>
        <td style="background-color:{{ $bgColor }};border:1.5px solid {{ $borderColor }};border-radius:8px;padding:12px 16px;">
            <span style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:{{ $accentColor }};letter-spacing:.06em;text-transform:uppercase;">
                {{ $badge }}
            </span>
        </td>
    </tr>
</table>

<p style="margin:0 0 6px 0;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#0f172a;">Hello,</p>
<p style="margin:0 0 24px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.7;">
    {{ $message ?? 'A lead has breached its SLA and requires immediate attention.' }}
</p>

{{-- Lead detail card --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:28px;">
    <tr>
        <td style="padding:20px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding:6px 0;border-bottom:1px solid #f1f5f9;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Lead</span><br>
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;font-weight:700;">{{ $leadName ?? 'N/A' }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 0 6px;border-bottom:1px solid #f1f5f9;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Lead Code</span><br>
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;font-weight:600;">{{ $leadCode ?? 'N/A' }}</span>
                    </td>
                </tr>
                @if (!empty($telecallerName))
                <tr>
                    <td style="padding:10px 0 6px;border-bottom:1px solid #f1f5f9;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Telecaller</span><br>
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;font-weight:600;">{{ $telecallerName }}</span>
                    </td>
                </tr>
                @endif
                @if (!empty($managerName))
                <tr>
                    <td style="padding:10px 0 6px;border-bottom:1px solid #f1f5f9;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Manager</span><br>
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0f172a;font-weight:600;">{{ $managerName }}</span>
                    </td>
                </tr>
                @endif
                <tr>
                    <td style="padding:10px 0 6px;border-bottom:1px solid #f1f5f9;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Escalation Level</span><br>
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:{{ $accentColor }};">
                            {{ $isCritical ? 'Level 2 — Admin / Report Viewer' : 'Level 1 — Manager' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 0 4px;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Escalated At</span><br>
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#ef4444;font-weight:700;">{{ $escalatedAt ?? now()->format('d M Y, h:i A') }}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- CTA button --}}
@if (!empty($actionUrl))
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td bgcolor="{{ $accentColor }}" style="border-radius:7px;">
                        <a href="{{ $actionUrl }}"
                           style="display:inline-block;padding:13px 36px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:7px;letter-spacing:.2px;">
                            Review Lead Now
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endif

@endsection

@section('footer-note')
@if (!empty($actionUrl))
<tr>
    <td align="center" style="padding-bottom:10px;">
        <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#cbd5e1;line-height:1.5;">
            If the button doesn't work, copy and paste this URL:<br>
            <a href="{{ $actionUrl }}" style="color:#c9a227;word-break:break-all;text-decoration:none;">{{ $actionUrl }}</a>
        </p>
    </td>
</tr>
@endif
@endsection
