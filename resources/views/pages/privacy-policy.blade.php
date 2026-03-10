<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — {{ $siteName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Manrope', sans-serif; background: #f6f7f8; color: #0f172a; }
        .page-header { background: #137fec; color: #fff; padding: 48px 0 32px; }
        .page-header h1 { font-size: 2rem; font-weight: 700; }
        .page-header p { opacity: .85; margin-bottom: 0; }
        .content-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 40px; }
        .content-card h2 { font-size: 1.1rem; font-weight: 700; color: #137fec; margin-top: 2rem; margin-bottom: .6rem; }
        .content-card h2:first-child { margin-top: 0; }
        .content-card p, .content-card li { color: #334155; line-height: 1.75; }
        .content-card hr { border-color: #e2e8f0; }
        .empty-state { text-align: center; padding: 60px 0; color: #64748b; }
        .empty-state .material-icons { font-size: 48px; opacity: .4; display: block; margin-bottom: 12px; }
        .site-footer { border-top: 1px solid #e2e8f0; padding: 20px 0; color: #64748b; font-size: 13px; }
        .site-footer a { color: #137fec; text-decoration: none; }
        .site-footer a:hover { text-decoration: underline; }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <div class="page-header">
        <div class="container" style="max-width:860px;">
            <a href="{{ url('/') }}" class="text-white text-decoration-none d-inline-flex align-items-center gap-2 mb-3" style="font-size:13px; opacity:.8;">
                <span class="material-icons" style="font-size:16px;">arrow_back</span>
                Back to {{ $siteName }}
            </a>
            <h1>Privacy Policy</h1>
            <p>How we collect, use, and protect your information</p>
        </div>
    </div>

    <div class="container py-5" style="max-width:860px;">
        <div class="content-card">
            @if($content)
                {!! $content !!}
            @else
                <div class="empty-state">
                    <span class="material-icons">shield</span>
                    <p class="fw-semibold mb-1">Privacy Policy not yet published</p>
                    <p class="small">Check back soon for our privacy information.</p>
                </div>
            @endif
        </div>
    </div>

    <footer class="site-footer">
        <div class="container d-flex justify-content-center align-items-center gap-3 flex-wrap" style="max-width:860px;">
            <span>© {{ date('Y') }} {{ $siteName }}</span>
            <span>·</span>
            <a href="{{ url('/privacy-policy') }}">Privacy Policy</a>
            <span>·</span>
            <a href="{{ url('/terms-of-service') }}">Terms of Service</a>
        </div>
    </footer>
</body>
</html>
