<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $siteName = \App\Models\Setting::get('site_name', 'Admission CRM');
        $favicon  = \App\Models\Setting::get('site_favicon');
        $logo     = \App\Models\Setting::get('site_logo');
    @endphp

    <title>{{ $siteName }}</title>

    @if ($favicon)
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $favicon) }}">
    @else
        <link rel="icon" type="image/png" href="{{ asset('images/default-favicon.png') }}">
    @endif

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        :root {
            --primary:      #6366f1;
            --primary-dark: #4f46e5;
            --primary-glow: rgba(99,102,241,0.22);
            --border:       #e2e8f0;
            --text-dark:    #0f172a;
            --text-muted:   #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: stretch;
        }

        /* ============ SPLIT WRAPPER ============ */
        .auth-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ============ LEFT BRAND PANEL ============ */
        .auth-left {
            flex: 0 0 44%;
            background: linear-gradient(145deg, #1e1b4b 0%, #312e81 40%, #4c1d95 75%, #6d28d9 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 52px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative blobs */
        .auth-left-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
        }

        .blob-1 { width: 320px; height: 320px; background: rgba(99,102,241,0.35); top: -80px; right: -80px; }
        .blob-2 { width: 260px; height: 260px; background: rgba(139,92,246,0.25); bottom: -60px; left: -60px; }
        .blob-3 { width: 180px; height: 180px; background: rgba(167,139,250,0.2); top: 45%; left: 30%; }

        /* Subtle grid pattern */
        .auth-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .auth-left-content {
            position: relative;
            z-index: 1;
        }

        /* Brand logo + name row */
        .brand-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 40px;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            flex-shrink: 0;
            overflow: hidden;
        }

        .brand-icon img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .brand-icon .material-icons { font-size: 26px; color: #fff; }

        .brand-text-wrap { min-width: 0; }

        .brand-name {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.3px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .brand-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.55);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 3px;
        }

        /* Headline */
        .auth-headline {
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            line-height: 1.25;
            letter-spacing: -0.8px;
            margin-bottom: 14px;
        }

        .auth-headline span {
            background: linear-gradient(90deg, #c4b5fd, #a5f3fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-subtext {
            font-size: 14px;
            color: rgba(255,255,255,0.62);
            line-height: 1.65;
            margin-bottom: 42px;
            max-width: 320px;
        }

        /* Feature bullets */
        .feature-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px; }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255,255,255,0.85);
            font-size: 13.5px;
            font-weight: 500;
        }

        .feature-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon .material-icons { font-size: 18px; color: #c4b5fd; }

        /* Footer */
        .auth-left-footer {
            position: relative;
            z-index: 1;
            font-size: 11.5px;
            color: rgba(255,255,255,0.35);
        }

        /* ============ RIGHT FORM PANEL ============ */
        .auth-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            background: #f1f5f9;
        }

        .auth-card {
            background: #fff;
            border-radius: 20px;
            border: 1.5px solid var(--border);
            padding: 44px 42px;
            width: 100%;
            max-width: 430px;
            box-shadow: 0 8px 40px rgba(15,23,42,0.09);
        }

        /* Top accent line */
        .auth-card::before {
            content: '';
            display: block;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #06b6d4);
            border-radius: 3px 3px 0 0;
            margin: -44px -42px 32px;
            border-radius: 20px 20px 0 0;
        }

        .auth-card-title {
            font-size: 23px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 5px;
            letter-spacing: -0.4px;
        }

        .auth-card-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        /* ============ FORM ELEMENTS ============ */
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .form-control {
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            color: var(--text-dark);
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
            outline: none;
        }

        .form-control.is-invalid { border-color: #ef4444; }

        .input-group .form-control {
            border-right: none;
            border-radius: 10px 0 0 10px;
        }

        .input-group-text {
            background: #fff;
            border: 1.5px solid var(--border);
            border-left: none;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
        }

        .input-group-text:hover { color: var(--primary); }

        .input-group:focus-within .form-control,
        .input-group:focus-within .input-group-text {
            border-color: var(--primary);
        }

        .input-group:focus-within .input-group-text {
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .invalid-feedback { font-size: 12px; color: #ef4444; }

        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }
        .form-check-input:focus   { box-shadow: 0 0 0 3px var(--primary-glow); }

        .form-check-label { font-size: 13px; color: var(--text-muted); }

        /* ============ PRIMARY BUTTON ============ */
        .btn-primary-crm {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border: none;
            border-radius: 11px;
            color: #fff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14.5px;
            font-weight: 700;
            padding: 12px 24px;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
            letter-spacing: 0.1px;
            box-shadow: 0 4px 18px rgba(99,102,241,0.4);
        }

        .btn-primary-crm:hover {
            box-shadow: 0 6px 24px rgba(99,102,241,0.55);
            transform: translateY(-1px);
        }

        .btn-primary-crm:active { transform: scale(0.98); }

        .forgot-link {
            color: var(--primary);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .forgot-link:hover { text-decoration: underline; color: var(--primary-dark); }

        /* ============ ALERTS ============ */
        .auth-alert {
            border-radius: 10px;
            font-size: 13px;
            padding: 11px 14px;
            margin-bottom: 18px;
            border: none;
        }

        .auth-alert-success { background: #d1fae5; color: #065f46; }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 900px) {
            .auth-left { display: none; }
            .auth-right { padding: 28px 16px; }
            .auth-card { padding: 36px 24px; border-radius: 16px; }
            .auth-card::before { margin: -36px -24px 26px; border-radius: 16px 16px 0 0; }
        }
    </style>

    @vite(['resources/js/app.js'])
</head>

<body>
    <div class="auth-wrapper">

        {{-- ====== LEFT: Brand Panel ====== --}}
        <div class="auth-left">
            <div class="auth-left-blob blob-1"></div>
            <div class="auth-left-blob blob-2"></div>
            <div class="auth-left-blob blob-3"></div>

            <div class="auth-left-content">

                {{-- Brand row --}}
                <div class="brand-row">
                    <div class="brand-icon">
                        @if ($logo)
                            <img src="{{ asset('storage/' . $logo) }}" alt="{{ $siteName }}">
                        @else
                            <span class="material-icons">school</span>
                        @endif
                    </div>
                    <div class="brand-text-wrap">
                        <div class="brand-name">{{ $siteName }}</div>
                        <div class="brand-sub">Admin Panel</div>
                    </div>
                </div>

                {{-- Headline --}}
                <h2 class="auth-headline">
                    Power your<br><span>admissions</span><br>smarter.
                </h2>
                <p class="auth-subtext">
                    One platform to manage leads, telecallers, campaigns, and analytics — from first contact to enrollment.
                </p>

                {{-- Features --}}
                <ul class="feature-list">
                    <li class="feature-item">
                        <div class="feature-icon"><span class="material-icons">person_add</span></div>
                        <span>Smart Lead Management</span>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon"><span class="material-icons">support_agent</span></div>
                        <span>Telecaller Performance Tracking</span>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon"><span class="material-icons">bar_chart</span></div>
                        <span>Real-time Reports & Analytics</span>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon"><span class="material-icons">event_note</span></div>
                        <span>Automated Follow-up Reminders</span>
                    </li>
                </ul>
            </div>

            <div class="auth-left-footer">
                &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
            </div>
        </div>

        {{-- ====== RIGHT: Form Panel ====== --}}
        <div class="auth-right">
            <div class="auth-card">
                {{ $slot }}
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
