<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Super Admin') — Insight CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f1f5f9; }
        .sidebar { width: 240px; min-height: 100vh; background: #0f172a; position: fixed; top: 0; left: 0; }
        .sidebar .brand { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,.08); color: #fff; font-weight: 700; font-size: 1rem; }
        .sidebar .brand span { color: #6366f1; }
        .sidebar .nav-link { color: rgba(255,255,255,.65); padding: .6rem 1.5rem; font-size: .875rem; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(99,102,241,.15); border-radius: 6px; margin: 0 .75rem; padding: .6rem .75rem; }
        .main-content { margin-left: 240px; padding: 2rem; }
        .topbar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: .75rem 2rem; margin-left: 240px; position: sticky; top: 0; z-index: 100; display: flex; align-items: center; justify-content: space-between; }
        .badge-active { background: #d1fae5; color: #065f46; font-size: .75rem; }
        .badge-inactive { background: #fee2e2; color: #991b1b; font-size: .75rem; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand">⚡ Insight <span>Super Admin</span></div>
    <nav class="mt-3">
        <a class="nav-link {{ request()->routeIs('superadmin.tenants.*') ? 'active' : '' }}" href="{{ route('superadmin.tenants.index') }}">
            🏢 Tenants
        </a>
    </nav>
</div>

<div class="topbar">
    <span class="fw-600 text-dark" style="font-size:.9rem">@yield('page-title', 'Dashboard')</span>
    <form method="POST" action="{{ route('superadmin.logout') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-danger">Logout</button>
    </form>
</div>

<div class="main-content">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
