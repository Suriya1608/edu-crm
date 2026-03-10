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

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts: Manrope -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        :root {
            --primary-color: #137fec;
            --primary-dark: #0d6bc9;
            --background-light: #f6f7f8;
            --border-color: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--background-light);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
        }

        /* ---- Split layout ---- */
        .auth-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ---- Left Brand Panel ---- */
        .auth-left {
            flex: 0 0 42%;
            background: linear-gradient(145deg, #137fec 0%, #0d6bc9 60%, #0a58a8 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            padding: 60px 56px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative circles */
        .auth-left::before {
            content: '';
            position: absolute;
            top: -80px;
            right: -80px;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
        }

        .auth-left::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -60px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
        }

        .brand-logo-wrap {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            backdrop-filter: blur(8px);
        }

        .brand-logo-wrap .material-icons {
            font-size: 32px;
            color: #fff;
        }

        .brand-name {
            font-size: 26px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }

        .brand-tagline {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 48px;
            line-height: 1.6;
            max-width: 300px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 500;
        }

        .feature-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon .material-icons {
            font-size: 18px;
            color: #fff;
        }

        .auth-left-footer {
            margin-top: auto;
            padding-top: 48px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.45);
            position: relative;
            z-index: 1;
        }

        /* ---- Right Form Panel ---- */
        .auth-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            background: var(--background-light);
        }

        .auth-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 48px 44px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 4px 32px rgba(19, 127, 236, 0.06);
        }

        .auth-card-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .auth-card-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 32px;
        }

        /* ---- Form elements ---- */
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .form-control {
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 14px;
            font-family: 'Manrope', sans-serif;
            font-size: 14px;
            color: var(--text-dark);
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(19, 127, 236, 0.12);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: #ef4444;
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 10px 0 0 10px;
        }

        .input-group-text {
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-left: none;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
        }

        .input-group-text:hover {
            color: var(--primary-color);
        }

        .input-group:focus-within .form-control,
        .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        .input-group:focus-within .input-group-text {
            box-shadow: 3px 0 0 3px rgba(19, 127, 236, 0.12) inset;
        }

        .invalid-feedback {
            font-size: 12px;
            color: #ef4444;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(19, 127, 236, 0.15);
        }

        .form-check-label {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* ---- Primary Button ---- */
        .btn-primary-crm {
            background: var(--primary-color);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-family: 'Manrope', sans-serif;
            font-size: 14px;
            font-weight: 700;
            padding: 11px 24px;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
            letter-spacing: 0.2px;
        }

        .btn-primary-crm:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 16px rgba(19, 127, 236, 0.3);
        }

        .btn-primary-crm:active {
            transform: scale(0.98);
        }

        .forgot-link {
            color: var(--primary-color);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }

        /* ---- Alert ---- */
        .auth-alert {
            border-radius: 10px;
            font-size: 13px;
            padding: 12px 16px;
            margin-bottom: 20px;
            border: none;
        }

        .auth-alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        /* ---- Responsive ---- */
        @media (max-width: 768px) {
            .auth-left {
                display: none;
            }

            .auth-right {
                padding: 32px 16px;
            }

            .auth-card {
                padding: 36px 24px;
                border-radius: 16px;
            }
        }
    </style>

    @vite(['resources/js/app.js'])
</head>

<body>
    <div class="auth-wrapper">

        {{-- Left: Brand Panel --}}
        <div class="auth-left">
            <div style="position: relative; z-index: 1; width: 100%;">
                <div class="brand-logo-wrap">
                    @if ($logo)
                        <img src="{{ asset('storage/' . $logo) }}" alt="{{ $siteName }}" style="height:36px; object-fit:contain;">
                    @else
                        <span class="material-icons">school</span>
                    @endif
                </div>

                <div class="brand-name">{{ $siteName }}</div>
                <p class="brand-tagline">Streamline your admissions process with powerful lead management and smart analytics.</p>

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
                        <span>Reports & Analytics</span>
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

        {{-- Right: Form Panel --}}
        <div class="auth-right">
            <div class="auth-card">
                {{ $slot }}
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
