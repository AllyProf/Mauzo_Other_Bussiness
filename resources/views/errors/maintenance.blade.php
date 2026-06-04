<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance - {{ $platformName ?? 'SP-POS' }}</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/main.css') }}">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { font-family: 'Century Gothic', 'Segoe UI', sans-serif; background: #f5f5f5; }
        .maintenance-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .maintenance-card { max-width: 520px; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.08); padding: 2.5rem; text-align: center; }
        .maintenance-card h1 { color: #940000; font-size: 1.75rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="maintenance-wrap">
        <div class="maintenance-card">
            <i class="fa fa-wrench fa-3x text-muted mb-3"></i>
            <h1>Under Maintenance</h1>
            <p class="text-muted">{{ $message ?? 'The system is temporarily unavailable. Please try again later.' }}</p>
            <a href="{{ route('login') }}" class="btn btn-primary mt-3" style="background:#940000;border-color:#940000;">
                <i class="fa fa-sign-in"></i> Admin Login
            </a>
        </div>
    </div>
</body>
</html>
