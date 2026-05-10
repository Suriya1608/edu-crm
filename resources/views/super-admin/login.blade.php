<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login — Insight CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { border: none; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.4); max-width: 400px; width: 100%; }
        .card-header { background: linear-gradient(135deg, #6366f1, #4f46e5); border-radius: 16px 16px 0 0; padding: 2rem; text-align: center; color: #fff; }
        .card-header h4 { font-weight: 700; margin: 0; }
        .card-body { padding: 2rem; }
        .form-label { font-weight: 600; font-size: .875rem; color: #374151; }
        .btn-primary { background: #6366f1; border-color: #6366f1; font-weight: 600; }
        .btn-primary:hover { background: #4f46e5; border-color: #4f46e5; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h4>⚡ Super Admin</h4>
        <p class="mb-0 mt-1 opacity-75" style="font-size:.85rem">Insight CRM Management Portal</p>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('superadmin.login.store') }}">
            @csrf
            @if($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
